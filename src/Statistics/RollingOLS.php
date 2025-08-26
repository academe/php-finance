<?php

declare(strict_types=1);

namespace Academe\PhpFinance\Statistics;

use InvalidArgumentException;

class RollingOLS
{
    private array $y;
    private array $X;
    private array $results = [];
    
    public function __construct(
        array $y, 
        array $X, 
        private int $window, 
        private bool $hasIntercept = true
    ) {
        if ($window < 2) {
            throw new InvalidArgumentException('Window size must be at least 2');
        }
        
        if (count($y) < $window) {
            throw new InvalidArgumentException('Data length must be greater than or equal to window size');
        }
        
        $this->y = array_values($y);
        $this->X = $X;
        
        $this->fit();
    }
    
    private function fit(): void
    {
        $n = count($this->y);
        
        for ($i = 0; $i <= $n - $this->window; $i++) {
            $yWindow = array_slice($this->y, $i, $this->window);
            $XWindow = array_slice($this->X, $i, $this->window);
            
            try {
                $ols = new OLS($yWindow, $XWindow, $this->hasIntercept);
                
                $this->results[] = [
                    'start_index' => $i,
                    'end_index' => $i + $this->window - 1,
                    'coefficients' => $ols->getCoefficients(),
                    'r_squared' => $ols->getRSquared(),
                    'adjusted_r_squared' => $ols->getAdjustedRSquared(),
                    'standard_errors' => $ols->getStandardErrors(),
                    't_statistics' => $ols->getTStatistics(),
                    'p_values' => $ols->getPValues(),
                ];
            } catch (\Exception $e) {
                $this->results[] = [
                    'start_index' => $i,
                    'end_index' => $i + $this->window - 1,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }
    
    public function getResults(): array
    {
        return $this->results;
    }
    
    public function getCoefficients(): array
    {
        $coefficients = [];
        
        foreach ($this->results as $result) {
            if (isset($result['coefficients'])) {
                $coefficients[] = $result['coefficients'];
            } else {
                $coefficients[] = null;
            }
        }
        
        return $coefficients;
    }
    
    public function getRSquared(): array
    {
        $rSquared = [];
        
        foreach ($this->results as $result) {
            if (isset($result['r_squared'])) {
                $rSquared[] = $result['r_squared'];
            } else {
                $rSquared[] = null;
            }
        }
        
        return $rSquared;
    }
    
    public function getCoefficientSeries(int $coefficientIndex = 0): array
    {
        $series = [];
        
        foreach ($this->results as $result) {
            if (isset($result['coefficients']) && isset($result['coefficients'][$coefficientIndex])) {
                $series[] = $result['coefficients'][$coefficientIndex];
            } else {
                $series[] = null;
            }
        }
        
        return $series;
    }
    
    public function getStandardErrorSeries(int $coefficientIndex = 0): array
    {
        $series = [];
        
        foreach ($this->results as $result) {
            if (isset($result['standard_errors']) && isset($result['standard_errors'][$coefficientIndex])) {
                $series[] = $result['standard_errors'][$coefficientIndex];
            } else {
                $series[] = null;
            }
        }
        
        return $series;
    }
    
    public function getTStatisticSeries(int $coefficientIndex = 0): array
    {
        $series = [];
        
        foreach ($this->results as $result) {
            if (isset($result['t_statistics']) && isset($result['t_statistics'][$coefficientIndex])) {
                $series[] = $result['t_statistics'][$coefficientIndex];
            } else {
                $series[] = null;
            }
        }
        
        return $series;
    }
    
    public function getPValueSeries(int $coefficientIndex = 0): array
    {
        $series = [];
        
        foreach ($this->results as $result) {
            if (isset($result['p_values']) && isset($result['p_values'][$coefficientIndex])) {
                $series[] = $result['p_values'][$coefficientIndex];
            } else {
                $series[] = null;
            }
        }
        
        return $series;
    }
    
    public function getSummary(): array
    {
        return [
            'window_size' => $this->window,
            'n_windows' => count($this->results),
            'has_intercept' => $this->hasIntercept,
            'total_observations' => count($this->y),
            'results' => $this->results,
        ];
    }
}