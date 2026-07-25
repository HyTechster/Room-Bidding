<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Colour;
use App\Domain\Pricing\PricingEngine;
use App\Domain\Pricing\Rational;
use PHPUnit\Framework\TestCase;

/**
 * Reproduces the spec 3.5.7 worked example exactly.
 * R = 3000 (=300000 sen), caps [2,2,2,1], N = 6 (C-i), offset 10% -> δ = 1/10.
 *
 * Note: the spec prints round-2 prices as 531.47 / 483.16 / 439.24, which are
 * hand-rounded to ~2 dp. This test asserts the EXACT rational prices (the real
 * invariant) and that they round/truncate to those printed figures.
 */
class WorkedExampleTest extends TestCase
{
    use RationalAssertions;

    private int $R = 300000;
    private array $caps = [2, 2, 2, 1];
    private Rational $delta;

    protected function setUp(): void
    {
        $this->delta = $this->r(1, 10);
    }

    public function test_round_one(): void
    {
        $weights = array_fill(0, 4, $this->r(1));
        $occ = [3, 2, 1, 0];

        $prices = PricingEngine::derivePrices($this->R, $weights, $occ);
        foreach ($prices as $p) {
            $this->assertRationalEquals($this->r(50000), $p); // RM500 each
        }
        $this->assertBudget($prices, $occ);

        $colours = PricingEngine::colours($occ, $this->caps);
        $this->assertSame(
            [Colour::Red, Colour::Green, Colour::Yellow, Colour::Yellow],
            $colours
        );
    }

    public function test_weight_update_after_round_one(): void
    {
        $occ = [3, 2, 1, 0];
        $curr = PricingEngine::colours($occ, $this->caps);
        $prev = array_fill(0, 4, Colour::Green);

        $res = PricingEngine::updateWeights($prev, $curr, array_fill(0, 4, $this->r(1)), [0, 0, 0, 0], $this->delta);

        $this->assertRationalEquals($this->r(11, 10), $res['weights'][0]); // green->red
        $this->assertRationalEquals($this->r(1), $res['weights'][1]);      // green->green
        $this->assertRationalEquals($this->r(10, 11), $res['weights'][2]); // green->yellow
        $this->assertRationalEquals($this->r(10, 11), $res['weights'][3]); // green->yellow
    }

    public function test_round_two_opening_prices(): void
    {
        $weights = [$this->r(11, 10), $this->r(1), $this->r(10, 11), $this->r(10, 11)];
        $occ = [3, 2, 1, 0];

        $prices = PricingEngine::derivePrices($this->R, $weights, $occ);

        // Exact prices in sen.
        $this->assertRationalEquals($this->r(36300000, 683), $prices[0]);
        $this->assertRationalEquals($this->r(33000000, 683), $prices[1]);
        $this->assertRationalEquals($this->r(30000000, 683), $prices[2]);
        $this->assertRationalEquals($this->r(30000000, 683), $prices[3]);

        // Money conserved exactly.
        $this->assertBudget($prices, $occ);

        // Directions: over-subscribed room dearer, everyone else cheaper than RM500.
        $this->assertSame(1, $prices[0]->compareTo($this->r(50000)));
        $this->assertSame(-1, $prices[1]->compareTo($this->r(50000)));
        $this->assertSame(-1, $prices[2]->compareTo($this->r(50000)));

        // Displayed (half-up) figures, in sen: 531.48 / 483.16 / 439.24.
        $this->assertSame('53148', $prices[0]->roundHalfUpInt());
        $this->assertSame('48316', $prices[1]->roundHalfUpInt());
        $this->assertSame('43924', $prices[2]->roundHalfUpInt());
    }

    private function assertBudget(array $prices, array $occ): void
    {
        $sum = Rational::fromInt(0);
        foreach ($prices as $j => $p) {
            $sum = $sum->add($p->mul(Rational::fromInt($occ[$j])));
        }
        $this->assertRationalEquals(Rational::fromInt($this->R), $sum);
    }
}
