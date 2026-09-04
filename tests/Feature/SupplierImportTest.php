<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessSupplierImport;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SupplierImportTest extends TestCase
{
    use DatabaseMigrations;

    public function test_valid_request_queues_an_import(): void
    {
        Queue::fake();
        $supplier = Supplier::factory()->create();

        $response = $this->postJson($this->importUrl($supplier), [
            'offers' => [$this->offerPayload()],
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.status', ImportStatus::Queued->value);

        $importId = $response->json('data.id');

        $this->assertDatabaseHas('imports', [
            'id' => $importId,
            'supplier_id' => $supplier->id,
            'status' => ImportStatus::Queued->value,
        ]);

        Queue::assertPushed(ProcessSupplierImport::class, 1);
        Queue::assertPushed(ProcessSupplierImport::class, function (ProcessSupplierImport $job) use ($importId): bool {
            return $job->importId === $importId;
        });
    }

    public function test_invalid_request_does_not_create_an_import_or_dispatch_a_job(): void
    {
        Queue::fake();
        $supplier = Supplier::factory()->create();
        $payload = $this->offerPayload(['price_amount' => 0]);

        $this->postJson($this->importUrl($supplier), ['offers' => [$payload]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('offers.0.price_amount');

        $this->assertDatabaseCount('imports', 0);
        Queue::assertNothingPushed();
    }

    public function test_unknown_supplier_returns_not_found(): void
    {
        Queue::fake();

        $this->postJson('/api/suppliers/missing-supplier/imports', ['offers' => []])
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_duplicate_external_offer_ids_are_rejected(): void
    {
        Queue::fake();
        $supplier = Supplier::factory()->create();
        $offer = $this->offerPayload();

        $this->postJson($this->importUrl($supplier), ['offers' => [$offer, $offer]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('offers.1.external_offer_id');

        $this->assertDatabaseCount('imports', 0);
        Queue::assertNothingPushed();
    }

    public function test_empty_offer_import_completes_successfully(): void
    {
        Queue::fake();
        $supplier = Supplier::factory()->create();
        $response = $this->postJson($this->importUrl($supplier), ['offers' => []])
            ->assertAccepted();
        $import = Import::query()->findOrFail($response->json('data.id'));

        Queue::assertPushed(ProcessSupplierImport::class, 1);

        (new ProcessSupplierImport($import->id, []))->handle();

        $this->assertDatabaseCount('offers', 0);
        $this->assertDatabaseCount('properties', 0);
        $this->assertSame(ImportStatus::Completed, $import->refresh()->status);
    }

    public function test_job_creates_properties_and_offers_for_an_import(): void
    {
        $supplier = Supplier::factory()->create();
        $import = Import::factory()->for($supplier)->create([
            'status' => ImportStatus::Queued,
        ]);
        $payload = $this->offerPayload();

        (new ProcessSupplierImport($import->id, [$payload]))->handle();

        $this->assertDatabaseHas('properties', [
            'external_code' => $payload['property']['external_code'],
        ]);
        $this->assertDatabaseHas('offers', [
            'supplier_id' => $supplier->id,
            'external_offer_id' => $payload['external_offer_id'],
            'import_id' => $import->id,
        ]);
        $this->assertSame(ImportStatus::Completed, $import->refresh()->status);
        $this->assertNotNull($import->finished_at);
    }

    public function test_repeated_import_does_not_create_duplicate_offers(): void
    {
        $supplier = Supplier::factory()->create();
        $payload = $this->offerPayload();

        $firstImport = $this->queuedImport($supplier);
        (new ProcessSupplierImport($firstImport->id, [$payload]))->handle();

        $secondImport = $this->queuedImport($supplier);
        (new ProcessSupplierImport($secondImport->id, [$payload]))->handle();

        $this->assertDatabaseCount('offers', 1);
        $this->assertDatabaseHas('offers', [
            'supplier_id' => $supplier->id,
            'external_offer_id' => $payload['external_offer_id'],
            'import_id' => $secondImport->id,
        ]);
    }

    public function test_changed_offer_data_updates_the_existing_offer(): void
    {
        $supplier = Supplier::factory()->create();
        $payload = $this->offerPayload();

        $firstImport = $this->queuedImport($supplier);
        (new ProcessSupplierImport($firstImport->id, [$payload]))->handle();
        $offerId = Offer::query()->sole()->id;

        $secondImport = $this->queuedImport($supplier);
        $changedPayload = array_replace($payload, [
            'price_amount' => '199.99',
            'status' => 'unavailable',
        ]);
        (new ProcessSupplierImport($secondImport->id, [$changedPayload]))->handle();

        $this->assertDatabaseCount('offers', 1);
        $this->assertDatabaseHas('offers', [
            'id' => $offerId,
            'price_amount' => '199.99',
            'status' => 'unavailable',
            'import_id' => $secondImport->id,
        ]);
    }

    public function test_processing_failure_rolls_back_offer_changes_and_marks_the_import_failed(): void
    {
        $supplier = Supplier::factory()->create();
        $import = $this->queuedImport($supplier);

        $job = new class($import->id, []) extends ProcessSupplierImport
        {
            protected function processOffers(Import $import): void
            {
                Property::query()->create([
                    'external_code' => 'partial-property',
                    'name' => 'Partial Property',
                ]);

                throw new RuntimeException('Forced failure');
            }
        };

        try {
            $job->handle();
            $this->fail('The job should throw the forced processing exception.');
        } catch (RuntimeException) {
            // The Import record is updated outside the rolled-back transaction.
        }

        $this->assertDatabaseCount('properties', 0);
        $this->assertDatabaseCount('offers', 0);
        $this->assertSame(ImportStatus::Failed, $import->refresh()->status);
        $this->assertSame('Unable to process supplier offers.', $import->error_message);
        $this->assertNotNull($import->finished_at);
    }

    public function test_duplicate_delivery_of_a_job_is_harmless(): void
    {
        $supplier = Supplier::factory()->create();
        $import = $this->queuedImport($supplier);
        $payload = $this->offerPayload();

        (new ProcessSupplierImport($import->id, [$payload]))->handle();
        (new ProcessSupplierImport($import->id, [$payload]))->handle();

        $this->assertDatabaseCount('offers', 1);
        $this->assertSame(ImportStatus::Completed, $import->refresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function offerPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'external_offer_id' => 'offer-123',
            'property' => [
                'external_code' => 'property-123',
                'name' => 'Rome Central Hotel',
            ],
            'status' => 'available',
            'price_amount' => '129.50',
            'currency' => 'EUR',
            'check_in_date' => '2026-10-12',
            'check_out_date' => '2026-10-15',
            'valid_from' => '2026-09-04 10:00:00',
            'valid_until' => '2026-09-05 10:00:00',
        ], $overrides);
    }

    private function queuedImport(Supplier $supplier): Import
    {
        return Import::factory()->for($supplier)->create([
            'status' => ImportStatus::Queued,
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    private function importUrl(Supplier $supplier): string
    {
        return "/api/suppliers/{$supplier->slug}/imports";
    }
}
