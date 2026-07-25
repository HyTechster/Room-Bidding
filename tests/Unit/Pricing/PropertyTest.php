<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Colour;
use App\Domain\Pricing\Offset;
use App\Domain\Pricing\PricingEngine;
use App\Domain\Pricing\Rational;
use PHPUnit\Framework\TestCase;

/**
 * Randomised property-based tests. Over many random configurations and random
 * occupancy sequences, budget balance (Σ n_j·p_j = R) must hold EXACTLY every
 * round, weights must stay strictly positive, and prices must stay positive.
 */
class PropertyTest extends TestCase
{
    use RationalAssertions;

    public function test_budget_balance_and_positivity_over_random_runs(): void
    {
        mt_srand(20260725); // deterministic

        for ($iter = 0; $iter < 300; $iter++) {
            $rooms = mt_rand(2, 6);
            $caps = [];
            $totalCap = 0;
            for ($j = 0; $j < $rooms; $j++) {
                $c = mt_rand(1, 3);
                $caps[$j] = $c;
                $totalCap += $c;
            }
            // C-i or C-ii: 1 <= N <= totalCap
            $N = mt_rand(1, $totalCap);
            $R = mt_rand(1000, 1000000); // sen

            $delta = mt_rand(0, 1) === 0
                ? Offset::fromPercentage(Rational::of(mt_rand(1, 30)))
                : Offset::fromFixedAmount(mt_rand(1, max(1, intdiv($R, $N))), $R, $N);

            $weights = array_fill(0, $rooms, $this->r(1));
            $flips = array_fill(0, $rooms, 0);
            $prev = array_fill(0, $rooms, Colour::Green);

            for ($round = 0; $round < 12; $round++) {
                // Random occupancy summing to N (every tenant placed somewhere).
                $occ = array_fill(0, $rooms, 0);
                for ($t = 0; $t < $N; $t++) {
                    $occ[mt_rand(0, $rooms - 1)]++;
                }

                $prices = PricingEngine::derivePrices($R, $weights, $occ);

                // Budget balance, exactly.
                $sum = Rational::fromInt(0);
                foreach ($prices as $j => $p) {
                    $this->assertTrue($p->isPositive(), 'price positive');
                    $sum = $sum->add($p->mul(Rational::fromInt($occ[$j])));
                }
                $this->assertTrue(
                    $sum->equals(Rational::fromInt($R)),
                    "budget balance failed: iter={$iter} round={$round} sum={$sum->toString()} R={$R}"
                );

                // Advance.
                $curr = PricingEngine::colours($occ, $caps);
                $res = PricingEngine::updateWeights($prev, $curr, $weights, $flips, $delta);
                foreach ($res['weights'] as $w) {
                    $this->assertTrue($w->isPositive(), 'weight positive');
                }
                $weights = $res['weights'];
                $flips = $res['flips'];
                $prev = $curr;
            }
        }
    }
}
