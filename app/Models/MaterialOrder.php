<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'user_id',
        'status',
        'total_amount',
        'notes',
        'approved_at',
        'delivered_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Get the franchise/outlet that owns the material order.
     */
    public function franchise()
    {
        return $this->belongsTo(Outlet::class, 'franchise_id');
    }

    /**
     * Get the user that created the material order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items for the material order.
     */
    public function items()
    {
        return $this->hasMany(MaterialOrderItem::class);
    }

    /**
     * Format total amount as currency
     *
     * @return string
     */
    public function getFormattedTotalAttribute()
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    /**
     * Get the status badge HTML
     *
     * @return string
     */
    public function getStatusBadgeAttribute()
    {
        $colors = [
            'pending' => 'warning',
            'approved' => 'info',
            'delivered' => 'success',
        ];

        return '<div class="badge badge-' . $colors[$this->status] . '">' . ucfirst($this->status) . '</div>';
    }
}
