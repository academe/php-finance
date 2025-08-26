<?php

declare(strict_types=1);

namespace Academe\PhpFinance\Options;

use InvalidArgumentException;
use MathPHP\Probability\Distribution\Continuous\StandardNormal;

class BlackScholes
{
    public function __construct(
        private float $spotPrice,
        private float $strikePrice,
        private float $timeToExpiry,
        private float $riskFreeRate,
        private float $volatility,
        private ?float $dividendYield = null
    ) {
        if ($spotPrice <= 0 || $strikePrice <= 0) {
            throw new InvalidArgumentException('Spot price and strike price must be positive');
        }
        
        if ($timeToExpiry < 0) {
            throw new InvalidArgumentException('Time to expiry cannot be negative');
        }
        
        if ($volatility < 0) {
            throw new InvalidArgumentException('Volatility cannot be negative');
        }
        
        $this->dividendYield = $dividendYield ?? 0.0;
    }
    
    private function d1(): float
    {
        if ($this->timeToExpiry == 0) {
            return 0.0;
        }
        
        if ($this->volatility == 0) {
            if ($this->spotPrice > $this->strikePrice) {
                return PHP_FLOAT_MAX;
            } elseif ($this->spotPrice < $this->strikePrice) {
                return -PHP_FLOAT_MAX;
            } else {
                return 0.0;
            }
        }
        
        $numerator = log($this->spotPrice / $this->strikePrice) + 
                    ($this->riskFreeRate - $this->dividendYield + 0.5 * pow($this->volatility, 2)) * $this->timeToExpiry;
        $denominator = $this->volatility * sqrt($this->timeToExpiry);
        
        return $numerator / $denominator;
    }
    
    private function d2(): float
    {
        return $this->d1() - $this->volatility * sqrt($this->timeToExpiry);
    }
    
    private function normCdf(float $x): float
    {
        $normal = new StandardNormal();
        return $normal->cdf($x);
    }
    
    private function normPdf(float $x): float
    {
        return exp(-0.5 * pow($x, 2)) / sqrt(2 * M_PI);
    }
    
    public function callPrice(): float
    {
        if ($this->timeToExpiry == 0) {
            return max(0, $this->spotPrice - $this->strikePrice);
        }
        
        $d1 = $this->d1();
        $d2 = $this->d2();
        
        $discountFactor = exp(-$this->riskFreeRate * $this->timeToExpiry);
        $dividendDiscount = exp(-$this->dividendYield * $this->timeToExpiry);
        
        return $this->spotPrice * $dividendDiscount * $this->normCdf($d1) - 
               $this->strikePrice * $discountFactor * $this->normCdf($d2);
    }
    
    public function putPrice(): float
    {
        if ($this->timeToExpiry == 0) {
            return max(0, $this->strikePrice - $this->spotPrice);
        }
        
        $d1 = $this->d1();
        $d2 = $this->d2();
        
        $discountFactor = exp(-$this->riskFreeRate * $this->timeToExpiry);
        $dividendDiscount = exp(-$this->dividendYield * $this->timeToExpiry);
        
        return $this->strikePrice * $discountFactor * $this->normCdf(-$d2) - 
               $this->spotPrice * $dividendDiscount * $this->normCdf(-$d1);
    }
    
    public function callDelta(): float
    {
        if ($this->timeToExpiry == 0) {
            return $this->spotPrice > $this->strikePrice ? 1.0 : 0.0;
        }
        
        $dividendDiscount = exp(-$this->dividendYield * $this->timeToExpiry);
        return $dividendDiscount * $this->normCdf($this->d1());
    }
    
    public function putDelta(): float
    {
        if ($this->timeToExpiry == 0) {
            return $this->spotPrice < $this->strikePrice ? -1.0 : 0.0;
        }
        
        $dividendDiscount = exp(-$this->dividendYield * $this->timeToExpiry);
        return -$dividendDiscount * $this->normCdf(-$this->d1());
    }
    
    public function gamma(): float
    {
        if ($this->timeToExpiry == 0 || $this->volatility == 0) {
            return 0.0;
        }
        
        $dividendDiscount = exp(-$this->dividendYield * $this->timeToExpiry);
        $denominator = $this->spotPrice * $this->volatility * sqrt($this->timeToExpiry);
        
        if ($denominator == 0) {
            return 0.0;
        }
        
        return $dividendDiscount * $this->normPdf($this->d1()) / $denominator;
    }
    
    public function vega(): float
    {
        if ($this->timeToExpiry == 0) {
            return 0.0;
        }
        
        $dividendDiscount = exp(-$this->dividendYield * $this->timeToExpiry);
        return $this->spotPrice * $dividendDiscount * $this->normPdf($this->d1()) * sqrt($this->timeToExpiry) / 100;
    }
    
    public function callTheta(): float
    {
        if ($this->timeToExpiry == 0) {
            return 0.0;
        }
        
        $d1 = $this->d1();
        $d2 = $this->d2();
        
        $dividendDiscount = exp(-$this->dividendYield * $this->timeToExpiry);
        $discountFactor = exp(-$this->riskFreeRate * $this->timeToExpiry);
        
        $term1 = -$this->spotPrice * $dividendDiscount * $this->normPdf($d1) * $this->volatility / 
                 (2 * sqrt($this->timeToExpiry));
        $term2 = $this->riskFreeRate * $this->strikePrice * $discountFactor * $this->normCdf($d2);
        $term3 = $this->dividendYield * $this->spotPrice * $dividendDiscount * $this->normCdf($d1);
        
        return ($term1 - $term2 + $term3) / 365;
    }
    
    public function putTheta(): float
    {
        if ($this->timeToExpiry == 0) {
            return 0.0;
        }
        
        $d1 = $this->d1();
        $d2 = $this->d2();
        
        $dividendDiscount = exp(-$this->dividendYield * $this->timeToExpiry);
        $discountFactor = exp(-$this->riskFreeRate * $this->timeToExpiry);
        
        $term1 = -$this->spotPrice * $dividendDiscount * $this->normPdf($d1) * $this->volatility / 
                 (2 * sqrt($this->timeToExpiry));
        $term2 = $this->riskFreeRate * $this->strikePrice * $discountFactor * $this->normCdf(-$d2);
        $term3 = $this->dividendYield * $this->spotPrice * $dividendDiscount * $this->normCdf(-$d1);
        
        return ($term1 + $term2 - $term3) / 365;
    }
    
    public function callRho(): float
    {
        if ($this->timeToExpiry == 0) {
            return 0.0;
        }
        
        $d2 = $this->d2();
        $discountFactor = exp(-$this->riskFreeRate * $this->timeToExpiry);
        
        return $this->strikePrice * $this->timeToExpiry * $discountFactor * $this->normCdf($d2) / 100;
    }
    
    public function putRho(): float
    {
        if ($this->timeToExpiry == 0) {
            return 0.0;
        }
        
        $d2 = $this->d2();
        $discountFactor = exp(-$this->riskFreeRate * $this->timeToExpiry);
        
        return -$this->strikePrice * $this->timeToExpiry * $discountFactor * $this->normCdf(-$d2) / 100;
    }
    
    public function impliedVolatility(
        float $marketPrice,
        string $optionType = 'call',
        float $tolerance = 1e-6,
        int $maxIterations = 100
    ): float {
        if (!in_array($optionType, ['call', 'put'])) {
            throw new InvalidArgumentException('Option type must be "call" or "put"');
        }
        
        $vol = 0.2;
        
        for ($i = 0; $i < $maxIterations; $i++) {
            $this->volatility = $vol;
            
            if ($optionType === 'call') {
                $price = $this->callPrice();
            } else {
                $price = $this->putPrice();
            }
            
            $vega = $this->vega() * 100;
            
            if (abs($price - $marketPrice) < $tolerance) {
                return $vol;
            }
            
            if ($vega == 0) {
                break;
            }
            
            $vol = $vol - ($price - $marketPrice) / $vega;
            
            if ($vol <= 0) {
                $vol = 0.001;
            }
        }
        
        return $vol;
    }
    
    public function getGreeks(string $optionType = 'call'): array
    {
        if (!in_array($optionType, ['call', 'put'])) {
            throw new InvalidArgumentException('Option type must be "call" or "put"');
        }
        
        return [
            'delta' => $optionType === 'call' ? $this->callDelta() : $this->putDelta(),
            'gamma' => $this->gamma(),
            'vega' => $this->vega(),
            'theta' => $optionType === 'call' ? $this->callTheta() : $this->putTheta(),
            'rho' => $optionType === 'call' ? $this->callRho() : $this->putRho(),
        ];
    }
    
    public function getSummary(string $optionType = 'call'): array
    {
        if (!in_array($optionType, ['call', 'put'])) {
            throw new InvalidArgumentException('Option type must be "call" or "put"');
        }
        
        $price = $optionType === 'call' ? $this->callPrice() : $this->putPrice();
        $greeks = $this->getGreeks($optionType);
        
        return [
            'type' => $optionType,
            'spot_price' => $this->spotPrice,
            'strike_price' => $this->strikePrice,
            'time_to_expiry' => $this->timeToExpiry,
            'risk_free_rate' => $this->riskFreeRate,
            'volatility' => $this->volatility,
            'dividend_yield' => $this->dividendYield,
            'price' => $price,
            'greeks' => $greeks,
        ];
    }
}