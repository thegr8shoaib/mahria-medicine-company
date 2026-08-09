<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'generic_name', 'sku', 'barcode', 'price', 'cost_price', 'unit', 'low_stock_alert', 'is_active'])]
class Product extends Model
{
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class)->orderBy('expiry_date');
    }

    public function stockTotal(): int
    {
        return (int) $this->batches()->sum('quantity');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function getStatusAttribute(): string
    {
        $stock = $this->stockTotal();

        if ($stock <= 0) {
            return 'out_of_stock';
        }

        return $stock <= $this->low_stock_alert ? 'low_stock' : 'in_stock';
    }
}