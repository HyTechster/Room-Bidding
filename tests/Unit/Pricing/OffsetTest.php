<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Colour;
use App\Domain\Pricing\Offset;
use App\Domain\Pricing\PricingEngine;
use App\Domain\Pricing\Rational;
use PHPUnit\Framework\TestCase;

class OffsetTest extends TestCase
{
    use RationalAssertions;

    public function test_percentage_normalisation(): void
    {
        $this->assertRationalEquals($this->r(1, 10), Offset::fromPercentage($this->r(10)));
        $this->assertRationalEquals($this->r(1, 8), Offset::fromPercentage(Rational::fromDecimalString('12.5')));
    }

    public function test_fixed_amount_normalisation(): void
    {
        // R=300000 sen, N=6 -> baseline R/N = 50000. A=5000 sen -> δ = 5000/50000 = 1/10.
        $this->assertRationalEquals($this->r(1, 10), Offset::fromFixedAmount(5000, 300000, 6));
    }

    /**
     * Percentage and fixed-amount offsets that normalise to the same δ must
     * produce identical weights and prices (spec 3.5.2).
     */
    public function test_percentage_and_fixed_yield_identical_behaviour(): void
    {
        $R = 300000;
        $N = 6;
        $deltaPct = Offset::fromPercentage($this->r(10));          // 10%
        $deltaFix = Offset::fromFixedAmount(5000, $R, $N);         // RM50 of a RM500 baseline

        $this->assertRationalEquals($deltaPct, $deltaFix);

        $caps = [2, 2, 2, 1];
        $occ = [3, 2, 1, 0];
        $weights = array_fill(0, 4, $this->r(1));
        $flips = [0, 0, 0, 0];
        $prev = array_fill(0, 4, Colour::Green);
        $curr = PricingEngine::colours($occ, $caps);

        $resPct = PricingEngine::updateWeights($prev, $curr, $weights, $flips, $deltaPct);
        $resFix = PricingEngine::updateWeights($prev, $curr, $weights, $flips, $deltaFix);

        foreach ($resPct['weights'] as $j => $w) {
            $this->assertRationalEquals($w, $resFix['weights'][$j], "weight room {$j}");
        }

        $pricesPct = PricingEngine::derivePrices($R, $resPct['weights'], $occ);
        $pricesFix = PricingEngine::derivePrices($R, $resFix['weights'], $occ);
        foreach ($pricesPct as $j => $p) {
            $this->assertRationalEquals($p, $pricesFix[$j], "price room {$j}");
        }
    }
}
