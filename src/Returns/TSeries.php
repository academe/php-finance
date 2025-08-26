<?php

declare(strict_types=1);

namespace Academe\PhpFinance\Returns;

use InvalidArgumentException;
use DateTime;
use DateTimeInterface;

class TSeries
{
    private array $data;
    private array $index;
    
    public function __construct(
        array $data, 
        array $index = [], 
        private ?string $frequency = null
    ) {
        if (empty($data)) {
            throw new InvalidArgumentException('Data array cannot be empty');
        }
        
        if (!empty($index) && count($data) !== count($index)) {
            throw new InvalidArgumentException('Data and index arrays must have the same length');
        }
        
        $this->data = array_values($data);
        $this->index = empty($index) ? range(0, count($data) - 1) : array_values($index);
        
        $this->validateMonotonicIndex();
    }
    
    private function validateMonotonicIndex(): void
    {
        $previous = null;
        foreach ($this->index as $value) {
            if ($previous !== null && $value <= $previous) {
                throw new InvalidArgumentException('Index must be strictly monotonically increasing');
            }
            $previous = $value;
        }
    }
    
    public function getData(): array
    {
        return $this->data;
    }
    
    public function getIndex(): array
    {
        return $this->index;
    }
    
    public function count(): int
    {
        return count($this->data);
    }
    
    public function sum(): float
    {
        return array_sum($this->data);
    }
    
    public function mean(): float
    {
        return $this->sum() / $this->count();
    }
    
    public function std(bool $sample = true): float
    {
        $mean = $this->mean();
        $variance = 0.0;
        
        foreach ($this->data as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $denominator = $sample ? ($this->count() - 1) : $this->count();
        
        if ($denominator <= 0) {
            return 0.0;
        }
        
        return sqrt($variance / $denominator);
    }
    
    public function variance(bool $sample = true): float
    {
        return pow($this->std($sample), 2);
    }
    
    public function min(): float
    {
        return min($this->data);
    }
    
    public function max(): float
    {
        return max($this->data);
    }
    
    public function cumsum(): TSeries
    {
        $cumulative = [];
        $sum = 0;
        
        foreach ($this->data as $value) {
            $sum += $value;
            $cumulative[] = $sum;
        }
        
        return new TSeries($cumulative, $this->index, $this->frequency);
    }
    
    public function cumprod(): TSeries
    {
        $cumulative = [];
        $product = 1;
        
        foreach ($this->data as $value) {
            $product *= $value;
            $cumulative[] = $product;
        }
        
        return new TSeries($cumulative, $this->index, $this->frequency);
    }
    
    public function retRels(): TSeries
    {
        $rels = array_map(fn($x) => 1 + $x, $this->data);
        return new TSeries($rels, $this->index, $this->frequency);
    }
    
    public function retIdx(float $base = 1.0): TSeries
    {
        $rels = $this->retRels();
        $cumProd = $rels->cumprod();
        $idx = array_map(fn($x) => $x * $base, $cumProd->getData());
        
        return new TSeries($idx, $this->index, $this->frequency);
    }
    
    public function anlzdRet(): float
    {
        $periods = $this->inferPeriods();
        $totalReturn = $this->retIdx()->getData()[count($this->data) - 1] / 1.0;
        
        if ($periods <= 0) {
            return 0.0;
        }
        
        return pow($totalReturn, 1.0 / $periods) - 1.0;
    }
    
    public function cumRet(): float
    {
        $idx = $this->retIdx()->getData();
        return end($idx) - 1.0;
    }
    
    public function excessRet($benchmark): TSeries
    {
        if ($benchmark instanceof TSeries) {
            $benchData = $benchmark->getData();
        } elseif (is_array($benchmark)) {
            $benchData = $benchmark;
        } elseif (is_numeric($benchmark)) {
            $benchData = array_fill(0, $this->count(), $benchmark);
        } else {
            throw new InvalidArgumentException('Benchmark must be TSeries, array, or numeric');
        }
        
        if (count($benchData) !== $this->count()) {
            throw new InvalidArgumentException('Benchmark and series must have same length');
        }
        
        $excess = [];
        for ($i = 0; $i < $this->count(); $i++) {
            $excess[] = $this->data[$i] - $benchData[$i];
        }
        
        return new TSeries($excess, $this->index, $this->frequency);
    }
    
    public function sharpeRatio(?float $riskFreeRate = null, bool $anlzd = true): float
    {
        $riskFreeRate = $riskFreeRate ?? 0.0;
        
        if ($anlzd) {
            $periods = $this->inferPeriods();
            $adjustedRfr = $riskFreeRate / $periods;
            $excess = $this->excessRet($adjustedRfr);
            $meanExcess = $excess->mean();
            $stdExcess = $excess->std();
            
            if ($stdExcess == 0) {
                return 0.0;
            }
            
            return ($meanExcess * $periods) / ($stdExcess * sqrt($periods));
        } else {
            $excess = $this->excessRet($riskFreeRate);
            $meanExcess = $excess->mean();
            $stdExcess = $excess->std();
            
            if ($stdExcess == 0) {
                return 0.0;
            }
            
            return $meanExcess / $stdExcess;
        }
    }
    
    public function beta($benchmark): float
    {
        if ($benchmark instanceof TSeries) {
            $benchData = $benchmark->getData();
        } elseif (is_array($benchmark)) {
            $benchData = $benchmark;
        } else {
            throw new InvalidArgumentException('Benchmark must be TSeries or array');
        }
        
        if (count($benchData) !== $this->count()) {
            throw new InvalidArgumentException('Benchmark and series must have same length');
        }
        
        $meanSeries = $this->mean();
        $meanBench = array_sum($benchData) / count($benchData);
        
        $covariance = 0.0;
        $benchVariance = 0.0;
        
        for ($i = 0; $i < $this->count(); $i++) {
            $covariance += ($this->data[$i] - $meanSeries) * ($benchData[$i] - $meanBench);
            $benchVariance += pow($benchData[$i] - $meanBench, 2);
        }
        
        if ($benchVariance == 0) {
            return 0.0;
        }
        
        return $covariance / $benchVariance;
    }
    
    public function alpha($benchmark, ?float $riskFreeRate = null): float
    {
        $riskFreeRate = $riskFreeRate ?? 0.0;
        $beta = $this->beta($benchmark);
        
        if ($benchmark instanceof TSeries) {
            $benchReturn = $benchmark->anlzdRet();
        } else {
            $benchSeries = new TSeries($benchmark);
            $benchReturn = $benchSeries->anlzdRet();
        }
        
        $seriesReturn = $this->anlzdRet();
        
        return $seriesReturn - $riskFreeRate - $beta * ($benchReturn - $riskFreeRate);
    }
    
    public function drawdownIdx(): TSeries
    {
        $retIdx = $this->retIdx()->getData();
        $runningMax = [];
        $maxSoFar = $retIdx[0];
        
        foreach ($retIdx as $value) {
            $maxSoFar = max($maxSoFar, $value);
            $runningMax[] = $maxSoFar;
        }
        
        $drawdowns = [];
        for ($i = 0; $i < count($retIdx); $i++) {
            if ($runningMax[$i] == 0) {
                $drawdowns[] = 0.0;
            } else {
                $drawdowns[] = ($retIdx[$i] - $runningMax[$i]) / $runningMax[$i];
            }
        }
        
        return new TSeries($drawdowns, $this->index, $this->frequency);
    }
    
    public function maxDrawdown(): float
    {
        $drawdowns = $this->drawdownIdx()->getData();
        return min($drawdowns);
    }
    
    public function trackingError($benchmark): float
    {
        $excess = $this->excessRet($benchmark);
        return $excess->std() * sqrt($this->inferPeriods());
    }
    
    public function infoRatio($benchmark): float
    {
        $excess = $this->excessRet($benchmark);
        $te = $this->trackingError($benchmark);
        
        if ($te == 0) {
            return 0.0;
        }
        
        return $excess->mean() * $this->inferPeriods() / $te;
    }
    
    public function sortino($target = 0.0, bool $anlzd = true): float
    {
        $excess = array_map(fn($x) => $x - $target, $this->data);
        $downsideReturns = array_filter($excess, fn($x) => $x < 0);
        
        if (empty($downsideReturns)) {
            return 0.0;
        }
        
        $downsideVariance = array_sum(array_map(fn($x) => pow($x, 2), $downsideReturns)) / count($this->data);
        $downsideDeviation = sqrt($downsideVariance);
        
        if ($downsideDeviation == 0) {
            return 0.0;
        }
        
        if ($anlzd) {
            $periods = $this->inferPeriods();
            $meanExcess = array_sum($excess) / count($excess);
            return ($meanExcess * $periods) / ($downsideDeviation * sqrt($periods));
        } else {
            $meanExcess = array_sum($excess) / count($excess);
            return $meanExcess / $downsideDeviation;
        }
    }
    
    private function inferPeriods(): float
    {
        if ($this->frequency !== null) {
            return $this->getPeriodsFromFrequency($this->frequency);
        }
        
        if ($this->count() < 2) {
            return 1.0;
        }
        
        return (float)$this->count() / 1.0;
    }
    
    private function getPeriodsFromFrequency(string $frequency): float
    {
        $frequencyMap = [
            'D' => 252.0,
            'W' => 52.0,
            'M' => 12.0,
            'Q' => 4.0,
            'Y' => 1.0,
            'daily' => 252.0,
            'weekly' => 52.0,
            'monthly' => 12.0,
            'quarterly' => 4.0,
            'yearly' => 1.0,
            'annual' => 1.0,
        ];
        
        return $frequencyMap[strtolower($frequency)] ?? 252.0;
    }
    
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'index' => $this->index,
            'frequency' => $this->frequency,
        ];
    }
}