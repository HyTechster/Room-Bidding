<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

/**
 * Normalises the host's offset into a single dimensionless rate δ (spec 3.5.2).
 *
 *   Percentage P  ->  δ = P / 100
 *   Fixed amount A (in minor units) -> δ = A / (R / N) = A·N / R
 *
 * Both units then drive the same multiplicative machinery, so percentage and
 * fixed-amount offsets produce identical behaviour once normalised.
 */
final class Offset
{
    /** @param Rational $percent the percentage value P (e.g. 10 for 10%) */
    public static function fromPercentage(Rational $percent): Rational
    {
        return $percent->div(Rational::fromInt(100));
    }

    /**
     * @param int $amountMinorUnits fixed offset A in minor units (sen)
     * @param int $rentMinorUnits   total rent R in minor units (sen)
     * @param int $numTenants       N
     */
    public static function fromFixedAmount(int $amountMinorUnits, int $rentMinorUnits, int $numTenants): Rational
    {
        // δ = A·N / R
        return Rational::of($amountMinorUnits * $numTenants, $rentMinorUnits);
    }
}
