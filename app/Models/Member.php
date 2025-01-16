<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone'
    ];

    // Relasi ke tabel orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Total spending dihitung dari jumlah total_amount di orders
    public function getTotalSpendingAttribute()
    {
        return $this->orders()->sum('total');
    }

    // Jumlah total order
    public function getTotalOrdersAttribute()
    {
        return $this->orders()->count();
    }

    // Format nomor telepon
    public function getFormattedPhoneAttribute()
    {
        $phone = $this->phone;
        if (strlen($phone) > 4) {
            return substr($phone, 0, 4) . '-' . substr($phone, 4);
        }
        return $phone;
    }
}
