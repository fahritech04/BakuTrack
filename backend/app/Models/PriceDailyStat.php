<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceDailyStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_master_id',
        'watchlist_id',
        'stat_date',
        'min_price',
        'max_price',
        'avg_price',
        'median_price',
        'sample_size',
    ];

    protected $casts = [
        'stat_date' => 'date',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'avg_price' => 'decimal:2',
        'median_price' => 'decimal:2',
    ];
}
