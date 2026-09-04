<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory()->state([
                'status' => OfferStatus::Unavailable,
            ]),
            'reserved_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ];
    }
}
