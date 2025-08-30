<?php

use Academe\PhpFinance\Returns\TSeries;

beforeEach(function () {
    $this->sampleReturns = [0.01, -0.02, 0.03, -0.01, 0.02];
    $this->sampleIndex = [1, 2, 3, 4, 5];
});

test('can create TSeries with data and index', function () {
    $ts = new TSeries($this->sampleReturns, $this->sampleIndex);
    
    expect($ts)->toBeInstanceOf(TSeries::class)
        ->and($ts->getData())->toBe($this->sampleReturns)
        ->and($ts->getIndex())->toBe($this->sampleIndex);
});

test('creates default index when not provided', function () {
    $ts = new TSeries($this->sampleReturns);
    
    expect($ts->getIndex())->toBe([0, 1, 2, 3, 4]);
});

test('throws exception for empty data', function () {
    new TSeries([]);
})->throws(InvalidArgumentException::class);

test('throws exception for mismatched data and index lengths', function () {
    new TSeries([1, 2, 3], [1, 2]);
})->throws(InvalidArgumentException::class);

test('throws exception for non-monotonic index', function () {
    new TSeries([1, 2, 3], [1, 3, 2]);
})->throws(InvalidArgumentException::class);

test('calculates basic statistics correctly', function () {
    $ts = new TSeries($this->sampleReturns);
    
    expect($ts->count())->toBe(5)
        ->and($ts->sum())->toBeFloat()->toEqualWithDelta(0.03, 0.0001)
        ->and($ts->mean())->toBeFloat()->toEqualWithDelta(0.006, 0.0001)
        ->and($ts->min())->toBe(-0.02)
        ->and($ts->max())->toBe(0.03);
});

test('calculates standard deviation and variance', function () {
    $ts = new TSeries($this->sampleReturns);
    
    $std = $ts->std();
    $variance = $ts->variance();
    
    expect($std)->toBeFloat()->toBeGreaterThan(0)->toBeLessThan(0.1)
        ->and($variance)->toBeFloat()->toBeGreaterThan(0)
        ->and($variance)->toEqualWithDelta(pow($std, 2), 0.0001);
});

test('calculates cumulative sum correctly', function () {
    $ts = new TSeries([1, 2, 3, 4, 5]);
    $cumsum = $ts->cumsum();
    
    expect($cumsum->getData())->toBe([1, 3, 6, 10, 15]);
});

test('calculates cumulative product correctly', function () {
    $ts = new TSeries([2, 3, 4]);
    $cumprod = $ts->cumprod();
    
    expect($cumprod->getData())->toBe([2, 6, 24]);
});

test('calculates return relatives correctly', function () {
    $ts = new TSeries($this->sampleReturns);
    $rels = $ts->retRels();
    
    $expected = array_map(fn($x) => 1 + $x, $this->sampleReturns);
    expect($rels->getData())->toBe($expected);
});

test('calculates return index correctly', function () {
    $ts = new TSeries([0.1, 0.05, -0.02]);
    $idx = $ts->retIdx(100);
    
    $actual = $idx->getData();
    expect($actual[0])->toEqualWithDelta(110, 0.01)
        ->and($actual[1])->toEqualWithDelta(115.5, 0.01)
        ->and($actual[2])->toEqualWithDelta(113.19, 0.01);
});

test('calculates annualized return', function () {
    $ts = new TSeries([0.1, 0.1, 0.1]);
    
    expect($ts->anlzdRet())->toBeFloat()->toBeGreaterThan(0);
});

test('calculates cumulative return', function () {
    $ts = new TSeries([0.1, 0.1, 0.1]);
    
    expect($ts->cumRet())->toEqualWithDelta(0.331, 0.001);
});

test('calculates excess returns with scalar benchmark', function () {
    $ts = new TSeries([0.05, 0.03, 0.07]);
    $excess = $ts->excessRet(0.02);
    
    $rounded = array_map(fn($x) => round($x, 10, PHP_ROUND_HALF_UP), $excess->getData());
    expect($rounded)->toBe([0.03, 0.01, 0.05]);

    // Use values that are less prone to floating-point errors
    $ts = new TSeries([0.1, 0.05, 0.15]);
    $excess = $ts->excessRet(0.05);
   
    $rounded = array_map(fn($x) => round($x, 10, PHP_ROUND_HALF_UP), $excess->getData());
    expect($rounded)->toBe([0.05, 0.0, 0.1]);
});

test('calculates excess returns with TSeries benchmark', function () {
    $ts = new TSeries([0.05, 0.03, 0.07]);
    $benchmark = new TSeries([0.02, 0.01, 0.03]);
    $excess = $ts->excessRet($benchmark);
    
    $rounded = array_map(fn($x) => round($x, 10, PHP_ROUND_HALF_UP), $excess->getData());
    expect($rounded)->toBe([0.03, 0.02, 0.04]);
});

test('calculates Sharpe ratio', function () {
    $returns = array_fill(0, 252, 0.001);
    $ts = new TSeries($returns, frequency: 'D');
    
    expect($ts->sharpeRatio(0.02))->toBeFloat();
});

test('calculates beta', function () {
    $ts = new TSeries([0.05, 0.03, 0.07, 0.02, 0.04]);
    $benchmark = [0.04, 0.02, 0.06, 0.01, 0.03];
    
    expect($ts->beta($benchmark))->toBeFloat()->toBeGreaterThan(0);
});

test('calculates alpha', function () {
    $ts = new TSeries([0.05, 0.03, 0.07, 0.02, 0.04]);
    $benchmark = new TSeries([0.04, 0.02, 0.06, 0.01, 0.03]);
    
    expect($ts->alpha($benchmark, 0.01))->toBeFloat();
});

test('calculates drawdown index', function () {
    $ts = new TSeries([0.1, -0.05, -0.1, 0.05, -0.02]);
    $dd = $ts->drawdownIdx();
    
    expect($dd)->toBeInstanceOf(TSeries::class)
        ->and(max($dd->getData()))->toBeLessThanOrEqual(0);
});

test('calculates maximum drawdown', function () {
    $ts = new TSeries([0.1, -0.05, -0.1, 0.05, -0.02]);
    
    expect($ts->maxDrawdown())->toBeLessThan(0);
});

test('calculates tracking error', function () {
    $ts = new TSeries([0.05, 0.03, 0.07, 0.02, 0.04]);
    $benchmark = [0.04, 0.02, 0.06, 0.01, 0.03];
    
    expect($ts->trackingError($benchmark))->toBeGreaterThan(0);
});

test('calculates information ratio', function () {
    $ts = new TSeries([0.05, 0.03, 0.07, 0.02, 0.04]);
    $benchmark = [0.04, 0.02, 0.06, 0.01, 0.03];
    
    expect($ts->infoRatio($benchmark))->toBeFloat();
});

test('calculates Sortino ratio', function () {
    $ts = new TSeries([0.05, -0.03, 0.07, -0.02, 0.04]);
    
    expect($ts->sortino(0.02))->toBeFloat();
});

test('converts to array', function () {
    $ts = new TSeries($this->sampleReturns, $this->sampleIndex, 'D');
    $array = $ts->toArray();
    
    expect($array)->toHaveKeys(['data', 'index', 'frequency'])
        ->and($array['data'])->toBe($this->sampleReturns)
        ->and($array['index'])->toBe($this->sampleIndex)
        ->and($array['frequency'])->toBe('D');
});