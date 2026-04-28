<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Failed    = 'failed';
    case Refunded  = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pending',
            self::Paid      => 'Paid',
            self::Failed    => 'Failed',
            self::Refunded  => 'Refunded',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending   => 'bg-warning text-dark',
            self::Paid      => 'bg-success',
            self::Failed    => 'bg-danger',
            self::Refunded  => 'bg-secondary',
            self::Cancelled => 'bg-dark',
        };
    }
}
