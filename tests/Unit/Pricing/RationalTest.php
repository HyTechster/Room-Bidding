<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Rational;
use PHPUnit\Framework\TestCase;

class RationalTest extends TestCase
{
    use RationalAssertions;

    public function test_reduces_to_lowest_terms(): void
    {
        $this->assertRationalEquals($this->r(1, 2), $this->r(50, 100));
        $this->assertRationalEquals($this->r(-1, 3), $this->r(2, -6));
        $this->assertRationalEquals($this->r(0), $this->r(0, 7));
    }

    public function test_parses_decimal_strings_exactly(): void
    {
        $this->assertRationalEquals($this->r(3000), Rational::fromDecimalString('3000.00'));
        $this->assertRationalEquals($this->r(1, 10), Rational::fromDecimalString('0.10'));
        $this->assertRationalEquals($this->r(25, 2), Rational::fromDecimalString('12.5'));
    }

    public function test_arithmetic(): void
    {
        $this->assertRationalEquals($this->r(5, 6), $this->r(1, 2)->add($this->r(1, 3)));
        $this->assertRationalEquals($this->r(1, 6), $this->r(1, 2)->sub($this->r(1, 3)));
        $this->assertRationalEquals($this->r(1, 6), $this->r(1, 2)->mul($this->r(1, 3)));
        $this->assertRationalEquals($this->r(3, 2), $this->r(1, 2)->div($this->r(1, 3)));
    }

    /** P2 core: ×(1+r) then ÷(1+r) returns EXACTLY the original, both orders. */
    public function test_multiply_then_divide_is_exact_inverse(): void
    {
        foreach ([$this->r(1, 10), $this->r(1, 20), $this->r(1, 40), $this->r(7, 3), $this->r(123, 1000)] as $rate) {
            $factor = Rational::fromInt(1)->add($rate);
            foreach ([$this->r(1), $this->r(11, 10), $this->r(10, 11), $this->r(999, 1000)] as $w) {
                $this->assertRationalEquals($w, $w->mul($factor)->div($factor), 'up-then-down');
                $this->assertRationalEquals($w, $w->div($factor)->mul($factor), 'down-then-up');
            }
        }
    }

    public function test_floor_and_round(): void
    {
        $this->assertSame('531', $this->r(53147, 100)->floorInt());
        $this->assertSame('531', $this->r(53148, 100)->roundHalfUpInt()); // 531.48 -> 531
        $this->assertSame('532', $this->r(53150, 100)->roundHalfUpInt()); // 531.50 -> 532 (half up)
        $this->assertSame('0', $this->r(0)->floorInt());
    }
}
