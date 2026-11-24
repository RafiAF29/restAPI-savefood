<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodListing extends Model
{
    protected $fillable = [
        'merchant_id',
        'category_id',
        'name',
        'description',
        'original_price',
        'discounted_price',
        'stock',
        'image',
        'allergen_info',
        'pickup_deadline',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'discounted_price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'pickup_deadline' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FoodCategory::class, 'category_id');
    }

    public function merchantProfile()
    {
        return $this->hasOneThrough(
            MerchantProfile::class,
            User::class,
            'id',
            'user_id',
            'merchant_id',
            'id'
        );
    }

    public function getDiscountPercentageAttribute(): int
    {
        if ($this->original_price <= 0) return 0;
        return (int) round((1 - $this->discounted_price / $this->original_price) * 100);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->pickup_deadline->isPast();
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where('stock', '>', 0)
            ->where('pickup_deadline', '>', now());
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'like', "%{$term}%");
    }
}
