<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'status' => ImportStatus::Completed,
            'error_message' => null,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }
}
