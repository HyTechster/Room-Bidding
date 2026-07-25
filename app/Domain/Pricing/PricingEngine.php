<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use InvalidArgumentException;

/**
 * The pricing engine (spec 3.5). Pure, framework-independent, exact.
 *
 * Two-layer model:
 *   Layer 1 — desirability weights w_j > 0 (what bidding moves; all start at 1).
 *   Layer 2 — prices, always DERIVED:  p_j = R · w_j / Σ_k (n_k · w_k)
 *
 * P1 (budget balance) holds structurally: Σ_j n_j·p_j = R · Σ(n_j w_j)/Σ(n_k w_k) = R.
 * P2 (symmetry) holds because weight updates are multiply/divide by (1+rate),
 * which are exact inverses in {@see Rational} arithmetic.
 *
 * All money is in integer minor units (sen). Prices are returned as exact
 * {@see Rational} values in minor units; rounding happens only at settlement.
 */
final class PricingEngine
{
    /**
     * Per-room status colours from occupancy vs capacity.
     *
     * @param int[] $occupancy
     * @param int[] $capacities
     * @return Colour[]
     */
    public static function colours(array $occupancy, array $capacities): array
    {
        self::assertSameLength($occupancy, $capacities);
        $out = [];
        foreach ($occupancy as $j => $n) {
            $out[$j] = Colour::determine((int) $n, (int) $capacities[$j]);
        }
        return $out;
    }

    public static function anyRed(array $colours): bool
    {
        foreach ($colours as $c) {
            if ($c === Colour::Red) {
                return true;
            }
        }
        return false;
    }

    /**
     * Derive live per-person prices (minor units) from weights and occupancy.
     *
     * @param Rational[] $weights
     * @param int[] $occupancy
     * @return Rational[] per-person price for each room, in minor units
     */
    public static function derivePrices(int $rentMinorUnits, array $weights, array $occupancy): array
    {
        self::assertSameLength($weights, $occupancy);

        // Denominator D = Σ_k n_k · w_k
        $denom = Rational::fromInt(0);
        foreach ($weights as $j => $w) {
            $denom = $denom->add($w->mul(Rational::fromInt((int) $occupancy[$j])));
        }

        if ($denom->isZero()) {
            throw new InvalidArgumentException('Cannot derive prices when total weighted occupancy is zero.');
        }

        $rent = Rational::fromInt($rentMinorUnits);
        $prices = [];
        foreach ($weights as $j => $w) {
            // p_j = R · w_j / D  (advertised even for empty rooms, n_j = 0)
            $prices[$j] = $rent->mul($w)->div($denom);
        }
        return $prices;
    }

    /**
     * Apply the unified transition rule (spec 3.5.4) to produce next-round weights
     * and flip counters. Weights change between rounds only.
     *
     * Round 1 has no previous colour: pass Colour::Green for every room.
     *
     * @param Colour[]   $prevColours
     * @param Colour[]   $currColours
     * @param Rational[] $weights
     * @param int[]      $flips        per-room flip counter f entering the round
     * @param Rational   $delta        normalised offset δ
     * @return array{weights: Rational[], flips: int[]}
     */
    public static function updateWeights(
        array $prevColours,
        array $currColours,
        array $weights,
        array $flips,
        Rational $delta,
    ): array {
        self::assertSameLength($prevColours, $currColours);
        self::assertSameLength($currColours, $weights);
        self::assertSameLength($weights, $flips);

        $newWeights = [];
        $newFlips = [];

        foreach ($currColours as $j => $curr) {
            $prev = $prevColours[$j];
            $w = $weights[$j];
            $f = (int) $flips[$j];

            if ($curr === Colour::Green) {
                // Green resets the damping and holds the weight static.
                $newFlips[$j] = 0;
                $newWeights[$j] = $w;
                continue;
            }

            $isFlip = ($prev === Colour::Yellow && $curr === Colour::Red)
                   || ($prev === Colour::Red && $curr === Colour::Yellow);

            if ($isFlip) {
                $f = $f + 1;
            }

            // rate = δ / 2^f   (per-room damping; another room's f never affects this one)
            $rate = $delta->div(Rational::of(bcpow('2', (string) $f)));
            $factor = Rational::fromInt(1)->add($rate);

            $newWeights[$j] = $curr === Colour::Red
                ? $w->mul($factor)    // over-subscribed -> dearer
                : $w->div($factor);   // under-filled    -> cheaper
            $newFlips[$j] = $f;
        }

        return ['weights' => $newWeights, 'flips' => $newFlips];
    }

    private static function assertSameLength(array $a, array $b): void
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException('Room-indexed arrays must have equal length.');
        }
    }
}
