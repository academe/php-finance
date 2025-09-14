# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Testing
```bash
composer test          # Run full Pest test suite
vendor/bin/pest       # Run tests directly
vendor/bin/pest tests/Unit/Returns/TSeriesTest.php  # Run a specific test file
vendor/bin/pest --filter "test name"  # Run tests matching a pattern
```

### Code Quality
```bash
composer phpstan      # Run PHPStan static analysis (level max)
composer phpcs        # Check code style against PSR standards
composer phpcbf       # Auto-fix code style issues
```

### Dependencies
```bash
composer install      # Install dependencies
composer update       # Update dependencies
```

## Architecture

This is a PHP port of the Python pyfinance library for quantitative finance. The codebase follows a domain-driven structure under the `Academe\PhpFinance` namespace:

### Core Modules
- **Returns\TSeries**: Time series analysis for financial returns. Calculates metrics like Sharpe ratio, max drawdown, VaR, CVaR.
- **Options\BlackScholes**: Black-Scholes-Merton option pricing with Greeks calculation.
- **Statistics\OLS**: Ordinary Least Squares regression with comprehensive statistics.
- **Statistics\RollingOLS**: Rolling window OLS regression for time-varying analysis.
- **General\Utils**: Static utility functions for financial calculations (active share, tracking error, etc.).
- **Datasets**: Data fetching system using adapter pattern for different financial data sources.

### Data Source Architecture (Adapter Pattern)
The library uses an adapter pattern for data sources, similar to Laravel database drivers or Flysystem:

- **DataManager**: Main facade for managing multiple data source adapters
- **Adapters**: Individual adapters for each data source (Yahoo Finance, FRED, Fama-French)
- **Contracts**: Interfaces defining adapter capabilities (DataSourceAdapterInterface, HistoricalDataInterface, etc.)
- **AbstractAdapter**: Base class providing common functionality (caching, HTTP requests, etc.)

Key adapters:
- **YahooFinanceAdapter**: Historical and real-time stock data using v8 API
- **FamaFrenchAdapter**: Academic factor data and portfolio returns
- **FredAdapter**: Federal Reserve economic data (requires API key)

Third-party adapters can be created by implementing the adapter interfaces.

### Key Design Principles
- **Strict Typing**: All files use `declare(strict_types=1)` and PHP 8.3+ type declarations.
- **PSR Compliance**: Uses PSR-18 (HTTP Client), PSR-17 (HTTP Factory), and PSR-6 (Cache) interfaces for flexibility.
- **Mathematical Precision**: Relies on `markrogoyski/math-php` for accurate mathematical operations.
- **Constructor Injection**: All configuration is passed through constructors with validation.
- **Dot Notation**: Uses `adbar/dot-notation` for clean access to nested array/object data from API responses.

### Testing Approach
Tests use Pest PHP framework with expressive syntax. Test files mirror the source structure in `tests/Unit/` and `tests/Feature/`. Tests extensively validate mathematical correctness against expected values.

### Development Notes
- The library maintains API compatibility with the original Python pyfinance library.
- All mathematical formulas and concepts are documented in PHPDoc comments.
- Error handling uses `InvalidArgumentException` for invalid inputs with descriptive messages.
- Optional caching can be configured for data fetching operations to improve performance.

### Yahoo Finance API Changes
- **Important**: Yahoo Finance v7 download API now requires authentication (returns 401 Unauthorized).
- The DataFetcher has been updated to use the v8 chart API which is still publicly accessible.
- The v8 API returns JSON instead of CSV, but the library maintains the same output format for compatibility.
- If you encounter authentication errors, ensure you're using the latest version of the DataFetcher class.