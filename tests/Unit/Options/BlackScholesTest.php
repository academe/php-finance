<?php

use Academe\PhpFinance\Options\BlackScholes;

beforeEach(function () {
    $this->spotPrice = 100.0;
    $this->strikePrice = 110.0;
    $this->timeToExpiry = 1.0;
    $this->riskFreeRate = 0.05;
    $this->volatility = 0.2;
    $this->dividendYield = 0.02;
});

test('can create BlackScholes instance', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility,
        $this->dividendYield
    );
    
    expect($bs)->toBeInstanceOf(BlackScholes::class);
});

test('throws exception for negative spot price', function () {
    new BlackScholes(
        -100,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
})->throws(InvalidArgumentException::class);

test('throws exception for negative strike price', function () {
    new BlackScholes(
        $this->spotPrice,
        -110,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
})->throws(InvalidArgumentException::class);

test('throws exception for negative time to expiry', function () {
    new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        -1,
        $this->riskFreeRate,
        $this->volatility
    );
})->throws(InvalidArgumentException::class);

test('throws exception for negative volatility', function () {
    new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        -0.2
    );
})->throws(InvalidArgumentException::class);

test('calculates call option price', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    $callPrice = $bs->callPrice();
    
    expect($callPrice)->toBeFloat()->toBeGreaterThan(0);
});

test('calculates put option price', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    $putPrice = $bs->putPrice();
    
    expect($putPrice)->toBeFloat()->toBeGreaterThan(0);
});

test('calculates option price at expiry', function () {
    $bs = new BlackScholes(
        120,
        100,
        0,
        $this->riskFreeRate,
        $this->volatility
    );
    
    expect($bs->callPrice())->toBe(20.0)
        ->and($bs->putPrice())->toBe(0.0);
});

test('calculates call delta', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    $delta = $bs->callDelta();
    
    expect($delta)->toBeFloat()
        ->toBeGreaterThanOrEqual(0)
        ->toBeLessThanOrEqual(1);
});

test('calculates put delta', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    $delta = $bs->putDelta();
    
    expect($delta)->toBeFloat()
        ->toBeGreaterThanOrEqual(-1)
        ->toBeLessThanOrEqual(0);
});

test('calculates gamma', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    expect($bs->gamma())->toBeFloat()->toBeGreaterThanOrEqual(0);
});

test('calculates vega', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    expect($bs->vega())->toBeFloat()->toBeGreaterThanOrEqual(0);
});

test('calculates call theta', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    expect($bs->callTheta())->toBeFloat();
});

test('calculates put theta', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    expect($bs->putTheta())->toBeFloat();
});

test('calculates call rho', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    expect($bs->callRho())->toBeFloat();
});

test('calculates put rho', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    expect($bs->putRho())->toBeFloat();
});

test('calculates implied volatility for call option', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        0.3
    );
    
    $marketPrice = $bs->callPrice();
    
    $bs2 = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        0.1
    );
    
    $impliedVol = $bs2->impliedVolatility($marketPrice, 'call');
    
    expect($impliedVol)->toBeFloat()
        ->toEqualWithDelta(0.3, 0.01);
});

test('throws exception for invalid option type in implied volatility', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    $bs->impliedVolatility(10, 'invalid');
})->throws(InvalidArgumentException::class);

test('returns all Greeks for call option', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility
    );
    
    $greeks = $bs->getGreeks('call');
    
    expect($greeks)->toBeArray()
        ->toHaveKeys(['delta', 'gamma', 'vega', 'theta', 'rho'])
        ->and($greeks['delta'])->toBeFloat()
        ->and($greeks['gamma'])->toBeFloat()
        ->and($greeks['vega'])->toBeFloat()
        ->and($greeks['theta'])->toBeFloat()
        ->and($greeks['rho'])->toBeFloat();
});

test('returns complete summary', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility,
        $this->dividendYield
    );
    
    $summary = $bs->getSummary('call');
    
    expect($summary)->toBeArray()
        ->toHaveKeys([
            'type', 'spot_price', 'strike_price', 'time_to_expiry',
            'risk_free_rate', 'volatility', 'dividend_yield', 'price', 'greeks'
        ])
        ->and($summary['type'])->toBe('call')
        ->and($summary['spot_price'])->toBe($this->spotPrice)
        ->and($summary['strike_price'])->toBe($this->strikePrice)
        ->and($summary['greeks'])->toBeArray();
});

test('put-call parity holds', function () {
    $bs = new BlackScholes(
        $this->spotPrice,
        $this->strikePrice,
        $this->timeToExpiry,
        $this->riskFreeRate,
        $this->volatility,
        0
    );
    
    $callPrice = $bs->callPrice();
    $putPrice = $bs->putPrice();
    
    $leftSide = $callPrice - $putPrice;
    $rightSide = $this->spotPrice - $this->strikePrice * exp(-$this->riskFreeRate * $this->timeToExpiry);
    
    expect($leftSide)->toEqualWithDelta($rightSide, 0.001);
});