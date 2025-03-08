<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyCash extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang sesuai
     */
    protected $table = 'daily_cash';

    /**
     * Atribut yang dapat diisi (mass assignable)
     */
    protected $fillable = [
        'outlet_id',
        'user_id',
        'date',
        'opening_balance',
        'expenses',
        'expenses_note',
    ];

    /**
     * Pengaturan tipe data
     */
    protected $casts = [
        'date' => 'date',
        'opening_balance' => 'decimal:2',
        'expenses' => 'decimal:2',
    ];

    /**
     * Relasi ke outlet
     */
    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Relasi ke user yang membuat record
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mendapatkan saldo akhir (closing balance)
     */
    public function getClosingBalanceAttribute()
    {
        $cashSales = Order::where('outlet_id', $this->outlet_id)
            ->whereDate('created_at', $this->date)
            ->where('payment_method', 'cash')
            ->sum('total');

        return $this->opening_balance + $cashSales - $this->expenses;
    }
}
