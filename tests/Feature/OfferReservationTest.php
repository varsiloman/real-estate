<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class OfferReservationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_successful_reservation_returns_created_and_makes_the_offer_unavailable(): void
    {
        $offer = $this->reservableOffer();

        $response = $this->postJson($this->reservationUrl($offer));

        $response
            ->assertCreated()
            ->assertJsonPath('data.offer_id', $offer->id)
            ->assertJsonStructure([
                'data' => ['id', 'offer_id', 'reserved_at'],
            ]);

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
        ]);
        $this->assertSame(OfferStatus::Unavailable, $offer->refresh()->status);
    }

    public function test_second_reservation_attempt_returns_conflict(): void
    {
        $offer = $this->reservableOffer();

        $this->postJson($this->reservationUrl($offer))->assertCreated();

        $this->postJson($this->reservationUrl($offer))->assertConflict();

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_unavailable_offer_returns_conflict(): void
    {
        $offer = $this->reservableOffer([
            'status' => OfferStatus::Unavailable,
        ]);

        $this->postJson($this->reservationUrl($offer))->assertConflict();

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_expired_offer_returns_gone(): void
    {
        $offer = $this->reservableOffer([
            'valid_until' => now('UTC')->subSecond(),
        ]);

        $this->postJson($this->reservationUrl($offer))->assertGone();

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_not_yet_valid_offer_returns_gone(): void
    {
        $offer = $this->reservableOffer([
            'valid_from' => now('UTC')->addMinute(),
        ]);

        $this->postJson($this->reservationUrl($offer))->assertGone();

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_nonexistent_offer_returns_not_found(): void
    {
        $this->postJson('/api/offers/999999/reservations')->assertNotFound();
    }

    public function test_database_uniqueness_prevents_duplicate_reservations(): void
    {
        $offer = $this->reservableOffer();

        Reservation::query()->create([
            'offer_id' => $offer->id,
            'reserved_at' => now('UTC'),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Reservation::query()->create([
            'offer_id' => $offer->id,
            'reserved_at' => now('UTC'),
        ]);
    }

    public function test_already_reserved_offer_returns_conflict_before_validity_is_checked(): void
    {
        $offer = $this->reservableOffer([
            'valid_until' => now('UTC')->subSecond(),
        ]);
        Reservation::query()->create([
            'offer_id' => $offer->id,
            'reserved_at' => now('UTC'),
        ]);

        $this->postJson($this->reservationUrl($offer))->assertConflict();
    }

    public function test_successfully_reserved_offer_is_excluded_from_cheapest_offer_results(): void
    {
        $offer = $this->reservableOffer([
            'currency' => 'EUR',
            'check_in_date' => '2026-10-12',
            'check_out_date' => '2026-10-15',
        ]);

        $this->postJson($this->reservationUrl($offer))->assertCreated();

        $this->getJson('/api/offers/cheapest?check_in_date=2026-10-12&check_out_date=2026-10-15&currency=EUR')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function reservableOffer(array $overrides = []): Offer
    {
        return Offer::factory()->create(array_replace([
            'status' => OfferStatus::Available,
            'valid_from' => now('UTC')->subMinute(),
            'valid_until' => now('UTC')->addMinute(),
        ], $overrides));
    }

    private function reservationUrl(Offer $offer): string
    {
        return "/api/offers/{$offer->id}/reservations";
    }
}
