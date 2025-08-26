<?php

use Academe\PhpFinance\Statistics\OLS;
use Academe\PhpFinance\Statistics\RollingOLS;
use MathPHP\Exception\MatrixException;

beforeEach(function () {
    $this->y = [2.5, 5.0, 7.5, 10.0, 12.5];
    $this->X = [[1], [2], [3], [4], [5]];
    
    $this->yMulti = [5, 10, 15, 20, 25];
    $this->XMulti = [
        [1, 2],
        [2, 4],
        [3, 6],
        [4, 8],
        [5, 10],
    ];

    $this->XMultiNonLinearlyDependent = [
        [1, 10],
        [2, 15],
        [3, 12],
        [4, 18],
        [5, 14],
    ];
});

test('can create OLS instance with single predictor', function () {
    $ols = new OLS($this->y, $this->X);
    
    expect($ols)->toBeInstanceOf(OLS::class);
});

// Fails if x2 is linearly dependent on x1 as that makes X'X singular (non-invertible)
test('can create OLS instance with multiple predictors', function () {
    $ols = new OLS($this->yMulti, $this->XMultiNonLinearlyDependent);
    
    expect($ols)->toBeInstanceOf(OLS::class);
});

test('throws exception for empty data', function () {
    new OLS([], []);
})->throws(InvalidArgumentException::class);

test('throws exception for mismatched dimensions', function () {
    new OLS([1, 2, 3], [[1], [2]]);
})->throws(InvalidArgumentException::class);

test('throws exception when observations less than parameters', function () {
    new OLS([1, 2], [[1, 2, 3], [4, 5, 6]]);
})->throws(InvalidArgumentException::class);

test('calculates coefficients correctly for simple linear regression', function () {
    $ols = new OLS($this->y, $this->X);
    $coefficients = $ols->getCoefficients();
    
    expect($coefficients)->toBeArray()
        ->toHaveCount(2)
        ->and($coefficients[0])->toEqualWithDelta(0, 0.01)
        ->and($coefficients[1])->toEqualWithDelta(2.5, 0.01);
});

test('calculates R-squared correctly', function () {
    $ols = new OLS($this->y, $this->X);
    
    expect($ols->getRSquared())->toEqualWithDelta(1.0, 0.01);
});

test('calculates adjusted R-squared', function () {
    $ols = new OLS($this->y, $this->X);
    
    expect($ols->getAdjustedRSquared())->toBeFloat()
        ->toBeLessThanOrEqual($ols->getRSquared());
});

test('calculates residuals', function () {
    $ols = new OLS($this->y, $this->X);
    $residuals = $ols->getResiduals();
    
    expect($residuals)->toBeArray()
        ->toHaveCount(5);
    
    foreach ($residuals as $residual) {
        expect(abs($residual))->toBeLessThan(0.01);
    }
});

test('calculates fitted values', function () {
    $ols = new OLS($this->y, $this->X);
    $fitted = $ols->getFittedValues();
    
    expect($fitted)->toBeArray()
        ->toHaveCount(5);
    
    for ($i = 0; $i < 5; $i++) {
        expect($fitted[$i])->toEqualWithDelta($this->y[$i], 0.01);
    }
});

test('calculates standard errors', function () {
    $ols = new OLS($this->y, $this->X);
    $standardErrors = $ols->getStandardErrors();
    
    expect($standardErrors)->toBeArray()
        ->toHaveCount(2);
    
    foreach ($standardErrors as $se) {
        expect($se)->toBeFloat()->toBeGreaterThanOrEqual(0);
    }
});

test('calculates t-statistics', function () {
    $ols = new OLS($this->y, $this->X);
    $tStats = $ols->getTStatistics();
    
    expect($tStats)->toBeArray()
        ->toHaveCount(2);
});

test('calculates p-values', function () {
    $ols = new OLS($this->y, $this->X);
    $pValues = $ols->getPValues();
    
    expect($pValues)->toBeArray()
        ->toHaveCount(2);
    
    foreach ($pValues as $pValue) {
        expect($pValue)->toBeFloat()
            ->toBeGreaterThanOrEqual(0)
            ->toBeLessThanOrEqual(1);
    }
});

test('makes predictions correctly', function () {
    $ols = new OLS($this->y, $this->X);
    
    $prediction = $ols->predict([[6]]);
    expect($prediction[0])->toEqualWithDelta(15.0, 0.01);
    
    $predictions = $ols->predict([[6], [7], [8]]);
    expect($predictions)->toHaveCount(3)
        ->and($predictions[0])->toEqualWithDelta(15.0, 0.01)
        ->and($predictions[1])->toEqualWithDelta(17.5, 0.01)
        ->and($predictions[2])->toEqualWithDelta(20.0, 0.01);
});

test('throws exception for wrong prediction dimensions', function () {
    $ols = new OLS($this->yMulti, $this->XMulti);
    
    $ols->predict([[1]]);
})->throws(MatrixException::class);

test('returns comprehensive summary', function () {
    $ols = new OLS($this->y, $this->X);
    $summary = $ols->getSummary();
    
    expect($summary)->toBeArray()
        ->toHaveKeys([
            'n_observations', 'n_parameters', 'degrees_of_freedom',
            'r_squared', 'adjusted_r_squared', 'coefficients'
        ])
        ->and($summary['n_observations'])->toBe(5)
        ->and($summary['n_parameters'])->toBe(2)
        ->and($summary['degrees_of_freedom'])->toBe(3)
        ->and($summary['coefficients'])->toBeArray()
        ->toHaveKeys(['intercept', 'x1']);
});

test('performs F-test', function () {
    $ols = new OLS($this->y, $this->X);
    $fTest = $ols->fTest();
    
    expect($fTest)->toBeArray()
        ->toHaveKeys(['f_statistic', 'p_value', 'df_regression', 'df_residual']);
});

test('handles OLS without intercept', function () {
    $ols = new OLS($this->y, $this->X, false);
    $coefficients = $ols->getCoefficients();
    
    expect($coefficients)->toHaveCount(1);
});

test('rolling OLS works correctly', function () {
    $y = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    $X = [[1], [2], [3], [4], [5], [6], [7], [8], [9], [10]];
    
    $rolling = new RollingOLS($y, $X, 3);
    
    expect($rolling)->toBeInstanceOf(RollingOLS::class);
    
    $results = $rolling->getResults();
    expect($results)->toHaveCount(8);
});

test('rolling OLS throws exception for window size less than 2', function () {
    new RollingOLS($this->y, $this->X, 1);
})->throws(InvalidArgumentException::class);

test('rolling OLS throws exception when data length less than window', function () {
    new RollingOLS([1, 2], [[1], [2]], 3);
})->throws(InvalidArgumentException::class);

test('rolling OLS extracts coefficient series', function () {
    $y = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    $X = [[1], [2], [3], [4], [5], [6], [7], [8], [9], [10]];
    
    $rolling = new RollingOLS($y, $X, 3);
    
    $interceptSeries = $rolling->getCoefficientSeries(0);
    $slopeSeries = $rolling->getCoefficientSeries(1);
    
    expect($interceptSeries)->toHaveCount(8)
        ->and($slopeSeries)->toHaveCount(8);
});

test('rolling OLS gets R-squared series', function () {
    $y = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    $X = [[1], [2], [3], [4], [5], [6], [7], [8], [9], [10]];
    
    $rolling = new RollingOLS($y, $X, 3);
    
    $rSquaredSeries = $rolling->getRSquared();
    
    expect($rSquaredSeries)->toHaveCount(8);
    
    foreach ($rSquaredSeries as $rSquared) {
        if ($rSquared !== null) {
            expect($rSquared)->toBeFloat()
                ->toBeGreaterThanOrEqual(0)
                ->toBeLessThanOrEqual(1);
        }
    }
});