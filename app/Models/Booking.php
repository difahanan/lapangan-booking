<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'court_id', 'play_date', 'start_hour', 'price', 'status', 'active_slot'])]
class Booking extends Model
{
    protected function casts(): array
    {
        return [
            'play_date' => 'date',
            'start_hour' => 'integer',
            'price' => 'integer',
            'status' => BookingStatus::class,
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}