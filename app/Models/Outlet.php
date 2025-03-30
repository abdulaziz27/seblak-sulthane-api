<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Outlet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address1',
        'address2',
        'phone',
        'leader',
        'notes',
        'is_warehouse'
    ];

    protected $dates = ['deleted_at'];


    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
