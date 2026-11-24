<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FoodCategory;
use App\Models\FoodListing;
use Illuminate\Http\Request;

class FoodController extends Controller
{
    public function index(Request $request)
    {
        $query = FoodListing::available()
            ->with(['category', 'merchant.merchantProfile']);

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('category_id')) {
            $query->byCategory($request->category_id);
        }

        $foods = $query->latest()->paginate(20);

        return response()->json([
            'foods' => $foods->items(),
            'meta' => [
                'current_page' => $foods->currentPage(),
                'last_page' => $foods->lastPage(),
                'total' => $foods->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $food = FoodListing::with(['category', 'merchant.merchantProfile'])
            ->findOrFail($id);

        return response()->json([
            'food' => array_merge($food->toArray(), [
                'discount_percentage' => $food->discount_percentage,
                'is_expired' => $food->is_expired,
            ]),
        ]);
    }

    public function categories()
    {
        $categories = FoodCategory::withCount(['foodListings' => function ($q) {
            $q->available();
        }])->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function featured()
    {
        $foods = FoodListing::available()
            ->with(['category', 'merchant.merchantProfile'])
            ->orderByRaw('(original_price - discounted_price) / original_price DESC')
            ->limit(10)
            ->get();

        return response()->json([
            'foods' => $foods,
        ]);
    }
}
