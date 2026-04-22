<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE watchlists ALTER COLUMN base_unit SET DEFAULT 'unit'");
        DB::statement("ALTER TABLE price_observations ALTER COLUMN base_unit SET DEFAULT 'unit'");
        DB::statement("ALTER TABLE product_masters ALTER COLUMN base_unit SET DEFAULT 'unit'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE watchlists ALTER COLUMN base_unit SET DEFAULT 'kg'");
        DB::statement("ALTER TABLE price_observations ALTER COLUMN base_unit SET DEFAULT 'kg'");
        DB::statement("ALTER TABLE product_masters ALTER COLUMN base_unit SET DEFAULT 'kg'");
    }
};
