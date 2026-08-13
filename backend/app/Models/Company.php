<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'distributor_id'])]
class Company extends Model
{
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'distributor_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}