<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Academe\PhpFinance\Datasets\DataManager;
use Academe\PhpFinance\Datasets\Adapters\YahooFinance\YahooFinanceAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

echo "=== PHP Finance Data Adapters Example ===\n\n";

// Setup dependencies
$httpClient = new Client(['timeout' => 30, 'verify' => false]);
$requestFactory = new HttpFactory();
$uriFactory = new HttpFactory();
$cache = new ArrayAdapter(); // In-memory cache for this example

// Create the data manager
$dataManager = new DataManager($httpClient, $requestFactory, $uriFactory, $cache);

// Example 1: List available adapters
echo "1. Available Adapters\n";
echo "--------------------\n";

$adapterInfo = $dataManager->getAdapterInfo();
foreach ($adapterInfo as $name => $info) {
    echo "- $name:\n";
    echo "  Available: " . ($info['available'] ? 'Yes' : 'No') . "\n";
    echo "  Features: " . implode(', ', $info['features']) . "\n";
    if (!empty($info['required_config'])) {
        echo "  Required config: " . implode(', ', $info['required_config']) . "\n";
    }
    echo "\n";
}

// Example 2: Yahoo Finance
echo "2. Yahoo Finance Adapter\n";
echo "-----------------------\n";

try {
    $yahoo = $dataManager->yahoo();
    
    // Get historical data
    echo "Fetching AAPL historical data...\n";
    $historicalData = $yahoo->getHistoricalData('AAPL', '2024-11-01', '2024-11-10');
    
    echo "Retrieved " . count($historicalData) . " data points\n";
    if (!empty($historicalData)) {
        $first = $historicalData[0];
        echo "First record: {$first['date']} - Close: \$" . number_format($first['close'], 2) . "\n";
    }
    
    // Get real-time quote
    echo "\nFetching AAPL real-time quote...\n";
    $quote = $yahoo->getQuote('AAPL');
    echo "Current price: \$" . number_format($quote['price'], 2) . "\n";
    echo "Previous close: \$" . number_format($quote['previous_close'], 2) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 3: Fama-French
echo "3. Fama-French Adapter\n";
echo "---------------------\n";

try {
    $famaFrench = $dataManager->famaFrench();
    
    // Get available datasets
    echo "Available datasets:\n";
    $datasets = $famaFrench->getAvailableDatasets();
    foreach (array_slice($datasets, 0, 3) as $id => $description) {
        echo "- $id: $description\n";
    }
    
    // Get 3-factor model data
    echo "\nFetching 3-factor model data...\n";
    $factors = $famaFrench->getFactorReturns('monthly', 3);
    
    echo "Dataset: " . $factors['dataset'] . "\n";
    echo "Records: " . $factors['count'] . "\n";
    
    if (!empty($factors['data'])) {
        $latest = end($factors['data']);
        echo "Latest data point: {$latest['date']}\n";
        echo "  Market-RF: " . number_format($latest['Mkt-RF'] * 100, 2) . "%\n";
        echo "  SMB: " . number_format($latest['SMB'] * 100, 2) . "%\n";
        echo "  HML: " . number_format($latest['HML'] * 100, 2) . "%\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 4: FRED (requires API key)
echo "4. FRED Adapter\n";
echo "--------------\n";

// Check if FRED_API_KEY is available
$fredApiKey = getenv('FRED_API_KEY');

if ($fredApiKey) {
    try {
        // Register FRED adapter with API key
        $dataManager->registerFredAdapter($fredApiKey);
        $fred = $dataManager->fred();
        
        echo "Fetching 10-Year Treasury Rate...\n";
        $treasuryData = $fred->getSeries('DGS10', '2024-10-01', '2024-11-01');
        
        echo "Series: " . $treasuryData['series_id'] . "\n";
        echo "Title: " . $treasuryData['title'] . "\n";
        echo "Records: " . count($treasuryData['data']) . "\n";
        
        if (!empty($treasuryData['data'])) {
            $latest = end($treasuryData['data']);
            echo "Latest: {$latest['date']} - " . number_format($latest['value'], 2) . "%\n";
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "FRED API key not found. Set FRED_API_KEY environment variable.\n";
    echo "Get your free API key at: https://fred.stlouisfed.org/docs/api/\n";
}

echo "\n";

// Example 5: Custom adapter registration
echo "5. Custom Adapter Registration\n";
echo "-----------------------------\n";

// You can create and register custom adapters
class CustomAdapter extends \Academe\PhpFinance\Datasets\Adapters\AbstractAdapter
{
    public function getName(): string
    {
        return 'custom';
    }
    
    public function getSupportedFeatures(): array
    {
        return ['custom_feature'];
    }
    
    public function getRequiredConfig(): array
    {
        return [];
    }
}

// Register the custom adapter
$customAdapter = new CustomAdapter($httpClient, $requestFactory, $uriFactory, $cache);
$dataManager->registerAdapter('custom', $customAdapter);

echo "Custom adapter registered: " . $dataManager->hasAdapter('custom') . "\n";
echo "Custom adapter name: " . $dataManager->getAdapter('custom')->getName() . "\n";

echo "\n";

// Example 6: Working with multiple adapters
echo "6. Multiple Data Sources\n";
echo "-----------------------\n";

echo "Comparing data from different sources:\n\n";

try {
    // Get stock price from Yahoo
    $yahooQuote = $dataManager->yahoo()->getQuote('SPY');
    echo "SPY (Yahoo): \$" . number_format($yahooQuote['price'], 2) . "\n";
    
    // Get market factors from Fama-French
    $factors = $dataManager->famaFrench()->getFactorReturns('daily', 3);
    if (!empty($factors['data'])) {
        $latest = end($factors['data']);
        echo "Market Factor (Fama-French): " . number_format($latest['Mkt-RF'] * 100, 2) . "%\n";
    }
    
    // If FRED is available, get VIX
    if ($dataManager->hasAdapter('fred')) {
        $vix = $dataManager->fred()->getSeries('VIXCLS', date('Y-m-d', strtotime('-5 days')));
        if (!empty($vix['data'])) {
            $latest = end($vix['data']);
            echo "VIX (FRED): " . number_format($latest['value'], 2) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error comparing sources: " . $e->getMessage() . "\n";
}

echo "\n=== Example Complete ===\n";