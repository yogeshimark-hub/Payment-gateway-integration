<?php

namespace App\Enums;

enum BillingType: string
{
    case Recurring = 'recurring';
    case OneTime   = 'one_time';

    public function label(): string
    {
        return match ($this) {
            self::Recurring => 'Recurring',
            self::OneTime   => 'One-time',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Recurring => 'bg-primary',
            self::OneTime   => 'bg-warning text-dark',
        };
    }
}
