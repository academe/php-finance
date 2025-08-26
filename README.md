# PHP Finance

A comprehensive PHP port of the pyfinance library for quantitative finance and investment analysis.

## Features

- **Time Series Analysis**: Statistical analysis of financial returns with TSeries class
- **Options Pricing**: Black-Scholes-Merton option valuation with Greeks calculation
- **Regression Analysis**: OLS regression with rolling window capabilities
- **Financial Data**: Fetch data from Fama-French, FRED, Yahoo Finance, and more
- **Risk Metrics**: VaR, CVaR, Sharpe ratio, maximum drawdown, and other risk measures
- **Portfolio Analytics**: Active share, tracking error, information ratio

## Installation

Install via Composer:

```bash
composer require academe/php-finance
```

## Quick Start

### Time Series Analysis

```php
use Academe\PhpFinance\Returns\TSeries;

// Daily returns data
$returns = [0.01, -0.02, 0.03, -0.01, 0.02, 0.015, -0.005];

// Create time series
$ts = new TSeries($returns, frequency: 'D');

// Calculate key metrics
echo "Annualized Return: " . $ts->anlzdRet() . "\n";
echo "Sharpe Ratio: " . $ts->sharpeRatio(0.02) . "\n";
echo "Maximum Drawdown: " . $ts->maxDrawdown() . "\n";
echo "Volatility: " . $ts->std() * sqrt(252) . "\n";
```

### Options Pricing

```php
use Academe\PhpFinance\Options\BlackScholes;

// Create Black-Scholes model
$bs = new BlackScholes(
    spotPrice: 100,        // Current stock price
    strikePrice: 110,      // Strike price
    timeToExpiry: 1.0,     // Time to expiry (1 year)
    riskFreeRate: 0.05,    // Risk-free rate (5%)
    volatility: 0.2,       // Volatility (20%)
    dividendYield: 0.02    // Dividend yield (2%)
);

// Calculate option prices and Greeks
echo "Call Price: " . $bs->callPrice() . "\n";
echo "Put Price: " . $bs->putPrice() . "\n";
echo "Call Delta: " . $bs->callDelta() . "\n";
echo "Gamma: " . $bs->gamma() . "\n";
echo "Vega: " . $bs->vega() . "\n";

// Calculate implied volatility
$marketPrice = 8.5;
$impliedVol = $bs->impliedVolatility($marketPrice, 'call');
echo "Implied Volatility: " . ($impliedVol * 100) . "%\n";
```

### Regression Analysis

```php
use Academe\PhpFinance\Statistics\OLS;
use Academe\PhpFinance\Statistics\RollingOLS;

// Asset returns vs market returns
$assetReturns = [0.05, 0.03, 0.07, 0.02, 0.04, 0.06, 0.01];
$marketReturns = [[0.04], [0.02], [0.06], [0.01], [0.03], [0.05], [0.02]];

// Single regression
$ols = new OLS($assetReturns, $marketReturns);
echo "Alpha: " . $ols->getCoefficients()[0] . "\n";
echo "Beta: " . $ols->getCoefficients()[1] . "\n";
echo "R-squared: " . $ols->getRSquared() . "\n";

// Rolling regression (30-day window)
$rolling = new RollingOLS($assetReturns, $marketReturns, 3);
$betaSeries = $rolling->getCoefficientSeries(1);
```

### Financial Data

```php
use Academe\PhpFinance\Datasets\DataFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

// Create HTTP client and cache
$httpClient = new Client(['timeout' => 30]);
$requestFactory = new HttpFactory();
$cache = new FilesystemAdapter('phpfinance', 3600, '/tmp/cache');

$fetcher = new DataFetcher($httpClient, $requestFactory, $cache);

// Get Fama-French factors
$factors = $fetcher->getFamaFrench('F-F_Research_Data_Factors');

// Get FRED data (requires API key)
putenv('FRED_API_KEY=your_api_key_here');
$data = $fetcher->getFRED('DGS10', '2020-01-01', '2024-01-01');

// Get Yahoo Finance data
$prices = $fetcher->getYahooFinance('AAPL', '2023-01-01', '2024-01-01');
```

### Risk and Performance Metrics

```php
use Academe\PhpFinance\General\Utils;

$returns = [-0.02, 0.01, 0.03, -0.01, 0.02, -0.015, 0.025];

// Risk metrics
echo "Value at Risk (95%): " . Utils::valueAtRisk($returns, 0.95) . "\n";
echo "CVaR (95%): " . Utils::conditionalValueAtRisk($returns, 0.95) . "\n";

// Performance metrics
echo "Compound Return: " . Utils::compoundReturn($returns) . "\n";
echo "Annualized Return: " . Utils::annualizedReturn($returns, 252) . "\n";
echo "Annualized Volatility: " . Utils::annualizedVolatility($returns, 252) . "\n";

// Portfolio analytics
$portfolio = ['AAPL' => 0.3, 'GOOGL' => 0.2, 'MSFT' => 0.25, 'AMZN' => 0.25];
$benchmark = ['AAPL' => 0.25, 'GOOGL' => 0.25, 'MSFT' => 0.25, 'AMZN' => 0.25];
echo "Active Share: " . Utils::activeShare($portfolio, $benchmark) . "\n";
```

## Advanced Examples

### Multi-Factor Regression

```php
use Academe\PhpFinance\Statistics\OLS;

// Three-factor model (Market, SMB, HML)
$assetReturns = [0.05, 0.03, 0.07, 0.02, 0.04];
$factors = [
    [0.04, 0.01, -0.01],  // Market, SMB, HML
    [0.02, -0.01, 0.02],
    [0.06, 0.02, 0.01],
    [0.01, -0.02, -0.01],
    [0.03, 0.01, 0.01]
];

$model = new OLS($assetReturns, $factors);
$coefficients = $model->getCoefficients();

echo "Alpha: " . $coefficients[0] . "\n";
echo "Market Beta: " . $coefficients[1] . "\n";
echo "SMB Loading: " . $coefficients[2] . "\n";
echo "HML Loading: " . $coefficients[3] . "\n";
```

### Option Strategy Analysis

```php
use Academe\PhpFinance\Options\BlackScholes;

// Long call spread
$longCall = new BlackScholes(100, 105, 0.25, 0.05, 0.2);
$shortCall = new BlackScholes(100, 115, 0.25, 0.05, 0.2);

$spreadCost = $longCall->callPrice() - $shortCall->callPrice();
$maxProfit = (115 - 105) - $spreadCost;
$breakeven = 105 + $spreadCost;

echo "Spread Cost: $" . number_format($spreadCost, 2) . "\n";
echo "Max Profit: $" . number_format($maxProfit, 2) . "\n";
echo "Breakeven: $" . number_format($breakeven, 2) . "\n";
```

## Testing

Run tests with Pest:

```bash
composer test
```

Run static analysis:

```bash
composer phpstan
```

## Requirements

- PHP >= 8.3
- ext-json
- PSR-18 HTTP Client implementation
- PSR-17 HTTP Message Factory implementation  
- PSR-6 Cache implementation (optional)
- markrogoyski/math-php

## PSR Compatibility

This library uses PSR standards for maximum flexibility:

- **PSR-18**: HTTP Client for data fetching
- **PSR-17**: HTTP Message Factory for creating requests
- **PSR-6**: Cache for improved performance (optional)

You can use any compatible implementation. Popular choices include:

- **HTTP Client**: Guzzle, Symfony HTTP Client, Buzz
- **PSR-7/17**: guzzlehttp/psr7, nyholm/psr7, laminas/laminas-diactoros
- **Cache**: Symfony Cache, doctrine/cache, cache/cache

## Documentation

For detailed documentation, see the [Wiki](../../wiki) or explore the source code.

## License

MIT License

## Contributing

Contributions are welcome! Please submit pull requests or open issues on GitHub.
