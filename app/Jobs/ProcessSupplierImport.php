<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Enums\OfferStatus;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessSupplierImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    public function __construct(
        public int $importId,
        public array $offers,
    ) {}

    public function handle(): void
    {
        $timestamp = now();

        $claimed = Import::query()
            ->whereKey($this->importId)
            ->where('status', ImportStatus::Queued->value)
            ->update([
                'status' => ImportStatus::Processing->value,
                'started_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

        if ($claimed === 0) {
            return;
        }

        $import = Import::query()->findOrFail($this->importId);

        try {
            DB::transaction(fn () => $this->processOffers($import));

            $import->forceFill([
                'status' => ImportStatus::Completed,
                'finished_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            Import::query()
                ->whereKey($this->importId)
                ->where('status', ImportStatus::Processing->value)
                ->update([
                    'status' => ImportStatus::Failed->value,
                    'error_message' => 'Unable to process supplier offers.',
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    protected function processOffers(Import $import): void
    {
        if ($this->offers === []) {
            return;
        }

        $timestamp = now();
        $properties = [];
        $externalOfferIds = array_column($this->offers, 'external_offer_id');

        $existingOffers = Offer::query()
            ->where('supplier_id', $import->supplier_id)
            ->whereIn('external_offer_id', $externalOfferIds)
            ->lockForUpdate()
            ->get(['id', 'external_offer_id'])
            ->keyBy('external_offer_id');

        $reservedOfferIds = Reservation::query()
            ->whereIn('offer_id', $existingOffers->pluck('id'))
            ->pluck('offer_id')
            ->flip();

        foreach ($this->offers as $offer) {
            $externalCode = $offer['property']['external_code'];

            $properties[$externalCode] = [
                'external_code' => $externalCode,
                'name' => $offer['property']['name'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        Property::query()->upsert(
            array_values($properties),
            ['external_code'],
            ['name', 'updated_at'],
        );

        $propertyIds = Property::query()
            ->whereIn('external_code', array_keys($properties))
            ->pluck('id', 'external_code');

        $offers = array_map(function (array $offer) use ($import, $propertyIds, $timestamp, $existingOffers, $reservedOfferIds): array {
            $existingOffer = $existingOffers->get($offer['external_offer_id']);
            $isReserved = $existingOffer !== null && $reservedOfferIds->has($existingOffer->id);

            return [
                'supplier_id' => $import->supplier_id,
                'property_id' => $propertyIds[$offer['property']['external_code']],
                'import_id' => $import->id,
                'external_offer_id' => $offer['external_offer_id'],
                'status' => $isReserved ? OfferStatus::Unavailable->value : $offer['status'],
                'price_amount' => $offer['price_amount'],
                'currency' => $offer['currency'],
                'check_in_date' => $offer['check_in_date'],
                'check_out_date' => $offer['check_out_date'],
                'valid_from' => isset($offer['valid_from']) ? Carbon::parse($offer['valid_from']) : null,
                'valid_until' => Carbon::parse($offer['valid_until']),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, $this->offers);

        Offer::query()->upsert(
            $offers,
            ['supplier_id', 'external_offer_id'],
            [
                'property_id',
                'import_id',
                'status',
                'price_amount',
                'currency',
                'check_in_date',
                'check_out_date',
                'valid_from',
                'valid_until',
                'updated_at',
            ],
        );
    }
}
