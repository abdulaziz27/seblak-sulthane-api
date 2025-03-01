<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_order_id',
        'raw_material_id',
        'quantity',
        'price_per_unit',
        'subtotal'
    ];

    /**
     * Get the material order that owns the item.
     */
    public function materialOrder()
    {
        return $this->belongsTo(MaterialOrder::class);
    }

    /**
     * Get the raw material for this item.
     */
    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    /**
     * Format price per unit as currency
     *
     * @return string
     */
    public function getFormattedPriceAttribute()
    {
        return 'Rp ' . number_format($this->price_per_unit, 0, ',', '.');
    }

    /**
     * Format subtotal as currency
     *
     * @return string
     */
    public function getFormattedSubtotalAttribute()
    {
        return 'Rp ' . number_format($this->subtotal, 0, ',', '.');
    }
}
