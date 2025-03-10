<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_amount',
        'sub_total',
        'tax',
        'discount',
        'discount_amount',
        'service_charge',
        'total',
        'payment_method',
        'total_item',
        'id_kasir',
        'nama_kasir',
        'transaction_time',
        'outlet_id',
        'member_id',
        'order_type'
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
