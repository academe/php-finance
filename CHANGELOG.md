# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-08-26

### Added
- Complete port of pyfinance Python library to PHP
- TSeries class for financial time series analysis
  - Statistical measures (mean, std, variance, min, max)
  - Return calculations (annualized, cumulative, excess)
  - Risk metrics (Sharpe ratio, beta, alpha, tracking error)
  - Drawdown analysis (max drawdown, drawdown index)
  - Performance ratios (information ratio, Sortino ratio)
- BlackScholes class for options pricing
  - European call and put option valuation
  - All Greeks calculation (delta, gamma, vega, theta, rho)
  - Implied volatility calculation using Newton-Raphson method
  - Support for dividend yield
- OLS regression analysis
  - Single and multiple regression
  - Statistical inference (t-tests, F-test, p-values)
  - Rolling window regression (RollingOLS)
  - Comprehensive summary statistics
- DataFetcher for financial data retrieval
  - Fama-French research factors
  - FRED economic data
  - Yahoo Finance stock data
  - PSR-6 cache integration for improved performance
  - PSR-18 HTTP client compatibility
- General utilities module
  - Active share calculation
  - Kelly formula for optimal bet sizing
  - Risk metrics (VaR, CVaR, maximum drawdown)
  - Return distribution generation
  - Performance metrics (Calmar ratio, correlation)
- Comprehensive test suite using Pest PHP
- Complete documentation and examples

### Technical Details
- PHP 8.3+ required
- Uses MathPHP for statistical computations
- PSR-18 HTTP client interface for data fetching
- PSR-17 HTTP message factory interface
- PSR-6 cache interface for performance optimization
- Pest testing framework
- PHPStan static analysis
- Composer package management

### Dependencies
- markrogoyski/math-php: ^2.0
- psr/http-client: ^1.0
- psr/http-factory: ^1.0  
- psr/cache: ^3.0
- pestphp/pest: ^2.0 (dev)
- phpstan/phpstan: ^1.0 (dev)

### Architecture
- Follows PSR standards for maximum interoperability
- Dependency injection ready
- Framework agnostic design
- Comprehensive error handling with proper exception types