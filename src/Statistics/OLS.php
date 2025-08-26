<?php

declare(strict_types=1);

namespace Academe\PhpFinance\Statistics;

use InvalidArgumentException;
use MathPHP\LinearAlgebra\Matrix;
use MathPHP\LinearAlgebra\MatrixFactory;
use MathPHP\Probability\Distribution\Continuous\StudentT;
use MathPHP\Statistics\Distribution\Continuous\F;

class OLS
{
    private array $y;
    private array $X;
    private ?array $coefficients = null;
    private ?array $residuals = null;
    private ?array $fittedValues = null;
    private ?float $rSquared = null;
    private ?float $adjustedRSquared = null;
    private ?array $standardErrors = null;
    private ?array $tStatistics = null;
    private ?array $pValues = null;
    private int $n;
    private int $k;
    
    public function __construct(
        array $y, 
        array $X, 
        private bool $hasIntercept = true
    ) {
        $this->validateInput($y, $X);
        
        $this->y = array_values($y);
        
        if ($this->hasIntercept) {
            $this->X = $this->addIntercept($X);
        } else {
            $this->X = $this->normalizeX($X);
        }
        
        $this->n = count($y);
        $this->k = count($this->X[0]);
        
        if ($this->n <= $this->k) {
            throw new InvalidArgumentException('Number of observations must be greater than number of parameters');
        }
        
        $this->fit();
    }
    
    private function validateInput(array $y, array $X): void
    {
        if (empty($y) || empty($X)) {
            throw new InvalidArgumentException('Input arrays cannot be empty');
        }
        
        $n = count($y);
        if (count($X) !== $n) {
            throw new InvalidArgumentException('X and y must have the same number of observations');
        }
        
        $firstRowLength = is_array($X[0]) ? count($X[0]) : 1;
        foreach ($X as $row) {
            if (!is_array($row) && !is_numeric($row)) {
                throw new InvalidArgumentException('X must be a 2D array or 1D array of numbers');
            }
            if (is_array($row) && count($row) !== $firstRowLength) {
                throw new InvalidArgumentException('All rows in X must have the same number of columns');
            }
        }
    }
    
    private function normalizeX(array $X): array
    {
        $normalized = [];
        
        foreach ($X as $row) {
            if (is_array($row)) {
                $normalized[] = array_values($row);
            } else {
                $normalized[] = [$row];
            }
        }
        
        return $normalized;
    }
    
    private function addIntercept(array $X): array
    {
        $withIntercept = [];
        
        foreach ($X as $row) {
            if (is_array($row)) {
                $withIntercept[] = array_merge([1.0], array_values($row));
            } else {
                $withIntercept[] = [1.0, $row];
            }
        }
        
        return $withIntercept;
    }
    
    private function fit(): void
    {
        $XMatrix = MatrixFactory::create($this->X);
        $yVector = MatrixFactory::createFromColumnVector($this->y);
        
        $XtX = $XMatrix->transpose()->multiply($XMatrix);
        $XtXInverse = $XtX->inverse();
        $Xty = $XMatrix->transpose()->multiply($yVector);
        
        $betaMatrix = $XtXInverse->multiply($Xty);
        $this->coefficients = $betaMatrix->getColumn(0);
        
        $this->calculateFittedValues();
        $this->calculateResiduals();
        $this->calculateRSquared();
        $this->calculateStandardErrors($XtXInverse);
        $this->calculateTStatistics();
        $this->calculatePValues();
    }
    
    private function calculateFittedValues(): void
    {
        $this->fittedValues = [];
        
        foreach ($this->X as $row) {
            $fitted = 0.0;
            for ($i = 0; $i < count($row); $i++) {
                $fitted += $row[$i] * $this->coefficients[$i];
            }
            $this->fittedValues[] = $fitted;
        }
    }
    
    private function calculateResiduals(): void
    {
        $this->residuals = [];
        
        for ($i = 0; $i < $this->n; $i++) {
            $this->residuals[] = $this->y[$i] - $this->fittedValues[$i];
        }
    }
    
    private function calculateRSquared(): void
    {
        $yMean = array_sum($this->y) / $this->n;
        
        $ssTot = 0.0;
        $ssRes = 0.0;
        
        for ($i = 0; $i < $this->n; $i++) {
            $ssTot += pow($this->y[$i] - $yMean, 2);
            $ssRes += pow($this->residuals[$i], 2);
        }
        
        $this->rSquared = 1 - ($ssRes / $ssTot);
        
        $this->adjustedRSquared = 1 - ((1 - $this->rSquared) * ($this->n - 1) / ($this->n - $this->k));
    }
    
    private function calculateStandardErrors(Matrix $XtXInverse): void
    {
        $ssRes = array_sum(array_map(fn($r) => pow($r, 2), $this->residuals));
        $sigma2 = $ssRes / ($this->n - $this->k);
        
        $this->standardErrors = [];
        $covarianceMatrix = $XtXInverse->scalarMultiply($sigma2);
        
        for ($i = 0; $i < $this->k; $i++) {
            $this->standardErrors[] = sqrt($covarianceMatrix->get($i, $i));
        }
    }
    
    private function calculateTStatistics(): void
    {
        $this->tStatistics = [];
        
        for ($i = 0; $i < $this->k; $i++) {
            if ($this->standardErrors[$i] != 0) {
                $this->tStatistics[] = $this->coefficients[$i] / $this->standardErrors[$i];
            } else {
                $this->tStatistics[] = 0.0;
            }
        }
    }
    
    private function calculatePValues(): void
    {
        $this->pValues = [];
        $df = $this->n - $this->k;
        $tDist = new StudentT($df);
        
        foreach ($this->tStatistics as $tStat) {
            $this->pValues[] = 2 * (1 - $tDist->cdf(abs($tStat)));
        }
    }
    
    public function getCoefficients(): array
    {
        return $this->coefficients;
    }
    
    public function getResiduals(): array
    {
        return $this->residuals;
    }
    
    public function getFittedValues(): array
    {
        return $this->fittedValues;
    }
    
    public function getRSquared(): float
    {
        return $this->rSquared;
    }
    
    public function getAdjustedRSquared(): float
    {
        return $this->adjustedRSquared;
    }
    
    public function getStandardErrors(): array
    {
        return $this->standardErrors;
    }
    
    public function getTStatistics(): array
    {
        return $this->tStatistics;
    }
    
    public function getPValues(): array
    {
        return $this->pValues;
    }
    
    public function predict(array $X): array
    {
        if (!is_array($X[0])) {
            $X = [$X];
        }
        
        $predictions = [];
        
        foreach ($X as $row) {
            if ($this->hasIntercept) {
                $row = array_merge([1.0], $row);
            }
            
            if (count($row) !== $this->k) {
                throw new InvalidArgumentException('Prediction input must have the same number of features as training data');
            }
            
            $prediction = 0.0;
            for ($i = 0; $i < $this->k; $i++) {
                $prediction += $row[$i] * $this->coefficients[$i];
            }
            $predictions[] = $prediction;
        }
        
        return count($predictions) === 1 ? [$predictions[0]] : $predictions;
    }
    
    public function getSummary(): array
    {
        $summary = [
            'n_observations' => $this->n,
            'n_parameters' => $this->k,
            'degrees_of_freedom' => $this->n - $this->k,
            'r_squared' => $this->rSquared,
            'adjusted_r_squared' => $this->adjustedRSquared,
            'coefficients' => [],
        ];
        
        $labels = $this->getCoefficientLabels();
        
        for ($i = 0; $i < $this->k; $i++) {
            $summary['coefficients'][$labels[$i]] = [
                'estimate' => $this->coefficients[$i],
                'std_error' => $this->standardErrors[$i],
                't_statistic' => $this->tStatistics[$i],
                'p_value' => $this->pValues[$i],
            ];
        }
        
        return $summary;
    }
    
    private function getCoefficientLabels(): array
    {
        $labels = [];
        
        if ($this->hasIntercept) {
            $labels[] = 'intercept';
            for ($i = 1; $i < $this->k; $i++) {
                $labels[] = 'x' . $i;
            }
        } else {
            for ($i = 0; $i < $this->k; $i++) {
                $labels[] = 'x' . ($i + 1);
            }
        }
        
        return $labels;
    }
    
    public function fTest(): array
    {
        $yMean = array_sum($this->y) / $this->n;
        
        $ssReg = 0.0;
        $ssRes = 0.0;
        
        for ($i = 0; $i < $this->n; $i++) {
            $ssReg += pow($this->fittedValues[$i] - $yMean, 2);
            $ssRes += pow($this->residuals[$i], 2);
        }
        
        $dfReg = $this->k - ($this->hasIntercept ? 1 : 0);
        $dfRes = $this->n - $this->k;
        
        if ($dfReg <= 0 || $dfRes <= 0 || $ssRes == 0) {
            return [
                'f_statistic' => null,
                'p_value' => null,
                'df_regression' => $dfReg,
                'df_residual' => $dfRes,
            ];
        }
        
        $msReg = $ssReg / $dfReg;
        $msRes = $ssRes / $dfRes;
        
        $fStatistic = $msReg / $msRes;
        
        $fDist = new F($dfReg, $dfRes);
        $pValue = 1 - $fDist->cdf($fStatistic);
        
        return [
            'f_statistic' => $fStatistic,
            'p_value' => $pValue,
            'df_regression' => $dfReg,
            'df_residual' => $dfRes,
        ];
    }
}