<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use InvalidArgumentException;

/**
 * Largest-remainder rounding at settlement (spec 3.5.6).
 *
 * Given each tenant's exact amount (minor units, as {@see Rational}), produce
 * integer minor-unit amounts that sum to EXACTLY the total rent: floor every
 * amount, then hand out the leftover minor units one at a time to the tenants
 * with the largest fractional remainders, breaking ties by array order.
 *
 * The caller must pass amounts already in the stable deterministic tie-break
 * order (spec: room index, then join order); ties in remainder then go to the
 * earliest index.
 */
final class Settlement
{
    /**
     * @param Rational[] $exactAmounts per-tenant exact amounts, minor units, in stable order
     * @param int $totalMinorUnits total rent R (minor units); must equal Σ exactAmounts
     * @return int[] rounded per-tenant amounts (minor units) summing to exactly $totalMinorUnits
     */
    public static function largestRemainder(array $exactAmounts, int $totalMinorUnits): array
    {
        $n = count($exactAmounts);
        if ($n === 0) {
            if ($totalMinorUnits !== 0) {
                throw new InvalidArgumentException('No tenants but non-zero total.');
            }
            return [];
        }

        $floors = [];
        $remNum = [];   // fractional remainder numerator per tenant
        $remDen = [];   // fractional remainder denominator per tenant
        $sumFloor = '0';

        foreach ($exactAmounts as $i => $amount) {
            $floor = $amount->floorInt();
            $floors[$i] = $floor;
            [$rNum, $rDen] = $amount->fractionalRemainder();
            $remNum[$i] = $rNum;
            $remDen[$i] = $rDen;
            $sumFloor = bcadd($sumFloor, $floor);
        }

        $leftover = bcsub((string) $totalMinorUnits, $sumFloor);
        if (bccomp($leftover, '0') < 0) {
            throw new InvalidArgumentException('Floored amounts exceed the total — inputs do not sum to the total.');
        }
        $leftover = (int) $leftover;

        // Order tenants by fractional remainder desc, ties by index asc.
        $order = range(0, $n - 1);
        usort($order, function (int $a, int $b) use ($remNum, $remDen) {
            // compare remNum[a]/remDen[a] vs remNum[b]/remDen[b]  (denominators positive)
            $cmp = bccomp(bcmul($remNum[$b], $remDen[$a]), bcmul($remNum[$a], $remDen[$b]));
            return $cmp !== 0 ? $cmp : ($a <=> $b);
        });

        $result = [];
        foreach ($floors as $i => $floor) {
            $result[$i] = $floor;
        }
        for ($k = 0; $k < $leftover; $k++) {
            $i = $order[$k];
            $result[$i] = bcadd($result[$i], '1');
        }

        // Cast back to int (minor-unit amounts are within PHP int range for realistic rents).
        return array_map(static fn ($v) => (int) $v, $result);
    }
}
