<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Colour;
use App\Domain\Pricing\Offset;
use App\Domain\Pricing\PricingEngine;
use App\Domain\Pricing\Rational;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * C-i and C-ii scenarios terminate with no red room within the round cap.
 *
 * Models a resolution process: starting from a fully over-subscribed room, each
 * round the excess occupants of red rooms move to rooms with spare capacity
 * (cheapest advertised price first). Weights update between rounds per the
 * engine, and budget balance is asserted every round.
 */
class TerminationTest extends TestCase
{
    use RationalAssertions;

    #[DataProvider('configProvider')]
    public function test_terminates_without_red_within_cap(array $caps, int $N): void
    {
        $R = 300000;
        $cap = 20; // round cap (Q3)
        $delta = Offset::fromPercentage($this->r(10));
        $rooms = count($caps);

        $weights = array_fill(0, $rooms, $this->r(1));
        $flips = array_fill(0, $rooms, 0);
        $prev = array_fill(0, $rooms, Colour::Green);

        // Start with everyone piled into room 0 (maximally over-subscribed).
        $occ = array_fill(0, $rooms, 0);
        $occ[0] = $N;

        $terminated = false;
        for ($round = 0; $round < $cap; $round++) {
            $prices = PricingEngine::derivePrices($R, $weights, $occ);
            $this->assertBudget($R, $prices, $occ);

            $curr = PricingEngine::colours($occ, $caps);
            if (!PricingEngine::anyRed($curr)) {
                $terminated = true;
                break;
            }

            // Move each red room's excess into rooms with spare capacity, cheapest first.
            foreach ($curr as $j => $c) {
                while ($occ[$j] > $caps[$j]) {
                    $target = $this->cheapestRoomWithSpare($prices, $occ, $caps);
                    $this->assertNotNull($target, 'C-i/C-ii guarantees spare capacity exists');
                    $occ[$j]--;
                    $occ[$target]++;
                }
            }

            $res = PricingEngine::updateWeights($prev, $curr, $weights, $flips, $delta);
            $weights = $res['weights'];
            $flips = $res['flips'];
            $prev = $curr;
        }

        $this->assertTrue($terminated, 'did not terminate within the round cap');
        $this->assertFalse(PricingEngine::anyRed(PricingEngine::colours($occ, $caps)));
    }

    public static function configProvider(): array
    {
        return [
            'fixture C-i [2,2,2,1] N=6'  => [[2, 2, 2, 1], 6],
            'C-ii exact fill [2,2,2,1]'  => [[2, 2, 2, 1], 7],
            'C-ii [2,1,1] N=4'           => [[2, 1, 1], 4],
            'C-i [3,3,2] N=5'            => [[3, 3, 2], 5],
            'single big room [5] N=5'    => [[5], 5],
        ];
    }

    private function cheapestRoomWithSpare(array $prices, array $occ, array $caps): ?int
    {
        $best = null;
        foreach ($caps as $j => $c) {
            if ($occ[$j] < $c) {
                if ($best === null || $prices[$j]->compareTo($prices[$best]) < 0) {
                    $best = $j;
                }
            }
        }
        return $best;
    }

    private function assertBudget(int $R, array $prices, array $occ): void
    {
        $sum = Rational::fromInt(0);
        foreach ($prices as $j => $p) {
            $sum = $sum->add($p->mul(Rational::fromInt($occ[$j])));
        }
        $this->assertTrue($sum->equals(Rational::fromInt($R)), 'budget balance');
    }
}
