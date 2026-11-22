<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\SlotAlreadyBooked;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Services\BookingService;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BookingService::class);
        $this->date = now()->addDay()->toDateString();
    }

    private function court(int $price = 100_000): Court
    {
        return Court::create([
            'name' => 'Lapangan A',
            'sport' => 'futsal',
            'price_per_hour' => $price,
            'is_active' => true,
        ]);
    }

    public function test_new_booking_is_pending_and_holds_the_slot(): void
    {
        $court = $this->court();

        $booking = $this->service->create(User::factory()->create(), $court, $this->date, 19);

        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame("{$court->id}|{$this->date}|19", $booking->active_slot);
        $this->assertSame(100_000, $booking->price);
    }

    public function test_same_slot_cannot_be_booked_twice(): void
    {
        $court = $this->court();
        $this->service->create(User::factory()->create(), $court, $this->date, 19);

        $this->assertThrows(
            fn () => $this->service->create(User::factory()->create(), $court, $this->date, 19),
            SlotAlreadyBooked::class,
        );
        $this->assertSame(1, Booking::count());
    }

    public function test_cancelled_slot_can_be_booked_again(): void
    {
        $court = $this->court();
        $first = $this->service->create(User::factory()->create(), $court, $this->date, 19);
        $this->service->cancel($first);

        $again = $this->service->create(User::factory()->create(), $court, $this->date, 19);

        $this->assertSame(BookingStatus::Pending, $again->status);
        $this->assertNull($first->fresh()->active_slot);
        $this->assertSame(2, Booking::count());
    }

    public function test_rejected_slot_can_be_booked_again(): void
    {
        $court = $this->court();
        $first = $this->service->create(User::factory()->create(), $court, $this->date, 19);
        $this->service->reject($first);

        $this->service->create(User::factory()->create(), $court, $this->date, 19);

        $this->assertSame(2, Booking::count());
    }

    public function test_different_court_date_or_hour_is_allowed(): void
    {
        $a = $this->court();
        $b = $this->court();
        $user = User::factory()->create();

        $this->service->create($user, $a, $this->date, 19);
        $this->service->create($user, $b, $this->date, 19);                        // lapangan lain
        $this->service->create($user, $a, $this->date, 20);                        // jam lain
        $this->service->create($user, $a, now()->addDays(2)->toDateString(), 19);  // tanggal lain

        $this->assertSame(4, Booking::count());
    }

    public function test_past_slot_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2030-01-10 20:30:00'));
        $court = $this->court();
        $user = User::factory()->create();

        $this->assertThrows(
            fn () => $this->service->create($user, $court, '2030-01-10', 19),
            DomainException::class,
        );

        $this->service->create($user, $court, '2030-01-10', 21);   // slot berikutnya masih boleh
        $this->assertSame(1, Booking::count());
    }

    public function test_hours_outside_operating_range_are_rejected(): void
    {
        $court = $this->court();
        $user = User::factory()->create();

        foreach ([config('booking.open_hour') - 1, config('booking.close_hour')] as $hour) {
            $this->assertThrows(
                fn () => $this->service->create($user, $court, $this->date, $hour),
                DomainException::class,
            );
        }

        $this->assertSame(0, Booking::count());
    }

    public function test_boundary_hours_are_accepted(): void
    {
        $court = $this->court();
        $user = User::factory()->create();

        $this->service->create($user, $court, $this->date, config('booking.open_hour'));
        $this->service->create($user, $court, $this->date, config('booking.close_hour') - 1);

        $this->assertSame(2, Booking::count());
    }

    public function test_inactive_court_cannot_be_booked(): void
    {
        $court = $this->court();
        $court->update(['is_active' => false]);

        $this->assertThrows(
            fn () => $this->service->create(User::factory()->create(), $court, $this->date, 19),
            DomainException::class,
        );
    }

    public function test_price_is_a_snapshot(): void
    {
        $court = $this->court(100_000);
        $booking = $this->service->create(User::factory()->create(), $court, $this->date, 19);

        $court->update(['price_per_hour' => 150_000]);

        $this->assertSame(100_000, $booking->fresh()->price);
    }

    public function test_only_pending_booking_can_be_confirmed(): void
    {
        $booking = $this->service->create(User::factory()->create(), $this->court(), $this->date, 19);

        $this->service->confirm($booking);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNotNull($booking->active_slot);   // yang dikonfirmasi tetap menahan slot
        $this->assertThrows(fn () => $this->service->confirm($booking), DomainException::class);
    }

    public function test_stale_instance_cannot_override_current_status(): void
    {
        $booking = $this->service->create(User::factory()->create(), $this->court(), $this->date, 19);
        $stale = Booking::find($booking->id);   // salinan lama, status masih pending

        $this->service->cancel($booking);

        $this->assertThrows(fn () => $this->service->confirm($stale), DomainException::class);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_database_enforces_unique_active_slot_but_allows_many_nulls(): void
    {
        $court = $this->court();
        $user = User::factory()->create();

        $insert = fn (?string $slot) => Booking::create([
            'user_id' => $user->id,
            'court_id' => $court->id,
            'play_date' => $this->date,
            'start_hour' => 19,
            'price' => 100_000,
            'status' => BookingStatus::Cancelled,
            'active_slot' => $slot,
        ]);

        $insert(null);       // riwayat batal boleh banyak
        $insert(null);
        $insert('slot-x');

        $this->assertThrows(fn () => $insert('slot-x'), UniqueConstraintViolationException::class);
    }
}