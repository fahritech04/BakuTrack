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
        Schema::create('price_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('watchlist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_master_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scrape_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_name');
            $table->string('listing_title');
            $table->decimal('price', 12, 2);
            $table->char('currency', 3)->default('IDR');
            $table->string('base_unit')->default('kg');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('price_per_base_unit', 12, 2);
            $table->decimal('confidence_score', 4, 3)->default(0.800);
            $table->boolean('is_anomaly')->default(false);
            $table->string('anomaly_reason')->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'observed_at']);
            $table->index(['tenant_id', 'product_master_id', 'observed_at']);
            $table->index(['supplier_id', 'observed_at']);
            $table->index(['is_anomaly', 'observed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_observations');
    }
};
