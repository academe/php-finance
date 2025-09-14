<?php

use Academe\PhpFinance\Datasets\DataFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

beforeEach(function () {
    $httpClient = new Client(['timeout' => 30]);
    $requestFactory = new HttpFactory();
    $uriFactory = new HttpFactory(); // HttpFactory implements both RequestFactoryInterface and UriFactoryInterface
    $cache = new ArrayAdapter();
    
    $this->fetcher = new DataFetcher($httpClient, $requestFactory, $uriFactory, $cache);
    $this->fetcherNoCache = new DataFetcher($httpClient, $requestFactory, $uriFactory, null);
});

test('can create DataFetcher instance', function () {
    expect($this->fetcher)->toBeInstanceOf(DataFetcher::class);
});

test('can create DataFetcher instance without cache', function () {
    expect($this->fetcherNoCache)->toBeInstanceOf(DataFetcher::class);
});

test('throws exception for invalid Fama-French dataset', function () {
    $this->fetcher->getFamaFrench('invalid_dataset');
})->throws(InvalidArgumentException::class);

test('validates Fama-French dataset names', function () {
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
    
    foreach ($validDatasets as $dataset) {
        expect(fn() => $this->fetcher->getFamaFrench($dataset))
            ->not->toThrow(InvalidArgumentException::class);
    }
})->skip('Requires network access');

test('throws exception when FRED API key not provided', function () {
    putenv('FRED_API_KEY=');
    $this->fetcher->getFRED('DGS10');
})->throws(RuntimeException::class);

test('can clear cache', function () {
    // Test that clearCache method doesn't throw errors
    expect(fn() => $this->fetcher->clearCache())->not->toThrow(\Throwable::class);
    expect(fn() => $this->fetcherNoCache->clearCache())->not->toThrow(\Throwable::class);
});

test('parses Yahoo Finance CSV format', function () {
    $mockCsv = "Date,Open,High,Low,Close,Adj Close,Volume\n";
    $mockCsv .= "2024-01-01,100.00,105.00,99.00,104.00,104.00,1000000\n";
    $mockCsv .= "2024-01-02,104.00,106.00,103.00,105.50,105.50,1100000";
    
    $reflection = new ReflectionClass($this->fetcherNoCache);
    $method = $reflection->getMethod('parseYahooCSV');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->fetcherNoCache, $mockCsv);
    
    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->and($result[0])->toHaveKeys(['Date', 'Open', 'High', 'Low', 'Close', 'Adj Close', 'Volume'])
        ->and($result[0]['Date'])->toBe('2024-01-01')
        ->and($result[0]['Close'])->toBe(104.0);
});

test('parses Fama-French CSV format', function () {
    $mockCsv = "Some header text\n";
    $mockCsv .= "Another header\n";
    $mockCsv .= "202401    2.50   -1.20    0.80    0.42\n";
    $mockCsv .= "202402    3.10   -0.50    1.20    0.45\n";
    $mockCsv .= "\nCopyright notice";
    
    $reflection = new ReflectionClass($this->fetcherNoCache);
    $method = $reflection->getMethod('parseFamaFrenchCSV');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->fetcherNoCache, $mockCsv);
    
    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->and($result[0]['date'])->toBe('202401')
        ->and($result[0]['values'])->toBe([2.50, -1.20, 0.80, 0.42]);
});

test('handles malformed CSV data gracefully', function () {
    $mockCsv = "Date,Close\n";
    $mockCsv .= "2024-01-01,100\n";
    $mockCsv .= "malformed line\n";
    $mockCsv .= "2024-01-02,105";
    
    $reflection = new ReflectionClass($this->fetcherNoCache);
    $method = $reflection->getMethod('parseYahooCSV');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->fetcherNoCache, $mockCsv);
    
    expect($result)->toBeArray()
        ->toHaveCount(2);
});