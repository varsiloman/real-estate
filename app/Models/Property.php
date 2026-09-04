<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    protected $fillable = [
        'external_code',
        'name',
    ];

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
