<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use InvalidArgumentException;

/**
 * Exact rational number (arbitrary-precision fraction) backed by BCMath integers.
 *
 * The pricing engine needs exact arithmetic so that:
 *   - Budget balance (P1) holds identically: Σ n_j·p_j == R with no drift.
 *   - Symmetry (P2) holds exactly: w·(1+r) then ÷(1+r) returns the ORIGINAL value.
 * Binary floats cannot guarantee either; decimals with a fixed scale lose exactness
 * on non-terminating divisions. Fractions kept in lowest terms do neither.
 *
 * Immutable. Numerator carries the sign; denominator is always positive.
 * This class has no framework dependencies.
 */
final class Rational
{
    private function __construct(
        public readonly string $num,
        public readonly string $den,
    ) {}

    public static function of(int|string $num, int|string $den = '1'): self
    {
        $num = (string) $num;
        $den = (string) $den;

        if (bccomp($den, '0') === 0) {
            throw new InvalidArgumentException('Denominator cannot be zero.');
        }

        // Move sign to the numerator.
        if (bccomp($den, '0') < 0) {
            $num = bcmul($num, '-1');
            $den = bcmul($den, '-1');
        }

        if (bccomp($num, '0') === 0) {
            return new self('0', '1');
        }

        $g = self::gcd(self::abs($num), $den);
        return new self(bcdiv($num, $g, 0), bcdiv($den, $g, 0));
    }

    public static function fromInt(int $n): self
    {
        return self::of($n, 1);
    }

    /** Parse a decimal string like "3000.00", "10", or "0.10" exactly. */
    public static function fromDecimalString(string $s): self
    {
        $s = trim($s);
        $sign = '1';
        if (str_starts_with($s, '-')) { $sign = '-1'; $s = substr($s, 1); }
        if (str_starts_with($s, '+')) { $s = substr($s, 1); }

        if (!preg_match('/^\d*(\.\d*)?$/', $s) || $s === '' || $s === '.') {
            throw new InvalidArgumentException("Not a decimal number: {$s}");
        }

        $parts = explode('.', $s);
        $intPart = $parts[0] === '' ? '0' : $parts[0];
        $fracPart = $parts[1] ?? '';

        $num = bcmul(bcadd(bcmul($intPart, self::pow10(strlen($fracPart))), $fracPart === '' ? '0' : $fracPart), $sign);
        $den = self::pow10(strlen($fracPart));

        return self::of($num, $den);
    }

    public function add(self $o): self
    {
        return self::of(
            bcadd(bcmul($this->num, $o->den), bcmul($o->num, $this->den)),
            bcmul($this->den, $o->den),
        );
    }

    public function sub(self $o): self
    {
        return self::of(
            bcsub(bcmul($this->num, $o->den), bcmul($o->num, $this->den)),
            bcmul($this->den, $o->den),
        );
    }

    public function mul(self $o): self
    {
        return self::of(bcmul($this->num, $o->num), bcmul($this->den, $o->den));
    }

    public function div(self $o): self
    {
        if (bccomp($o->num, '0') === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }
        return self::of(bcmul($this->num, $o->den), bcmul($this->den, $o->num));
    }

    /** -1, 0, or 1 for this <, ==, > $o. Denominators are positive. */
    public function compareTo(self $o): int
    {
        return bccomp(bcmul($this->num, $o->den), bcmul($o->num, $this->den));
    }

    public function equals(self $o): bool
    {
        return $this->compareTo($o) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->num, '0') > 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->num, '0') === 0;
    }

    /** Largest integer <= value (floor). Assumes non-negative in engine use. */
    public function floorInt(): string
    {
        $q = bcdiv($this->num, $this->den, 0); // truncates toward zero
        // Correct toward negative infinity when there is a negative remainder.
        if (bccomp($this->num, '0') < 0 && bccomp(bcmul($q, $this->den), $this->num) !== 0) {
            $q = bcsub($q, '1');
        }
        return $q;
    }

    /** Round half-up to the nearest integer. Used for display only (minor units). */
    public function roundHalfUpInt(): string
    {
        $floor = $this->floorInt();
        $rem = bcsub($this->num, bcmul($floor, $this->den)); // 0 <= rem < den
        // rem/den >= 1/2  <=>  2*rem >= den
        return bccomp(bcmul($rem, '2'), $this->den) >= 0 ? bcadd($floor, '1') : $floor;
    }

    /**
     * Non-negative fractional-part numerator over a common comparison.
     * Returns [remNum, den] where fraction = remNum/den in [0,1). For largest-remainder.
     */
    public function fractionalRemainder(): array
    {
        $floor = $this->floorInt();
        $rem = bcsub($this->num, bcmul($floor, $this->den));
        return [$rem, $this->den];
    }

    public function toFloat(): float
    {
        return (float) bcdiv($this->num, $this->den, 20);
    }

    public function toString(): string
    {
        return $this->den === '1' ? $this->num : "{$this->num}/{$this->den}";
    }

    /** Serialise exactly as "num/den" (always includes the denominator) for storage. */
    public function toFractionString(): string
    {
        return "{$this->num}/{$this->den}";
    }

    /** Parse a "num/den" (or plain integer) fraction string produced by toFractionString(). */
    public static function fromFractionString(string $s): self
    {
        $s = trim($s);
        if (str_contains($s, '/')) {
            [$n, $d] = explode('/', $s, 2);
            return self::of($n, $d);
        }
        return self::of($s, '1');
    }

    // ---- integer helpers (BCMath, scale 0) --------------------------------

    private static function abs(string $n): string
    {
        return bccomp($n, '0') < 0 ? bcmul($n, '-1') : $n;
    }

    private static function gcd(string $a, string $b): string
    {
        while (bccomp($b, '0') !== 0) {
            [$a, $b] = [$b, bcmod($a, $b)];
        }
        return bccomp($a, '0') === 0 ? '1' : $a;
    }

    private static function pow10(int $n): string
    {
        return bcpow('10', (string) $n);
    }
}
