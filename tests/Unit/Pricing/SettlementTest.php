<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\PricingEngine;
use App\Domain\Pricing\Rational;
use App\Domain\Pricing\Settlement;
use PHPUnit\Framework\TestCase;

class SettlementTest extends TestCase
{
    use RationalAssertions;

    /** Rounded per-person minor units sum to exactly R. */
    public function test_largest_remainder_sums_to_total(): void
    {
        // Three tenants sharing 1000 sen equally: 333.33... each -> 334,333,333.
        $amounts = [$this->r(1000, 3), $this->r(1000, 3), $this->r(1000, 3)];
        $rounded = Settlement::largestRemainder($amounts, 1000);

        $this->assertSame(1000, array_sum($rounded));
        // Leftover sen goes to the earliest indices on a tie.
        $this->assertSame([334, 333, 333], $rounded);
    }

    public function test_exact_amounts_are_unchanged(): void
    {
        $amounts = [$this->r(500), $this->r(500)];
        $this->assertSame([500, 500], Settlement::largestRemainder($amounts, 1000));
    }

    /** Settlement of the worked-example round-2 occupancy sums to exactly R. */
    public function test_settlement_of_worked_example_round_two(): void
    {
        $R = 300000;
        $weights = [$this->r(11, 10), $this->r(1), $this->r(10, 11), $this->r(10, 11)];
        $occ = [3, 2, 1, 0];
        $prices = PricingEngine::derivePrices($R, $weights, $occ);

        // Build per-tenant amounts in stable order (room index, then join order).
        $tenantAmounts = [];
        foreach ($occ as $j => $n) {
            for ($k = 0; $k < $n; $k++) {
                $tenantAmounts[] = $prices[$j];
            }
        }
        $rounded = Settlement::largestRemainder($tenantAmounts, $R);

        $this->assertSame($R, array_sum($rounded));
        $this->assertCount(6, $rounded); // N = 6 tenants
    }

    public function test_empty_settlement(): void
    {
        $this->assertSame([], Settlement::largestRemainder([], 0));
    }
}
