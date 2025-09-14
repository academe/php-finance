<?php

declare(strict_types=1);

namespace Academe\PhpFinance\Datasets;

use InvalidArgumentException;
use RuntimeException;
use Psr\Http\Client\ClientInterface; // PSR-18
use Psr\Http\Client\ClientExceptionInterface; // PSR-18
use Psr\Http\Message\RequestFactoryInterface; // PSR-17
use Psr\Http\Message\UriFactoryInterface; // PSR-7
use Adbar\Dot;

/**
 * Alternative Yahoo Finance fetcher using the v8 chart API.
 * 
 * The v7 download API now requires authentication, but the v8 chart API
 * is still publicly accessible and provides OHLCV data in JSON format.
 */
class YahooFinanceV8
{
    /**
     * @param ClientInterface $httpClient PSR-18 compatible HTTP client
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory
     * @param UriFactoryInterface $uriFactory PSR-17 URI factory
     */
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private UriFactoryInterface $uriFactory
    ) {}
    
    /**
     * Fetches historical price data from Yahoo Finance v8 API.
     * 
     * @param string $symbol Yahoo Finance ticker symbol
     * @param string|null $startDate Start date in YYYY-MM-DD format
     * @param string|null $endDate End date in YYYY-MM-DD format
     * @param string $interval Data interval (1d, 1wk, 1mo)
     * @return array Array of OHLCV data
     * @throws RuntimeException if request fails
     */
    public function getHistoricalData(
        string $symbol,
        ?string $startDate = null,
        ?string $endDate = null,
        string $interval = '1d'
    ): array {
        $period1 = $startDate ? strtotime($startDate) : strtotime('-1 year');
        $period2 = $endDate ? strtotime($endDate) : time();
        
        // Validate timestamps
        if ($period1 === false || $period2 === false) {
            throw new InvalidArgumentException('Invalid date format provided');
        }
        
        if ($period1 >= $period2) {
            throw new InvalidArgumentException('Start date must be before end date');
        }
        
        // Build the v8 API URL
        $params = [
            'period1' => $period1,
            'period2' => $period2,
            'interval' => $interval,
            'includePrePost' => 'false',
            'events' => 'div|split|earn',
        ];
        
        $uri = $this->uriFactory->createUri('https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($symbol))
            ->withQuery(http_build_query($params));
        
        try {
            $request = $this->requestFactory->createRequest('GET', (string) $uri);
            
            // Set headers that help avoid blocks
            $request = $request
                ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->withHeader('Accept', 'application/json')
                ->withHeader('Accept-Language', 'en-US,en;q=0.9')
                ->withHeader('Cache-Control', 'no-cache')
                ->withHeader('Pragma', 'no-cache');
            
            $response = $this->httpClient->sendRequest($request);
            $content = $response->getBody()->getContents();
            
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Failed to parse JSON response: ' . json_last_error_msg());
            }
            
            return $this->parseV8Response($data);
            
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException('Failed to fetch data from Yahoo Finance: ' . $e->getMessage());
        }
    }
    
    /**
     * Parses the v8 API response into a standardized format.
     * Uses dot notation for cleaner nested data access.
     * 
     * @param array $data Raw API response
     * @return array Parsed OHLCV data
     */
    private function parseV8Response(array $data): array
    {
        $dot = new Dot($data);
        
        // Use dot notation for cleaner access
        $timestamps = $dot->get('chart.result.0.timestamp', []);
        $quote = $dot->get('chart.result.0.indicators.quote.0', []);
        
        if (empty($timestamps) || empty($quote)) {
            throw new RuntimeException('Missing required data in Yahoo Finance response');
        }
        
        $ohlcv = [];
        
        for ($i = 0; $i < count($timestamps); $i++) {
            $ohlcv[] = [
                'Date' => date('Y-m-d', $timestamps[$i]),
                'Open' => $quote['open'][$i] ?? null,
                'High' => $quote['high'][$i] ?? null,
                'Low' => $quote['low'][$i] ?? null,
                'Close' => $quote['close'][$i] ?? null,
                'Volume' => $quote['volume'][$i] ?? null,
            ];
        }
        
        return $ohlcv;
    }
    
    /**
     * Gets real-time quote data for a symbol.
     * Uses dot notation for cleaner nested data access.
     * 
     * @param string $symbol Yahoo Finance ticker symbol
     * @return array Current price data
     */
    public function getQuote(string $symbol): array
    {
        $uri = $this->uriFactory->createUri('https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($symbol));
        
        try {
            $request = $this->requestFactory->createRequest('GET', (string) $uri);
            
            $request = $request
                ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->withHeader('Accept', 'application/json');
            
            $response = $this->httpClient->sendRequest($request);
            $content = $response->getBody()->getContents();
            
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Failed to parse JSON response: ' . json_last_error_msg());
            }
            
            $dot = new Dot($data);
            $meta = $dot->get('chart.result.0.meta', []);
            
            if (empty($meta)) {
                throw new RuntimeException('Invalid quote response from Yahoo Finance');
            }
            
            return [
                'symbol' => $dot->get('chart.result.0.meta.symbol'),
                'regularMarketPrice' => $dot->get('chart.result.0.meta.regularMarketPrice'),
                'regularMarketTime' => date('Y-m-d H:i:s', $dot->get('chart.result.0.meta.regularMarketTime', 0)),
                'regularMarketDayHigh' => $dot->get('chart.result.0.meta.regularMarketDayHigh'),
                'regularMarketDayLow' => $dot->get('chart.result.0.meta.regularMarketDayLow'),
                'regularMarketVolume' => $dot->get('chart.result.0.meta.regularMarketVolume'),
                'previousClose' => $dot->get('chart.result.0.meta.previousClose', $dot->get('chart.result.0.meta.chartPreviousClose')),
                'currency' => $dot->get('chart.result.0.meta.currency'),
                'exchangeName' => $dot->get('chart.result.0.meta.exchangeName'),
            ];
            
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException('Failed to fetch quote from Yahoo Finance: ' . $e->getMessage());
        }
    }
}