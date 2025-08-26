<?php

declare(strict_types=1);

namespace Academe\PhpFinance\General;

use InvalidArgumentException;
use MathPHP\Probability\Distribution\Continuous\Normal;

class Utils
{
    public static function activeShare(array $portfolio, array $benchmark): float
    {
        if (empty($portfolio) || empty($benchmark)) {
            throw new InvalidArgumentException('Portfolio and benchmark arrays cannot be empty');
        }
        
        $portfolioSum = array_sum($portfolio);
        $benchmarkSum = array_sum($benchmark);
        
        if (abs($portfolioSum - 1.0) > 0.01 || abs($benchmarkSum - 1.0) > 0.01) {
            throw new InvalidArgumentException('Portfolio and benchmark weights must sum to 1');
        }
        
        $allAssets = array_unique(array_merge(array_keys($portfolio), array_keys($benchmark)));
        $activeShare = 0.0;
        
        foreach ($allAssets as $asset) {
            $portfolioWeight = $portfolio[$asset] ?? 0.0;
            $benchmarkWeight = $benchmark[$asset] ?? 0.0;
            $activeShare += abs($portfolioWeight - $benchmarkWeight);
        }
        
        return $activeShare / 2.0;
    }
    
    public static function returnsDistribution(
        float $mean,
        float $std,
        float $skew = 0.0,
        float $kurt = 3.0,
        int $n = 1000
    ): array {
        if ($std <= 0) {
            throw new InvalidArgumentException('Standard deviation must be positive');
        }
        
        if ($n <= 0) {
            throw new InvalidArgumentException('Number of samples must be positive');
        }
        
        $normal = new Normal($mean, $std);
        $samples = [];
        
        for ($i = 0; $i < $n; $i++) {
            $samples[] = $normal->rand();
        }
        
        if ($skew != 0.0 || $kurt != 3.0) {
            $samples = self::adjustSkewKurtosis($samples, $mean, $std, $skew, $kurt);
        }
        
        return $samples;
    }
    
    private static function adjustSkewKurtosis(
        array $samples,
        float $targetMean,
        float $targetStd,
        float $targetSkew,
        float $targetKurt
    ): array {
        $z = self::standardize($samples);
        
        $a = $targetSkew / 6.0;
        $b = ($targetKurt - 3.0) / 24.0;
        
        $adjusted = [];
        foreach ($z as $zi) {
            $yi = $zi + $a * (pow($zi, 2) - 1) + $b * (pow($zi, 3) - 3 * $zi);
            $adjusted[] = $yi;
        }
        
        $adjusted = self::standardize($adjusted);
        
        $result = [];
        foreach ($adjusted as $val) {
            $result[] = $val * $targetStd + $targetMean;
        }
        
        return $result;
    }
    
    private static function standardize(array $data): array
    {
        $mean = array_sum($data) / count($data);
        $variance = 0.0;
        
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $std = sqrt($variance / count($data));
        
        if ($std == 0) {
            return array_fill(0, count($data), 0.0);
        }
        
        $standardized = [];
        foreach ($data as $value) {
            $standardized[] = ($value - $mean) / $std;
        }
        
        return $standardized;
    }
    
    public static function kellyFormula(
        float $winProbability,
        float $winAmount,
        float $lossAmount
    ): float {
        if ($winProbability < 0 || $winProbability > 1) {
            throw new InvalidArgumentException('Win probability must be between 0 and 1');
        }
        
        if ($winAmount <= 0 || $lossAmount <= 0) {
            throw new InvalidArgumentException('Win and loss amounts must be positive');
        }
        
        $b = $winAmount / $lossAmount;
        $p = $winProbability;
        $q = 1 - $p;
        
        if ($b == 0) {
            return 0.0;
        }
        
        return ($p * $b - $q) / $b;
    }
    
    public static function compoundReturn(array $returns): float
    {
        if (empty($returns)) {
            throw new InvalidArgumentException('Returns array cannot be empty');
        }
        
        $product = 1.0;
        foreach ($returns as $return) {
            $product *= (1 + $return);
        }
        
        return $product - 1.0;
    }
    
    public static function geometricMean(array $returns): float
    {
        if (empty($returns)) {
            throw new InvalidArgumentException('Returns array cannot be empty');
        }
        
        $product = 1.0;
        foreach ($returns as $return) {
            if ($return <= -1) {
                throw new InvalidArgumentException('Returns must be greater than -100%');
            }
            $product *= (1 + $return);
        }
        
        return pow($product, 1.0 / count($returns)) - 1.0;
    }
    
    public static function valueAtRisk(array $returns, float $confidence = 0.95): float
    {
        if (empty($returns)) {
            throw new InvalidArgumentException('Returns array cannot be empty');
        }
        
        if ($confidence <= 0 || $confidence >= 1) {
            throw new InvalidArgumentException('Confidence level must be between 0 and 1');
        }
        
        sort($returns);
        $index = (int)floor((1 - $confidence) * count($returns));
        
        if ($index >= count($returns)) {
            $index = count($returns) - 1;
        }
        
        return $returns[$index];
    }
    
    public static function conditionalValueAtRisk(array $returns, float $confidence = 0.95): float
    {
        if (empty($returns)) {
            throw new InvalidArgumentException('Returns array cannot be empty');
        }
        
        if ($confidence <= 0 || $confidence >= 1) {
            throw new InvalidArgumentException('Confidence level must be between 0 and 1');
        }
        
        $var = self::valueAtRisk($returns, $confidence);
        
        $tailReturns = array_filter($returns, fn($r) => $r <= $var);
        
        if (empty($tailReturns)) {
            return $var;
        }
        
        return array_sum($tailReturns) / count($tailReturns);
    }
    
    public static function maxDrawdown(array $prices): float
    {
        if (empty($prices)) {
            throw new InvalidArgumentException('Prices array cannot be empty');
        }
        
        $maxDrawdown = 0.0;
        $peak = $prices[0];
        
        foreach ($prices as $price) {
            if ($price > $peak) {
                $peak = $price;
            }
            
            $drawdown = ($price - $peak) / $peak;
            
            if ($drawdown < $maxDrawdown) {
                $maxDrawdown = $drawdown;
            }
        }
        
        return $maxDrawdown;
    }
    
    public static function calmarRatio(array $returns, int $periods = 252): float
    {
        if (empty($returns)) {
            throw new InvalidArgumentException('Returns array cannot be empty');
        }
        
        $annualReturn = self::annualizedReturn($returns, $periods);
        
        $cumReturns = [];
        $cumReturn = 1.0;
        foreach ($returns as $return) {
            $cumReturn *= (1 + $return);
            $cumReturns[] = $cumReturn;
        }
        
        $maxDD = abs(self::maxDrawdown($cumReturns));
        
        if ($maxDD == 0) {
            return 0.0;
        }
        
        return $annualReturn / $maxDD;
    }
    
    public static function annualizedReturn(array $returns, int $periods = 252): float
    {
        if (empty($returns)) {
            throw new InvalidArgumentException('Returns array cannot be empty');
        }
        
        $totalReturn = self::compoundReturn($returns);
        $years = count($returns) / $periods;
        
        if ($years <= 0) {
            return 0.0;
        }
        
        return pow(1 + $totalReturn, 1 / $years) - 1;
    }
    
    public static function annualizedVolatility(array $returns, int $periods = 252): float
    {
        if (empty($returns)) {
            throw new InvalidArgumentException('Returns array cannot be empty');
        }
        
        $mean = array_sum($returns) / count($returns);
        $variance = 0.0;
        
        foreach ($returns as $return) {
            $variance += pow($return - $mean, 2);
        }
        
        $std = sqrt($variance / (count($returns) - 1));
        
        return $std * sqrt($periods);
    }
    
    public static function correlation(array $x, array $y): float
    {
        if (empty($x) || empty($y)) {
            throw new InvalidArgumentException('Input arrays cannot be empty');
        }
        
        if (count($x) !== count($y)) {
            throw new InvalidArgumentException('Arrays must have the same length');
        }
        
        $n = count($x);
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        
        $numerator = 0.0;
        $denomX = 0.0;
        $denomY = 0.0;
        
        for ($i = 0; $i < $n; $i++) {
            $diffX = $x[$i] - $meanX;
            $diffY = $y[$i] - $meanY;
            
            $numerator += $diffX * $diffY;
            $denomX += pow($diffX, 2);
            $denomY += pow($diffY, 2);
        }
        
        if ($denomX == 0 || $denomY == 0) {
            return 0.0;
        }
        
        return $numerator / sqrt($denomX * $denomY);
    }
}