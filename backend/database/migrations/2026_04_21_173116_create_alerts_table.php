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
        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('watchlist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_observation_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('alert_type', ['price_drop', 'arbitrage', 'anomaly_block']);
            $table->enum('status', ['open', 'acknowledged', 'sent'])->default('open');
            $table->text('message');
            $table->decimal('trigger_value', 12, 2)->nullable();
            $table->decimal('baseline_value', 12, 2)->nullable();
            $table->decimal('threshold_pct', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'triggered_at']);
            $table->index(['tenant_id', 'alert_type', 'triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
