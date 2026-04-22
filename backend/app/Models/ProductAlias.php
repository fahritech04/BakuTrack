<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_master_id',
        'source_name',
        'alias_text',
        'confidence',
    ];

    protected $casts = [
        'confidence' => 'decimal:3',
    ];

    public function productMaster(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class);
    }
}
