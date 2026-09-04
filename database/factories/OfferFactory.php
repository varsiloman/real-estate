<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        $checkInDate = fake()->dateTimeBetween('+7 days', '+3 months')->setTime(0, 0);

        return [
            'supplier_id' => Supplier::factory(),
            'property_id' => Property::factory(),
            'import_id' => null,
            'external_offer_id' => fake()->unique()->bothify('offer-########'),
            'status' => OfferStatus::Available,
            'price_amount' => fake()->randomFloat(2, 50, 2500),
            'currency' => fake()->randomElement(['EUR', 'GBP', 'USD']),
            'check_in_date' => $checkInDate->format('Y-m-d'),
            'check_out_date' => (clone $checkInDate)->modify('+'.fake()->numberBetween(1, 14).' days')->format('Y-m-d'),
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
        ];
    }
}
