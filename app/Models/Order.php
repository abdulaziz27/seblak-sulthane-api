<?php

namespace App\Models;

use Carbon\Carbon;
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
        'order_type',
        'qris_fee',
        'notes'
    ];

    protected $casts = [
        'qris_fee' => 'decimal:2',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class)->withTrashed();
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    protected static function booted()
    {
        static::creating(function ($order) {
            if (!empty($order->transaction_time)) {
                $transactionTime = Carbon::parse($order->transaction_time);
                $order->created_at = $transactionTime;
                $order->updated_at = $transactionTime;
            }
        });
    }
}
