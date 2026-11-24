<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_id',
        'merchant_id',
        'status',
        'total_amount',
        'notes',
        'rejection_reason',
        'pickup_time',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'pickup_time' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public static function generateOrderNumber(): string
    {
        return 'SF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
}
