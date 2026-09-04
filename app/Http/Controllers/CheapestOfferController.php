<?php

namespace App\Http\Controllers;

use App\Enums\OfferStatus;
use App\Http\Requests\ListCheapestOffersRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CheapestOfferController extends Controller
{
    public function index(ListCheapestOffersRequest $request): JsonResponse
    {
        $checkInDate = $request->validated('check_in_date');
        $checkOutDate = $request->validated('check_out_date');
        $currency = $request->validated('currency');
        $now = now('UTC');

        $rankedOffers = DB::table('offers')
            ->select('offers.*')
            ->selectRaw(
                'ROW_NUMBER() OVER (
                    PARTITION BY offers.property_id
                    ORDER BY offers.price_amount ASC, offers.id ASC
                ) AS offer_rank'
            )
            ->where('offers.status', OfferStatus::Available->value)
            ->where(function ($query) use ($now): void {
                $query->whereNull('offers.valid_from')
                    ->orWhere('offers.valid_from', '<=', $now);
            })
            ->where('offers.valid_until', '>', $now)
            ->where('offers.check_in_date', $checkInDate)
            ->where('offers.check_out_date', $checkOutDate)
            ->where('offers.currency', $currency);

        $offers = DB::query()
            ->fromSub($rankedOffers, 'ranked_offers')
            ->join('properties', 'properties.id', '=', 'ranked_offers.property_id')
            ->join('suppliers', 'suppliers.id', '=', 'ranked_offers.supplier_id')
            ->where('ranked_offers.offer_rank', 1)
            ->orderBy('ranked_offers.property_id')
            ->select([
                'ranked_offers.id as offer_id',
                'ranked_offers.price_amount',
                'ranked_offers.currency',
                'ranked_offers.check_in_date',
                'ranked_offers.check_out_date',
                'ranked_offers.valid_from',
                'ranked_offers.valid_until',
                'properties.id as property_id',
                'properties.external_code as property_external_code',
                'properties.name as property_name',
                'suppliers.id as supplier_id',
                'suppliers.slug as supplier_slug',
                'suppliers.name as supplier_name',
            ])
            ->get();

        return response()->json([
            'data' => $offers->map(static fn (object $offer): array => [
                'id' => $offer->offer_id,
                'price_amount' => $offer->price_amount,
                'currency' => $offer->currency,
                'check_in_date' => $offer->check_in_date,
                'check_out_date' => $offer->check_out_date,
                'valid_from' => $offer->valid_from,
                'valid_until' => $offer->valid_until,
                'property' => [
                    'id' => $offer->property_id,
                    'external_code' => $offer->property_external_code,
                    'name' => $offer->property_name,
                ],
                'supplier' => [
                    'id' => $offer->supplier_id,
                    'slug' => $offer->supplier_slug,
                    'name' => $offer->supplier_name,
                ],
            ]),
        ]);
    }
}
