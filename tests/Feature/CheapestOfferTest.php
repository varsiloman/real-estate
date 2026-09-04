<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class CheapestOfferTest extends TestCase
{
    use DatabaseMigrations;

    public function test_returns_the_cheapest_current_offer_per_property_across_suppliers(): void
    {
        $property = Property::factory()->create();
        $secondProperty = Property::factory()->create();
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();
        $moreExpensiveOffer = $this->currentOffer($property, $supplierA, [
            'price_amount' => '150.00',
        ]);
        $cheapestOffer = $this->currentOffer($property, $supplierB, [
            'price_amount' => '100.00',
        ]);
        $secondPropertyOffer = $this->currentOffer($secondProperty, $supplierA, [
            'price_amount' => '120.00',
        ]);

        $response = $this->getJson($this->endpoint());

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $cheapestOffer->id)
            ->assertJsonPath('data.0.price_amount', '100.00')
            ->assertJsonPath('data.0.property.id', $property->id)
            ->assertJsonPath('data.0.supplier.id', $supplierB->id)
            ->assertJsonPath('data.1.id', $secondPropertyOffer->id)
            ->assertJsonPath('data.1.property.id', $secondProperty->id);

        $this->assertNotSame($moreExpensiveOffer->id, $response->json('data.0.id'));
    }

    public function test_excludes_expired_not_yet_valid_and_unavailable_offers(): void
    {
        $property = Property::factory()->create();
        $supplier = Supplier::factory()->create();
        $currentOffer = $this->currentOffer($property, $supplier, [
            'price_amount' => '200.00',
        ]);

        $this->currentOffer($property, $supplier, [
            'price_amount' => '50.00',
            'valid_until' => now('UTC')->subSecond(),
        ]);
        $this->currentOffer($property, $supplier, [
            'price_amount' => '60.00',
            'valid_from' => now('UTC')->addSecond(),
        ]);
        $this->currentOffer($property, $supplier, [
            'price_amount' => '70.00',
            'status' => OfferStatus::Unavailable,
        ]);

        $this->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $currentOffer->id);
    }

    public function test_excludes_offers_for_other_stays_currencies_and_properties_without_matches(): void
    {
        $matchingProperty = Property::factory()->create();
        $otherStayProperty = Property::factory()->create();
        $otherCurrencyProperty = Property::factory()->create();
        $supplier = Supplier::factory()->create();
        $matchingOffer = $this->currentOffer($matchingProperty, $supplier);

        $this->currentOffer($otherStayProperty, $supplier, [
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-03',
        ]);
        $this->currentOffer($otherCurrencyProperty, $supplier, [
            'currency' => 'USD',
        ]);

        $response = $this->getJson($this->endpoint());

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingOffer->id)
            ->assertJsonPath('data.0.property.id', $matchingProperty->id);
    }

    public function test_equal_price_ties_are_resolved_by_lowest_offer_id(): void
    {
        $property = Property::factory()->create();
        $supplier = Supplier::factory()->create();
        $firstOffer = $this->currentOffer($property, $supplier, [
            'price_amount' => '100.00',
        ]);
        $this->currentOffer($property, $supplier, [
            'price_amount' => '100.00',
        ]);

        $this->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstOffer->id);
    }

    public function test_invalid_query_parameters_are_rejected(): void
    {
        $this->getJson('/api/offers/cheapest')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_in_date', 'check_out_date', 'currency']);

        $this->getJson('/api/offers/cheapest?check_in_date=2026-10-15&check_out_date=2026-10-12&currency=eu')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_in_date', 'currency']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function currentOffer(Property $property, Supplier $supplier, array $overrides = []): Offer
    {
        return Offer::factory()
            ->for($property)
            ->for($supplier)
            ->create(array_replace([
                'status' => OfferStatus::Available,
                'price_amount' => '125.00',
                'currency' => 'EUR',
                'check_in_date' => '2026-10-12',
                'check_out_date' => '2026-10-15',
                'valid_from' => now('UTC')->subMinute(),
                'valid_until' => now('UTC')->addMinute(),
            ], $overrides));
    }

    private function endpoint(): string
    {
        return '/api/offers/cheapest?check_in_date=2026-10-12&check_out_date=2026-10-15&currency=eur';
    }
}
