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
        Schema::create('scrape_result_raws', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scrape_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('watchlist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_name');
            $table->string('listing_title');
            $table->string('listing_price_text');
            $table->string('listing_unit_text')->nullable();
            $table->text('listing_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('scraped_at');
            $table->timestamps();

            $table->index(['tenant_id', 'scraped_at']);
            $table->index(['source_name', 'scraped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_result_raws');
    }
};
