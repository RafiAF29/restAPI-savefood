<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FoodCategory;
use App\Models\FoodListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MerchantController extends Controller
{
    /**
     * Dashboard stats for the merchant.
     */
    public function dashboard(Request $request)
    {
        $merchant = $request->user();
        $profile = $merchant->merchantProfile;

        $activeListings = FoodListing::where('merchant_id', $merchant->id)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->where('pickup_deadline', '>', now())
            ->count();

        $totalListings = FoodListing::where('merchant_id', $merchant->id)->count();

        $expiredListings = FoodListing::where('merchant_id', $merchant->id)
            ->where('pickup_deadline', '<=', now())
            ->count();

        $outOfStockListings = FoodListing::where('merchant_id', $merchant->id)
            ->where('stock', 0)
            ->where('pickup_deadline', '>', now())
            ->count();

        return response()->json([
            'merchant' => $merchant,
            'profile' => $profile,
            'stats' => [
                'active_listings' => $activeListings,
                'total_listings' => $totalListings,
                'expired_listings' => $expiredListings,
                'out_of_stock' => $outOfStockListings,
            ],
        ]);
    }

    /**
     * List all food listings for the authenticated merchant.
     */
    public function listings(Request $request)
    {
        $query = FoodListing::where('merchant_id', $request->user()->id)
            ->with('category')
            ->latest();

        if ($request->has('status')) {
            switch ($request->status) {
                case 'active':
                    $query->where('is_active', true)
                        ->where('stock', '>', 0)
                        ->where('pickup_deadline', '>', now());
                    break;
                case 'expired':
                    $query->where('pickup_deadline', '<=', now());
                    break;
                case 'out_of_stock':
                    $query->where('stock', 0)
                        ->where('pickup_deadline', '>', now());
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
            }
        }

        $foods = $query->paginate(20);

        return response()->json([
            'foods' => $foods->items(),
            'meta' => [
                'current_page' => $foods->currentPage(),
                'last_page' => $foods->lastPage(),
                'total' => $foods->total(),
            ],
        ]);
    }

    /**
     * Store a new food listing.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:food_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'original_price' => 'required|numeric|min:0',
            'discounted_price' => 'required|numeric|min:0|lte:original_price',
            'stock' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'allergen_info' => 'nullable|string|max:500',
            'pickup_deadline' => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['merchant_id'] = $request->user()->id;
        $data['is_active'] = true;

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('food-images', 'public');
            $data['image'] = $path;
        }

        $food = FoodListing::create($data);
        $food->load('category');

        return response()->json([
            'message' => 'Food listing created successfully',
            'food' => $food,
        ], 201);
    }

    /**
     * Show a single food listing owned by the merchant.
     */
    public function show(Request $request, $id)
    {
        $food = FoodListing::where('merchant_id', $request->user()->id)
            ->with('category')
            ->findOrFail($id);

        return response()->json([
            'food' => array_merge($food->toArray(), [
                'discount_percentage' => $food->discount_percentage,
                'is_expired' => $food->is_expired,
            ]),
        ]);
    }

    /**
     * Update a food listing.
     */
    public function update(Request $request, $id)
    {
        $food = FoodListing::where('merchant_id', $request->user()->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:food_categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'original_price' => 'sometimes|numeric|min:0',
            'discounted_price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'allergen_info' => 'nullable|string|max:500',
            'pickup_deadline' => 'sometimes|date|after:now',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($food->image) {
                Storage::disk('public')->delete($food->image);
            }
            $path = $request->file('image')->store('food-images', 'public');
            $data['image'] = $path;
        }

        $food->update($data);
        $food->load('category');

        return response()->json([
            'message' => 'Food listing updated successfully',
            'food' => $food,
        ]);
    }

    /**
     * Delete a food listing.
     */
    public function destroy(Request $request, $id)
    {
        $food = FoodListing::where('merchant_id', $request->user()->id)
            ->findOrFail($id);

        // Delete image if exists
        if ($food->image) {
            Storage::disk('public')->delete($food->image);
        }

        $food->delete();

        return response()->json([
            'message' => 'Food listing deleted successfully',
        ]);
    }

    /**
     * Toggle food listing active status.
     */
    public function toggleActive(Request $request, $id)
    {
        $food = FoodListing::where('merchant_id', $request->user()->id)
            ->findOrFail($id);

        $food->update(['is_active' => !$food->is_active]);

        return response()->json([
            'message' => $food->is_active ? 'Listing activated' : 'Listing deactivated',
            'food' => $food,
        ]);
    }

    /**
     * Get/update merchant profile.
     */
    public function profile(Request $request)
    {
        $merchant = $request->user();
        $profile = $merchant->merchantProfile;

        return response()->json([
            'merchant' => $merchant,
            'profile' => $profile,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'business_name' => 'sometimes|string|max:255',
            'business_description' => 'sometimes|string|max:1000',
            'address' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $merchant = $request->user();

        // Update user fields
        if (isset($data['name'])) {
            $merchant->update(['name' => $data['name']]);
        }
        if (isset($data['phone'])) {
            $merchant->update(['phone' => $data['phone']]);
        }

        // Update or create merchant profile
        $profileData = array_filter([
            'business_name' => $data['business_name'] ?? null,
            'business_description' => $data['business_description'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? $merchant->phone,
        ]);

        if (!empty($profileData)) {
            $merchant->merchantProfile()->updateOrCreate(
                ['user_id' => $merchant->id],
                $profileData
            );
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'merchant' => $merchant->fresh(),
            'profile' => $merchant->merchantProfile()->first(),
        ]);
    }
}
