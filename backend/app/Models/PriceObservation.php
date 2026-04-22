<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'watchlist_id',
        'product_master_id',
        'supplier_id',
        'scrape_job_id',
        'source_name',
        'listing_title',
        'price',
        'currency',
        'base_unit',
        'quantity',
        'price_per_base_unit',
        'confidence_score',
        'is_anomaly',
        'anomaly_reason',
        'observed_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'decimal:3',
        'price_per_base_unit' => 'decimal:2',
        'confidence_score' => 'decimal:3',
        'is_anomaly' => 'boolean',
        'observed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(Watchlist::class);
    }

    public function productMaster(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scrapeJob(): BelongsTo
    {
        return $this->belongsTo(ScrapeJob::class);
    }
}
