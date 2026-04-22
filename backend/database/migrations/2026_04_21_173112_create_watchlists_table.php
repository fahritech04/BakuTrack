<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('watchlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_master_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_product_name')->nullable();
            $table->decimal('target_price', 12, 2)->nullable();
            $table->decimal('drop_threshold_pct', 5, 2)->default(10);
            $table->decimal('arbitrage_threshold_pct', 5, 2)->default(15);
            $table->string('base_unit')->default('kg');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watchlists');
    }
};
