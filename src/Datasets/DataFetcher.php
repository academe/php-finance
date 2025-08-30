<?php

declare(strict_types=1);

namespace Academe\PhpFinance\Datasets;

use InvalidArgumentException;
use RuntimeException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;

class DataFetcher
{
    private array $defaultHeaders;
    
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private ?CacheItemPoolInterface $cache = null,
        array $defaultHeaders = []
    ) {
        $this->defaultHeaders = array_merge([
            'User-Agent' => 'PHPFinance/1.0',
        ], $defaultHeaders);
    }
    
    public function getFamaFrench(string $dataset = 'F-F_Research_Data_Factors'): array
    {
        $validDatasets = [
            'F-F_Research_Data_Factors',
            'F-F_Research_Data_Factors_daily',
            'F-F_Research_Data_5_Factors_2x3',
            'F-F_Research_Data_5_Factors_2x3_daily',
            'F-F_Momentum_Factor',
            'F-F_Momentum_Factor_daily',
            '6_Portfolios_2x3',
            '25_Portfolios_5x5',
            '100_Portfolios_10x10',
        ];
        
        if (!in_array($dataset, $validDatasets)) {
            throw new InvalidArgumentException('Invalid Fama-French dataset name');
        }
        
        $url = sprintf(
            'http://mba.tuck.dartmouth.edu/pages/faculty/ken.french/ftp/%s_CSV.zip',
            $dataset
        );
        
        $cacheKey = 'famafrench_' . md5($dataset);
        
        if ($this->cache && $cachedData = $this->getFromCache($cacheKey)) {
            return $cachedData;
        }
        
        $zipContent = $this->downloadFile($url);
        $csvContent = $this->extractZipContent($zipContent);
        $data = $this->parseFamaFrenchCSV($csvContent);
        
        if ($this->cache) {
            $this->saveToCache($cacheKey, $data, 86400); // 24 hours
        }
        
        return $data;
    }
    
    public function getFRED(string $series, ?string $startDate = null, ?string $endDate = null, ?string $apiKey = null): array
    {
        if ($apiKey === null) {
            $apiKey = getenv('FRED_API_KEY');
            if (!$apiKey) {
                throw new RuntimeException('FRED API key not provided. Set FRED_API_KEY environment variable or pass it as parameter.');
            }
        }
        
        $params = [
            'series_id' => $series,
            'api_key' => $apiKey,
            'file_type' => 'json',
        ];
        
        if ($startDate) {
            $params['observation_start'] = $startDate;
        }
        
        if ($endDate) {
            $params['observation_end'] = $endDate;
        }
        
        $url = 'https://api.stlouisfed.org/fred/series/observations?' . http_build_query($params);
        
        $cacheKey = 'fred_' . md5($url);
        
        if ($this->cache && $cachedData = $this->getFromCache($cacheKey)) {
            return $cachedData;
        }
        
        try {
            $request = $this->requestFactory->createRequest('GET', $url);
            foreach ($this->defaultHeaders as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
            
            $response = $this->httpClient->sendRequest($request);
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['observations'])) {
                throw new RuntimeException('Invalid response from FRED API');
            }
            
            $result = [];
            foreach ($data['observations'] as $obs) {
                if ($obs['value'] !== '.') {
                    $result[] = [
                        'date' => $obs['date'],
                        'value' => (float)$obs['value'],
                    ];
                }
            }
            
            if ($this->cache) {
                $this->saveToCache($cacheKey, $result, 86400); // 24 hours
            }
            
            return $result;
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException('Failed to fetch data from FRED: ' . $e->getMessage());
        }
    }
    
    public function getShillerData(): array
    {
        $url = 'http://www.econ.yale.edu/~shiller/data/ie_data.xls';
        
        $cacheKey = 'shiller_data';
        
        if ($this->cache && $cachedData = $this->getFromCache($cacheKey)) {
            return $cachedData;
        }
        
        $xlsContent = $this->downloadFile($url);
        
        $data = $this->parseShillerExcel($xlsContent);
        
        if ($this->cache) {
            $this->saveToCache($cacheKey, $data, 604800); // 7 days
        }
        
        return $data;
    }
    
    public function getYahooFinance(string $symbol, ?string $startDate = null, ?string $endDate = null): array
    {
        $startTimestamp = $startDate ? strtotime($startDate) : strtotime('-1 year');
        $endTimestamp = $endDate ? strtotime($endDate) : time();
        
        $url = sprintf(
            'https://query1.finance.yahoo.com/v7/finance/download/%s?period1=%d&period2=%d&interval=1d&events=history',
            urlencode($symbol),
            $startTimestamp,
            $endTimestamp
        );
        
        $cacheKey = 'yahoo_' . md5($url);
        
        if ($this->cache && $cachedData = $this->getFromCache($cacheKey)) {
            return $cachedData;
        }
        
        try {
            $request = $this->requestFactory->createRequest('GET', $url);
            $request = $request->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            
            $response = $this->httpClient->sendRequest($request);
            
            $csvContent = $response->getBody()->getContents();
            $data = $this->parseYahooCSV($csvContent);
            
            if ($this->cache) {
                $this->saveToCache($cacheKey, $data, 3600); // 1 hour
            }
            
            return $data;
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException('Failed to fetch data from Yahoo Finance: ' . $e->getMessage());
        }
    }
    
    private function downloadFile(string $url): string
    {
        try {
            $request = $this->requestFactory->createRequest('GET', $url);
            foreach ($this->defaultHeaders as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
            
            $response = $this->httpClient->sendRequest($request);
            return $response->getBody()->getContents();
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException('Failed to download file: ' . $e->getMessage());
        }
    }
    
    private function extractZipContent(string $zipContent): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpfinance_zip');
        file_put_contents($tempFile, $zipContent);
        
        $zip = new \ZipArchive();
        if ($zip->open($tempFile) !== true) {
            unlink($tempFile);
            throw new RuntimeException('Failed to open zip file');
        }
        
        if ($zip->numFiles === 0) {
            $zip->close();
            unlink($tempFile);
            throw new RuntimeException('Zip file is empty');
        }
        
        $content = $zip->getFromIndex(0);
        $zip->close();
        unlink($tempFile);
        
        return $content;
    }
    
    private function parseFamaFrenchCSV(string $csvContent): array
    {
        $lines = explode("\n", $csvContent);
        $data = [];
        $inData = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            if (preg_match('/^\d{6}/', $line) || preg_match('/^\d{4}/', $line)) {
                $inData = true;
            }
            
            if (!$inData) {
                continue;
            }
            
            if (strpos($line, 'Copyright') !== false || strpos($line, 'Annual') !== false) {
                break;
            }
            
            $parts = preg_split('/\s+/', $line);
            
            if (count($parts) < 2) {
                continue;
            }
            
            $date = $parts[0];
            $values = array_slice($parts, 1);
            
            $data[] = [
                'date' => $date,
                'values' => array_map('floatval', $values),
            ];
        }
        
        return $data;
    }
    
    private function parseYahooCSV(string $csvContent): array
    {
        $lines = explode("\n", $csvContent);
        $headers = str_getcsv(array_shift($lines), escape: '\\');

        $data = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $row = str_getcsv($line, escape: '\\');

            if (count($row) !== count($headers)) {
                continue;
            }
            
            $entry = [];
            foreach ($headers as $i => $header) {
                $value = $row[$i];
                
                if ($header === 'Date') {
                    $entry[$header] = $value;
                } elseif ($value !== 'null' && $value !== '') {
                    $entry[$header] = (float)$value;
                } else {
                    $entry[$header] = null;
                }
            }
            
            $data[] = $entry;
        }
        
        return $data;
    }
    
    private function parseShillerExcel(string $content): array
    {
        return [
            'error' => 'Excel parsing requires additional PHP extensions. Please use CSV alternative or implement Excel parser.',
        ];
    }
    
    private function getFromCache(string $key): ?array
    {
        if (!$this->cache) {
            return null;
        }
        
        try {
            $item = $this->cache->getItem($key);
            
            if ($item->isHit()) {
                return $item->get();
            }
            
            return null;
        } catch (CacheInvalidArgumentException $e) {
            return null;
        }
    }
    
    private function saveToCache(string $key, array $data, int $ttl = 86400): void
    {
        if (!$this->cache) {
            return;
        }
        
        try {
            $item = $this->cache->getItem($key);
            $item->set($data);
            $item->expiresAfter($ttl);
            
            $this->cache->save($item);
        } catch (CacheInvalidArgumentException $e) {
            // Silently fail on cache errors
        }
    }
    
    public function clearCache(): void
    {
        if ($this->cache) {
            $this->cache->clear();
        }
    }
}