<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'batch_number', 'quantity', 'expiry_date'])]
class Batch extends Model
{
    protected $casts = [
        'expiry_date' => 'datetime',
    ];
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function getStockAttribute(): int
    {
        return (int) $this->quantity;
    }
}