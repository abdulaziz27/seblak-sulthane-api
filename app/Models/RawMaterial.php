<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'unit',
        'price', // Harga jual ke outlet
        'purchase_price', // Harga beli dari supplier
        'stock',
        'reserved_stock',
        'description',
        'is_active'
    ];

    protected $dates = ['deleted_at'];

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

    /**
     * Format purchase price as currency
     *
     * @return string
     */
    public function getFormattedPurchasePriceAttribute()
    {
        return 'Rp ' . number_format($this->purchase_price, 0, ',', '.');
    }

    /**
     * Get margin amount (price - purchase_price)
     *
     * @return int
     */
    public function getMarginAmountAttribute()
    {
        return $this->price - $this->purchase_price;
    }

    /**
     * Get margin percentage
     *
     * @return float
     */
    public function getMarginPercentageAttribute()
    {
        if ($this->purchase_price <= 0) {
            return 0;
        }

        return round((($this->price - $this->purchase_price) / $this->purchase_price) * 100, 2);
    }

    public function recordStockAdjustment($quantity, $purchasePrice = null, $type = 'purchase', $notes = '')
    {
        return StockAdjustment::create([
            'raw_material_id' => $this->id,
            'quantity' => $quantity,
            'purchase_price' => $type === 'purchase' ? ($purchasePrice ?? $this->purchase_price) : null,
            'adjustment_date' => now(),
            'adjustment_type' => $type,
            'notes' => $notes,
            'user_id' => Auth::id()
        ]);
    }

    /**
     * Get available stock (total stock minus reserved stock)
     *
     * @return int
     */
    public function getAvailableStockAttribute()
    {
        return $this->stock - $this->reserved_stock;
    }
}
