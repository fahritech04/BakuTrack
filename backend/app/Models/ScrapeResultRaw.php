<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapeResultRaw extends Model
{
    use HasFactory;

    protected $fillable = [
        'scrape_job_id',
        'tenant_id',
        'watchlist_id',
        'source_name',
        'listing_title',
        'listing_price_text',
        'listing_unit_text',
        'listing_url',
        'raw_payload',
        'scraped_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'scraped_at' => 'datetime',
    ];

    public function scrapeJob(): BelongsTo
    {
        return $this->belongsTo(ScrapeJob::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(Watchlist::class);
    }
}
