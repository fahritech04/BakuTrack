<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'watchlist_id',
        'price_observation_id',
        'alert_type',
        'status',
        'message',
        'trigger_value',
        'baseline_value',
        'threshold_pct',
        'metadata',
        'triggered_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'trigger_value' => 'decimal:2',
        'baseline_value' => 'decimal:2',
        'threshold_pct' => 'decimal:2',
        'metadata' => 'array',
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(Watchlist::class);
    }

    public function priceObservation(): BelongsTo
    {
        return $this->belongsTo(PriceObservation::class);
    }
}
