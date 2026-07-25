<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Colour;
use App\Domain\Pricing\PricingEngine;
use App\Domain\Pricing\Rational;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PricingEngineTest extends TestCase
{
    use RationalAssertions;

    private Rational $delta; // δ = 1/10

    protected function setUp(): void
    {
        $this->delta = $this->r(1, 10);
    }

    // ---- Colours ----------------------------------------------------------

    public function test_colour_determination(): void
    {
        $this->assertSame(Colour::Green, Colour::determine(2, 2));
        $this->assertSame(Colour::Yellow, Colour::determine(1, 2));
        $this->assertSame(Colour::Red, Colour::determine(3, 2));
    }

    // ---- Round 1 ----------------------------------------------------------

    /** Round 1: every weight is 1, so every room prices at exactly R/N. */
    public function test_round_one_prices_equal_rent_over_tenants(): void
    {
        $R = 300000; // sen
        $weights = [$this->r(1), $this->r(1), $this->r(1), $this->r(1)];
        $occupancy = [3, 2, 1, 0]; // Σ = 6 = N
        $prices = PricingEngine::derivePrices($R, $weights, $occupancy);

        foreach ($prices as $p) {
            $this->assertRationalEquals($this->r(50000), $p); // 300000 / 6
        }
        $this->assertBudgetBalances($R, $prices, $occupancy);
    }

    /** Round 1 uses GREEN as the previous colour for every room. */
    public function test_round_one_uses_green_as_previous_colour(): void
    {
        $caps = [2, 2, 2, 1];
        $occ = [3, 2, 1, 0];
        $weights = [$this->r(1), $this->r(1), $this->r(1), $this->r(1)];
        $flips = [0, 0, 0, 0];

        $curr = PricingEngine::colours($occ, $caps);
        $prev = array_fill(0, 4, Colour::Green); // round-1 convention

        $res = PricingEngine::updateWeights($prev, $curr, $weights, $flips, $this->delta);

        // room0 green->red (dearer), room1 green->green (static), room2/3 green->yellow (cheaper)
        $this->assertRationalEquals($this->r(11, 10), $res['weights'][0]);
        $this->assertRationalEquals($this->r(1), $res['weights'][1]);
        $this->assertRationalEquals($this->r(10, 11), $res['weights'][2]);
        $this->assertRationalEquals($this->r(10, 11), $res['weights'][3]);
        $this->assertSame([0, 0, 0, 0], $res['flips']);
    }

    // ---- The nine colour transitions -------------------------------------

    #[DataProvider('transitionProvider')]
    public function test_nine_transitions(Colour $prev, Colour $curr, int $startF, int $expectedF, int $direction, int $rateExp): void
    {
        $w0 = $this->r(1);
        $res = PricingEngine::updateWeights([$prev], [$curr], [$w0], [$startF], $this->delta);

        $this->assertSame($expectedF, $res['flips'][0], 'flip counter');

        if ($direction === 0) {
            $this->assertRationalEquals($w0, $res['weights'][0], 'static');
            return;
        }

        $rate = $this->delta->div(Rational::of(bcpow('2', (string) $rateExp)));
        $factor = Rational::fromInt(1)->add($rate);
        $expected = $direction > 0 ? $w0->mul($factor) : $w0->div($factor);
        $this->assertRationalEquals($expected, $res['weights'][0], 'weight');

        // Direction sanity: red dearer (>1), yellow cheaper (<1).
        $this->assertSame($direction, $res['weights'][0]->compareTo($w0));
    }

    public static function transitionProvider(): array
    {
        [$G, $Y, $Rr] = [Colour::Green, Colour::Yellow, Colour::Red];
        // [prev, curr, startF, expectedF, direction(+1 dearer / -1 cheaper / 0 static), rateExponent]
        return [
            'G->G static'        => [$G, $G, 3, 0, 0, 0],
            'G->Y cheaper full'  => [$G, $Y, 0, 0, -1, 0],
            'G->R dearer full'   => [$G, $Rr, 0, 0, +1, 0],
            'Y->G static'        => [$Y, $G, 2, 0, 0, 0],
            'Y->Y cheaper damped'=> [$Y, $Y, 2, 2, -1, 2],
            'Y->R flip dearer'   => [$Y, $Rr, 0, 1, +1, 1],
            'R->G static'        => [$Rr, $G, 2, 0, 0, 0],
            'R->Y flip cheaper'  => [$Rr, $Y, 1, 2, -1, 2],
            'R->R dearer damped' => [$Rr, $Rr, 2, 2, +1, 2],
        ];
    }

    // ---- Damping across consecutive flips --------------------------------

    /** 1, 2, 3, 4 consecutive flips give rates δ/2, δ/4, δ/8, δ/16. */
    public function test_damping_across_consecutive_flips(): void
    {
        // green -> red -> yellow -> red -> yellow -> red
        $sequence = [Colour::Red, Colour::Yellow, Colour::Red, Colour::Yellow, Colour::Red];
        $prev = Colour::Green;
        $w = $this->r(1);
        $f = 0;
        $expectedExponents = [0, 1, 2, 3, 4]; // δ, δ/2, δ/4, δ/8, δ/16

        foreach ($sequence as $i => $curr) {
            $before = $w;
            $res = PricingEngine::updateWeights([$prev], [$curr], [$w], [$f], $this->delta);
            $w = $res['weights'][0];
            $f = $res['flips'][0];

            $rate = $this->delta->div(Rational::of(bcpow('2', (string) $expectedExponents[$i])));
            $expectedRate = $curr === Colour::Red
                ? $w->div($before)->sub(Rational::fromInt(1))      // factor - 1
                : $before->div($w)->sub(Rational::fromInt(1));     // (1/factor)^-1 - 1
            $this->assertRationalEquals($rate, $expectedRate, "flip #{$i} rate");
            $prev = $curr;
        }
    }

    /** Green resets the flip counter to 0. */
    public function test_green_resets_flip_counter(): void
    {
        // Build up f via flips, then hit green.
        $res = PricingEngine::updateWeights([Colour::Yellow], [Colour::Red], [$this->r(1)], [3], $this->delta);
        $this->assertSame(4, $res['flips'][0]);

        $res2 = PricingEngine::updateWeights([Colour::Red], [Colour::Green], $res['weights'], $res['flips'], $this->delta);
        $this->assertSame(0, $res2['flips'][0]);
        $this->assertRationalEquals($res['weights'][0], $res2['weights'][0], 'green is static');
    }

    /** One room's damping never changes another room's rate in the same round. */
    public function test_damping_is_per_room(): void
    {
        // Room A deep in oscillation (high f); room B seeing red for the first time (f=0).
        $prev = [Colour::Yellow, Colour::Green];
        $curr = [Colour::Red, Colour::Red];
        $weights = [$this->r(1), $this->r(1)];
        $flips = [5, 0];

        $res = PricingEngine::updateWeights($prev, $curr, $weights, $flips, $this->delta);

        // Room A: flip -> f=6, rate δ/2^6
        $rateA = $this->delta->div(Rational::of(bcpow('2', '6')));
        $this->assertRationalEquals(Rational::fromInt(1)->add($rateA), $res['weights'][0]);
        $this->assertSame(6, $res['flips'][0]);

        // Room B: full offset δ, unaffected by room A.
        $this->assertRationalEquals(Rational::fromInt(1)->add($this->delta), $res['weights'][1]);
        $this->assertSame(0, $res['flips'][1]);
    }

    // ---- Positivity -------------------------------------------------------

    /** Prices stay strictly positive under a long run of consecutive decreases. */
    public function test_prices_positive_under_long_decreases(): void
    {
        $w = $this->r(1);
        $prev = Colour::Green;
        $f = 0;
        for ($i = 0; $i < 100; $i++) {
            $res = PricingEngine::updateWeights([$prev], [Colour::Yellow], [$w], [$f], $this->delta);
            $w = $res['weights'][0];
            $f = $res['flips'][0];
            $prev = Colour::Yellow;
            $this->assertTrue($w->isPositive(), "weight positive at step {$i}");
        }
        // Derived price for that room is also strictly positive.
        $prices = PricingEngine::derivePrices(300000, [$w, $this->r(1)], [1, 5]);
        $this->assertTrue($prices[0]->isPositive());
    }

    // ---- helpers ----------------------------------------------------------

    private function assertBudgetBalances(int $R, array $prices, array $occupancy): void
    {
        $sum = Rational::fromInt(0);
        foreach ($prices as $j => $p) {
            $sum = $sum->add($p->mul(Rational::fromInt((int) $occupancy[$j])));
        }
        $this->assertRationalEquals(Rational::fromInt($R), $sum, 'budget balance Σ n_j·p_j = R');
    }
}
