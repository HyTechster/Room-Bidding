<?php

namespace App\Support;

/**
 * Minimal currency helper: code to symbol, and money formatting from minor units.
 * MYR (RM) is the default and listed first.
 */
class Currency
{
    public const MAP = [
        'MYR' => 'RM',
        'SGD' => 'S$',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'IDR' => 'Rp',
        'THB' => '฿',
        'PHP' => '₱',
        'INR' => '₹',
        'AUD' => 'A$',
    ];

    public static function symbol(string $code): string
    {
        return self::MAP[$code] ?? $code;
    }

    /** Format minor units (cents) as "RM 1,234.56". */
    public static function format(int $cents, string $code): string
    {
        return self::symbol($code).' '.number_format($cents / 100, 2);
    }

    /** @return array<string,string> code => symbol, for a selector */
    public static function options(): array
    {
        return self::MAP;
    }
}
