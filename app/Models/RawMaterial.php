<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
        'price',
        'stock',
        'description',
        'is_active'
    ];

    /**
     * Get the material order items for the raw material.
     */
    public function materialOrderItems()
    {
        return $this->hasMany(MaterialOrderItem::class);
    }

    /**
     * Format price as currency
     *
     * @return string
     */
    public function getFormattedPriceAttribute()
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }
}
