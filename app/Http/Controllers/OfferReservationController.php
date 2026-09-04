<?php

namespace App\Http\Controllers;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OfferReservationController extends Controller
{
    public function store(string $offer): JsonResponse
    {
        try {
            return DB::transaction(function () use ($offer): JsonResponse {
                $reservationOffer = Offer::query()
                    ->lockForUpdate()
                    ->find($offer);

                if ($reservationOffer === null) {
                    return response()->json([
                        'message' => 'Offer not found.',
                    ], Response::HTTP_NOT_FOUND);
                }

                if ($reservationOffer->status !== OfferStatus::Available || $reservationOffer->reservation()->exists()) {
                    return response()->json([
                        'message' => 'Offer is no longer available.',
                    ], Response::HTTP_CONFLICT);
                }

                $now = now('UTC');

                if (
                    ($reservationOffer->valid_from !== null && $reservationOffer->valid_from->gt($now))
                    || $reservationOffer->valid_until->lte($now)
                ) {
                    return response()->json([
                        'message' => 'Offer is no longer valid.',
                    ], Response::HTTP_GONE);
                }

                $reservation = Reservation::query()->create([
                    'offer_id' => $reservationOffer->id,
                    'reserved_at' => $now,
                ]);

                $reservationOffer->update([
                    'status' => OfferStatus::Unavailable,
                ]);

                return response()->json([
                    'data' => [
                        'id' => $reservation->id,
                        'offer_id' => $reservation->offer_id,
                        'reserved_at' => $reservation->reserved_at->toISOString(),
                    ],
                ], Response::HTTP_CREATED);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Offer is no longer available.',
            ], Response::HTTP_CONFLICT);
        }
    }
}
