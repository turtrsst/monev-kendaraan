<?php

declare(strict_types=1);

namespace App\Services;

/** Pure transition map used only by TripService and lifecycle unit tests. */
final class TripStateMachine
{
    private const TRANSITIONS = [
        'ASSIGNED' => 'READY',
        'READY' => 'STARTED',
        'STARTED' => 'ARRIVED',
        'ARRIVED' => 'RETURNING',
        'RETURNING' => 'COMPLETED',
        'COMPLETED' => 'SUBMITTED',
    ];

    public static function next(string $current): ?string
    {
        return self::TRANSITIONS[$current] ?? null;
    }

    public static function allows(string $current, string $target): bool
    {
        return self::next($current) === $target;
    }

    /** @return array<string,string> */
    public static function transitions(): array
    {
        return self::TRANSITIONS;
    }
}
