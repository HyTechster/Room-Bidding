<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

/**
 * Room status colour, evaluated per room from occupancy n vs capacity C (spec 3.4).
 *   Green  : n == C  (exactly full)
 *   Yellow : n <  C  (under-filled — fine when tenants < total capacity)
 *   Red    : n >  C  (over-subscribed — blocking)
 */
enum Colour: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    public static function determine(int $occupancy, int $capacity): self
    {
        return match (true) {
            $occupancy > $capacity  => self::Red,
            $occupancy === $capacity => self::Green,
            default                 => self::Yellow,
        };
    }

    public function isRed(): bool
    {
        return $this === self::Red;
    }
}
