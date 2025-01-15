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

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Calculate total spending
    public function getTotalSpendingAttribute()
    {
        return $this->orders()->sum('total_amount');
    }

    // Get order count
    public function getOrdersCountAttribute()
    {
        return $this->orders()->count();
    }
}
