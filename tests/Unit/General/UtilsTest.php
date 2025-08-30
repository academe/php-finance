<?php

use Academe\PhpFinance\General\Utils;

test('calculates active share correctly', function () {
    $portfolio = [
        'AAPL' => 0.3,
        'GOOGL' => 0.2,
        'MSFT' => 0.25,
        'AMZN' => 0.25,
    ];
    
    $benchmark = [
        'AAPL' => 0.25,
        'GOOGL' => 0.25,
        'MSFT' => 0.25,
        'AMZN' => 0.2,
        'TSLA' => 0.05,
    ];
    
    $activeShare = Utils::activeShare($portfolio, $benchmark);
    
    expect($activeShare)->toBeFloat()
        ->toBeGreaterThanOrEqual(0)
        ->toBeLessThanOrEqual(1);
});

test('throws exception for non-normalized weights in active share', function () {
    $portfolio = ['AAPL' => 0.5, 'GOOGL' => 0.3];
    $benchmark = ['AAPL' => 0.5, 'GOOGL' => 0.5];
    
    Utils::activeShare($portfolio, $benchmark);
})->throws(InvalidArgumentException::class);

test('generates returns distribution', function () {
    $samples = Utils::returnsDistribution(0.1, 0.2, n: 100);
    
    expect($samples)->toBeArray()
        ->toHaveCount(100);
    
    $mean = array_sum($samples) / count($samples);
    expect($mean)->toEqualWithDelta(0.1, 0.1);
});

test('throws exception for negative standard deviation in returns distribution', function () {
    Utils::returnsDistribution(0.1, -0.2);
})->throws(InvalidArgumentException::class);

test('calculates Kelly formula correctly', function () {
    // $kelly = Utils::kellyFormula(0.6, 1.5, 1.0);
    
    // expect($kelly)->toBeFloat()
    //     ->toEqualWithDelta(0.2, 0.01);

    // Test case 1: Original values
    $kelly1 = Utils::kellyFormula(0.6, 1.5, 1.0);
    expect($kelly1)->toEqualWithDelta(0.3333, 0.01);
    
    // Test case 2: Even odds
    $kelly2 = Utils::kellyFormula(0.6, 1.0, 1.0);
    expect($kelly2)->toEqualWithDelta(0.2, 0.01);
    
    // Test case 3: Unfavorable bet (should be negative)
    $kelly3 = Utils::kellyFormula(0.4, 1.0, 1.0);
    expect($kelly3)->toEqualWithDelta(-0.2, 0.01);
});

test('throws exception for invalid win probability in Kelly formula', function () {
    Utils::kellyFormula(1.5, 1.0, 1.0);
})->throws(InvalidArgumentException::class);

test('throws exception for negative amounts in Kelly formula', function () {
    Utils::kellyFormula(0.6, -1.0, 1.0);
})->throws(InvalidArgumentException::class);

test('calculates compound return correctly', function () {
    $returns = [0.1, 0.05, -0.02, 0.03];
    $compoundReturn = Utils::compoundReturn($returns);
    
    expect($compoundReturn)->toEqualWithDelta(0.1667, 0.001);
});

test('calculates geometric mean correctly', function () {
    $returns = [0.1, 0.05, 0.08];
    $geoMean = Utils::geometricMean($returns);
    
    expect($geoMean)->toBeFloat()
        ->toBeGreaterThan(0)
        ->toBeLessThan(0.1);
});

test('throws exception for returns less than -100% in geometric mean', function () {
    Utils::geometricMean([0.1, -1.5, 0.05]);
})->throws(InvalidArgumentException::class);

test('calculates Value at Risk correctly', function () {
    $returns = [-0.05, -0.03, -0.02, -0.01, 0, 0.01, 0.02, 0.03, 0.04, 0.05];
    $var = Utils::valueAtRisk($returns, 0.95);
    
    expect($var)->toBeLessThan(0);
});

test('throws exception for invalid confidence level in VaR', function () {
    Utils::valueAtRisk([0.1, 0.2], 1.5);
})->throws(InvalidArgumentException::class);

test('calculates Conditional Value at Risk correctly', function () {
    $returns = [-0.05, -0.03, -0.02, -0.01, 0, 0.01, 0.02, 0.03, 0.04, 0.05];
    $cvar = Utils::conditionalValueAtRisk($returns, 0.95);
    
    expect($cvar)->toBeLessThan(0)
        ->toBeLessThanOrEqual(Utils::valueAtRisk($returns, 0.95));
});

test('calculates maximum drawdown correctly', function () {
    $prices = [100, 110, 105, 95, 100, 90, 95];
    $maxDD = Utils::maxDrawdown($prices);
    
    expect($maxDD)->toBeLessThan(0)
        ->toEqualWithDelta(-0.1818, 0.0001);
});

test('calculates Calmar ratio', function () {
    $returns = [0.01, -0.02, 0.03, -0.01, 0.02, 0.01, -0.01, 0.02];
    $calmar = Utils::calmarRatio($returns, 252);
    
    expect($calmar)->toBeFloat();
});

test('calculates annualized return', function () {
    $dailyReturns = array_fill(0, 252, 0.0004);
    $annualReturn = Utils::annualizedReturn($dailyReturns, 252);
    
    expect($annualReturn)->toBeFloat()
        ->toBeGreaterThan(0.09)
        ->toBeLessThan(0.11);
});

test('calculates annualized volatility', function () {
    $returns = [0.01, -0.02, 0.03, -0.01, 0.02];
    $annualVol = Utils::annualizedVolatility($returns, 252);
    
    expect($annualVol)->toBeFloat()->toBeGreaterThan(0);
});

test('calculates correlation correctly', function () {
    $x = [1, 2, 3, 4, 5];
    $y = [2, 4, 6, 8, 10];
    
    $correlation = Utils::correlation($x, $y);
    
    expect($correlation)->toEqualWithDelta(1.0, 0.0001);
});

test('calculates negative correlation correctly', function () {
    $x = [1, 2, 3, 4, 5];
    $y = [5, 4, 3, 2, 1];
    
    $correlation = Utils::correlation($x, $y);
    
    expect($correlation)->toEqualWithDelta(-1.0, 0.0001);
});

test('throws exception for empty arrays in correlation', function () {
    Utils::correlation([], []);
})->throws(InvalidArgumentException::class);

test('throws exception for mismatched array lengths in correlation', function () {
    Utils::correlation([1, 2, 3], [1, 2]);
})->throws(InvalidArgumentException::class);

test('returns zero correlation for constant arrays', function () {
    $x = [1, 1, 1, 1, 1];
    $y = [2, 3, 4, 5, 6];
    
    $correlation = Utils::correlation($x, $y);
    
    expect($correlation)->toBe(0.0);
});