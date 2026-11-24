<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodCategory extends Model
{
    protected $fillable = ['name', 'icon'];

    public function foodListings(): HasMany
    {
        return $this->hasMany(FoodListing::class, 'category_id');
    }
}
