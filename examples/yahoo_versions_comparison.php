<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Academe\PhpFinance\Datasets\DataManager;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

echo "=== Yahoo Finance v7 vs v8 Comparison ===\n\n";

// Setup
$httpClient = new Client(['timeout' => 30, 'verify' => false]);
$requestFactory = new HttpFactory();
$uriFactory = new HttpFactory();

$dataManager = new DataManager($httpClient, $requestFactory, $uriFactory);

$symbol = 'AAPL';
$startDate = '2024-11-01';
$endDate = '2024-11-10';

echo "Comparing data for $symbol from $startDate to $endDate\n\n";

// Test Yahoo Finance v8 (default)
echo "1. Yahoo Finance v8 API (Chart/JSON)\n";
echo "====================================\n";

try {
    $v8 = $dataManager->yahooV8();
    
    echo "Features: " . implode(', ', $v8->getSupportedFeatures()) . "\n";
    echo "Auth required: No\n\n";
    
    $v8Data = $v8->getHistoricalData($symbol, $startDate, $endDate);
    
    echo "Retrieved " . count($v8Data) . " records\n";
    
    if (!empty($v8Data)) {
        $first = $v8Data[0];
        echo "Sample record (v8):\n";
        echo "  Date: {$first['date']}\n";
        echo "  Open: \$" . number_format($first['open'] ?? 0, 2) . "\n";
        echo "  Close: \$" . number_format($first['close'] ?? 0, 2) . "\n";
        echo "  Volume: " . number_format($first['volume'] ?? 0) . "\n";
        echo "  Adjusted Close: " . (isset($first['adjusted_close']) ? '$' . number_format($first['adjusted_close'], 2) : 'Not available') . "\n";
    }
    
    echo "\n✅ v8 API working successfully\n";
    
} catch (Exception $e) {
    echo "❌ v8 Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat('-', 60) . "\n\n";

// Test Yahoo Finance v7 (legacy)
echo "2. Yahoo Finance v7 API (CSV/Legacy)\n";
echo "====================================\n";

try {
    $v7 = $dataManager->yahooV7();
    
    echo "Features: " . implode(', ', $v7->getSupportedFeatures()) . "\n";
    echo "Auth required: YES (likely to fail without authentication)\n\n";
    
    $v7Data = $v7->getHistoricalData($symbol, $startDate, $endDate);
    
    echo "Retrieved " . count($v7Data) . " records\n";
    
    if (!empty($v7Data)) {
        $first = $v7Data[0];
        echo "Sample record (v7):\n";
        echo "  Date: {$first['date']}\n";
        echo "  Open: \$" . number_format($first['open'] ?? 0, 2) . "\n";
        echo "  Close: \$" . number_format($first['close'] ?? 0, 2) . "\n";
        echo "  Volume: " . number_format($first['volume'] ?? 0) . "\n";
        echo "  Adjusted Close: " . (isset($first['adjusted_close']) ? '$' . number_format($first['adjusted_close'], 2) : 'Not available') . "\n";
    }
    
    echo "\n✅ v7 API working (you have authentication!)\n";
    
} catch (Exception $e) {
    echo "❌ v7 Error (expected): " . $e->getMessage() . "\n";
    echo "\nThis is expected unless you have authentication credentials.\n";
    echo "v7 advantages: Provides adjusted close prices and cleaner CSV format.\n";
}

echo "\n" . str_repeat('-', 60) . "\n\n";

// Comparison summary
echo "3. API Comparison Summary\n";
echo "========================\n\n";

echo "Yahoo Finance v8 (Recommended):\n";
echo "  ✅ No authentication required\n";
echo "  ✅ JSON format (structured data)\n";
echo "  ✅ Real-time quotes available\n";
echo "  ✅ Multiple intervals supported\n";
echo "  ❌ No adjusted close prices\n";
echo "  ❌ More complex data structure\n\n";

echo "Yahoo Finance v7 (Legacy):\n";
echo "  ❌ Requires authentication (401 errors)\n";
echo "  ✅ Provides adjusted close prices\n";
echo "  ✅ Clean CSV format\n";
echo "  ✅ Historical compatibility\n";
echo "  ❌ Limited to historical data only\n";
echo "  ❌ Fewer interval options\n\n";

// Usage recommendations
echo "4. Usage Recommendations\n";
echo "========================\n\n";

echo "Use v8 when:\n";
echo "  - You need real-time quotes\n";
echo "  - You don't have Yahoo authentication\n";
echo "  - You want guaranteed public access\n";
echo "  - You need intraday intervals\n\n";

echo "Use v7 when:\n";
echo "  - You need adjusted close prices\n";
echo "  - You have authentication credentials\n";
echo "  - You prefer CSV format\n";
echo "  - You're maintaining legacy systems\n\n";

// Show adapter info
echo "5. Registered Adapters\n";
echo "======================\n\n";

$adapterInfo = $dataManager->getAdapterInfo();
foreach (['yahoo', 'yahoo_v8', 'yahoo_v7'] as $name) {
    if (isset($adapterInfo[$name])) {
        $info = $adapterInfo[$name];
        echo "$name:\n";
        echo "  Available: " . ($info['available'] ? 'Yes' : 'No') . "\n";
        echo "  Features: " . implode(', ', $info['features']) . "\n";
        if (!empty($info['required_config'])) {
            echo "  Required config: " . implode(', ', $info['required_config']) . "\n";
        }
        echo "\n";
    }
}

// Authentication example for v7
echo "6. Adding Authentication to v7 (Example)\n";
echo "========================================\n\n";

echo "// Future: If you have authentication credentials\n";
echo "/*\n";
echo "\$v7Auth = \$dataManager->yahooV7()->withAuth([\n";
echo "    'cookies' => 'your_session_cookies_here',\n";
echo "    // or\n";
echo "    'api_key' => 'your_api_key_here'\n";
echo "]);\n";
echo "\n";
echo "\$authData = \$v7Auth->getHistoricalData('AAPL', '2024-01-01', '2024-12-31');\n";
echo "*/\n\n";

echo "=== Comparison Complete ===\n";