<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Rational;

trait RationalAssertions
{
    protected function r(int|string $num, int|string $den = 1): Rational
    {
        return Rational::of($num, $den);
    }

    protected function assertRationalEquals(Rational $expected, Rational $actual, string $msg = ''): void
    {
        $this->assertTrue(
            $expected->equals($actual),
            ($msg ? $msg.': ' : '')."expected {$expected->toString()} but got {$actual->toString()}"
        );
    }
}
