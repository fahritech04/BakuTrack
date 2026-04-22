<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'normalized_name',
        'category',
        'base_unit',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }
}
