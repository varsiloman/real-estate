<?php

namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offer extends Model
{
    protected $fillable = [
        'supplier_id',
        'property_id',
        'import_id',
        'external_offer_id',
        'status',
        'price_amount',
        'currency',
        'check_in_date',
        'check_out_date',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'price_amount' => 'decimal:2',
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class);
    }
}
