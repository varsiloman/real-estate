<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();

        Supplier::query()->upsert([
            [
                'slug' => 'supplier-a',
                'name' => 'Supplier A',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'slug' => 'supplier-b',
                'name' => 'Supplier B',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ], ['slug'], ['name', 'updated_at']);
    }
}
