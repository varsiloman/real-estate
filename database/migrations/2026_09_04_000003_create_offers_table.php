<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('external_offer_id');
            $table->string('status', 20);
            $table->decimal('price_amount', 12, 2);
            $table->char('currency', 3);
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until');
            $table->timestamps();

            $table->unique(['supplier_id', 'external_offer_id']);
            $table->index('property_id');
            $table->index(['status', 'valid_until']);
            $table->index(['check_in_date', 'check_out_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
