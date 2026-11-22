<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Apakah status ini masih menahan slot. */
    public function holdsSlot(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu konfirmasi',
            self::Confirmed => 'Dikonfirmasi',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }
}