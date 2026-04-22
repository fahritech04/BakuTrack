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
        Schema::table('scrape_result_raws', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'watchlist_id', 'source_name', 'scraped_at'],
                'scrape_result_raws_tenant_watchlist_source_scraped_idx'
            );
        });

        Schema::table('price_observations', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'watchlist_id', 'source_name', 'observed_at'],
                'price_observations_tenant_watchlist_source_observed_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_observations', function (Blueprint $table): void {
            $table->dropIndex('price_observations_tenant_watchlist_source_observed_idx');
        });

        Schema::table('scrape_result_raws', function (Blueprint $table): void {
            $table->dropIndex('scrape_result_raws_tenant_watchlist_source_scraped_idx');
        });
    }
};
