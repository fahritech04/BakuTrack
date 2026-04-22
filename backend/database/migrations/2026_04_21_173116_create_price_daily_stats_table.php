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
        Schema::create('price_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_master_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('watchlist_id')->nullable()->constrained()->nullOnDelete();
            $table->date('stat_date');
            $table->decimal('min_price', 12, 2);
            $table->decimal('max_price', 12, 2);
            $table->decimal('avg_price', 12, 2);
            $table->decimal('median_price', 12, 2)->nullable();
            $table->unsignedInteger('sample_size')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'stat_date']);
            $table->unique(['tenant_id', 'watchlist_id', 'stat_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_daily_stats');
    }
};
