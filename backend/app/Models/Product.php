<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'company', 'company_id', 'generic_name', 'category', 'variants', 'sku', 'barcode', 'price', 'trade_price', 'cost_price', 'unit', 'items_per_pack', 'low_stock_alert', 'is_active'])]
class Product extends Model
{
    protected $appends = ['is_pack', 'sale_unit'];

    public function getIsPackAttribute(): bool
    {
        return (int) ($this->items_per_pack ?? 0) > 0;
    }

    public function getSaleUnitAttribute(): string
    {
        return $this->is_pack ? 'item' : (string) $this->unit;
    }

    public function packQuantity(int $packs): int
    {
        return $packs * (int) ($this->items_per_pack ?? 1);
    }
    public function companyModel(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
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