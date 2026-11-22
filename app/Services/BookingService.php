<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\SlotAlreadyBooked;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

final class BookingService
{
    public function create(User $user, Court $court, string $date, int $hour): Booking
    {
        $slotStart = Carbon::parse($date)->setTime($hour, 0);
        $playDate = $slotStart->toDateString();   // normalisasi: "2026-12-1" jadi "2026-12-01"

        $this->assertBookable($court, $slotStart, $hour);

        try {
            return Booking::create([
                'user_id' => $user->id,
                'court_id' => $court->id,
                'play_date' => $playDate,
                'start_hour' => $hour,
                'price' => $court->price_per_hour,
                'status' => BookingStatus::Pending,
                'active_slot' => sprintf('%d|%s|%d', $court->id, $playDate, $hour),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new SlotAlreadyBooked();
        }
    }

    public function confirm(Booking $booking): void
    {
        $this->transition($booking, [BookingStatus::Pending], BookingStatus::Confirmed, releaseSlot: false);
    }

    public function reject(Booking $booking): void
    {
        $this->transition($booking, [BookingStatus::Pending], BookingStatus::Rejected, releaseSlot: true);
    }

    public function cancel(Booking $booking): void
    {
        $this->transition(
            $booking,
            [BookingStatus::Pending, BookingStatus::Confirmed],
            BookingStatus::Cancelled,
            releaseSlot: true,
        );
    }

    private function assertBookable(Court $court, Carbon $slotStart, int $hour): void
    {
        if (! $court->is_active) {
            throw new DomainException('Lapangan sedang tidak tersedia.');
        }

        if ($hour < config('booking.open_hour') || $hour >= config('booking.close_hour')) {
            throw new DomainException('Jam di luar jam operasional.');
        }

        if ($slotStart->isPast()) {
            throw new DomainException('Tidak bisa memesan slot yang sudah lewat.');
        }
    }

    /**
     * Ubah status secara atomik: UPDATE hanya berhasil jika status di database
     * masih salah satu dari $from. Aman terhadap dua request bersamaan.
     *
     * @param  list<BookingStatus>  $from
     */
    private function transition(Booking $booking, array $from, BookingStatus $to, bool $releaseSlot): void
    {
        $changes = ['status' => $to->value];

        if ($releaseSlot) {
            $changes['active_slot'] = null;   // slot dilepas, bisa dipesan lagi
        }

        $updated = Booking::whereKey($booking->getKey())
            ->whereIn('status', array_map(fn (BookingStatus $s) => $s->value, $from))
            ->update($changes);

        if ($updated === 0) {
            throw new DomainException("Booking tidak dapat diubah menjadi \"{$to->label()}\" dari status saat ini.");
        }

        $booking->refresh();
    }
}