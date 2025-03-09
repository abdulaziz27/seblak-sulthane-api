<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyCash extends Model
{
    use HasFactory;

    protected $table = 'daily_cash';

    protected $fillable = [
        'outlet_id',
        'user_id',
        'date',
        'opening_balance',
        'expenses',
        'expenses_note',
    ];

    protected $casts = [
        'date' => 'date',
        'opening_balance' => 'integer',
        'expenses' => 'integer',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
