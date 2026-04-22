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
        Schema::create('product_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_master_id')->constrained()->cascadeOnDelete();
            $table->string('source_name');
            $table->string('alias_text');
            $table->decimal('confidence', 4, 3)->default(1);
            $table->timestamps();

            $table->unique(['source_name', 'alias_text']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_aliases');
    }
};
