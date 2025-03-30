<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_material_id',
        'quantity',
        'purchase_price',
        'adjustment_date',
        'adjustment_type',
        'notes',
        'user_id'
    ];

    protected $casts = [
        'adjustment_date' => 'date',
    ];

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
