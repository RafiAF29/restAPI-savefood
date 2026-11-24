<?php

namespace Database\Seeders;

use App\Models\FoodCategory;
use App\Models\FoodListing;
use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $categories = [
            ['name' => 'Bakery', 'icon' => 'bakery_dining'],
            ['name' => 'Rice & Meals', 'icon' => 'rice_bowl'],
            ['name' => 'Drinks', 'icon' => 'local_cafe'],
            ['name' => 'Snacks', 'icon' => 'cookie'],
            ['name' => 'Fruits', 'icon' => 'nutrition'],
            ['name' => 'Desserts', 'icon' => 'cake'],
        ];

        foreach ($categories as $cat) {
            FoodCategory::firstOrCreate(['name' => $cat['name']], $cat);
        }

        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@savefood.com'],
            [
                'name' => 'Admin SaveFood',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        // Demo merchant
        $merchant = User::firstOrCreate(
            ['email' => 'merchant@savefood.com'],
            [
                'name' => 'Rafi Bakery',
                'password' => Hash::make('password123'),
                'role' => 'merchant',
                'phone' => '08123456789',
            ]
        );

        MerchantProfile::firstOrCreate(
            ['user_id' => $merchant->id],
            [
                'business_name' => 'Rafi Bakery & Cafe',
                'business_description' => 'Fresh bakery and cafe serving the community since 2020.',
                'address' => 'Jl. Merdeka No. 10, Jakarta',
                'phone' => '08123456789',
                'is_verified' => true,
            ]
        );

        // Demo merchant 2
        $merchant2 = User::firstOrCreate(
            ['email' => 'merchant2@savefood.com'],
            [
                'name' => 'Warung Nusantara',
                'password' => Hash::make('password123'),
                'role' => 'merchant',
                'phone' => '08198765432',
            ]
        );

        MerchantProfile::firstOrCreate(
            ['user_id' => $merchant2->id],
            [
                'business_name' => 'Warung Nusantara',
                'business_description' => 'Authentic Indonesian food, home-cooked style.',
                'address' => 'Jl. Sudirman No. 55, Jakarta',
                'phone' => '08198765432',
                'is_verified' => true,
            ]
        );

        // Demo food listings
        $bakery = FoodCategory::where('name', 'Bakery')->first();
        $meals = FoodCategory::where('name', 'Rice & Meals')->first();
        $drinks = FoodCategory::where('name', 'Drinks')->first();
        $snacks = FoodCategory::where('name', 'Snacks')->first();
        $desserts = FoodCategory::where('name', 'Desserts')->first();

        $foods = [
            [
                'merchant_id' => $merchant->id,
                'category_id' => $bakery->id,
                'name' => 'Croissant Pack (3 pcs)',
                'description' => 'Freshly baked butter croissants. Made this morning, still soft and flaky.',
                'original_price' => 45000,
                'discounted_price' => 20000,
                'stock' => 5,
                'allergen_info' => 'Contains: wheat, dairy, eggs',
                'pickup_deadline' => now()->addHours(6),
            ],
            [
                'merchant_id' => $merchant->id,
                'category_id' => $bakery->id,
                'name' => 'Roti Sobek Cokelat',
                'description' => 'Soft pull-apart bread filled with rich chocolate.',
                'original_price' => 35000,
                'discounted_price' => 15000,
                'stock' => 8,
                'allergen_info' => 'Contains: wheat, dairy, soy',
                'pickup_deadline' => now()->addHours(5),
            ],
            [
                'merchant_id' => $merchant->id,
                'category_id' => $desserts->id,
                'name' => 'Assorted Mini Cakes',
                'description' => 'Box of 4 mini cakes: red velvet, tiramisu, cheese, and black forest.',
                'original_price' => 80000,
                'discounted_price' => 35000,
                'stock' => 3,
                'allergen_info' => 'Contains: wheat, dairy, eggs, nuts',
                'pickup_deadline' => now()->addHours(4),
            ],
            [
                'merchant_id' => $merchant2->id,
                'category_id' => $meals->id,
                'name' => 'Nasi Gudeg Komplit',
                'description' => 'Traditional Yogyakarta gudeg with rice, chicken, egg, and krecek.',
                'original_price' => 30000,
                'discounted_price' => 15000,
                'stock' => 10,
                'pickup_deadline' => now()->addHours(3),
            ],
            [
                'merchant_id' => $merchant2->id,
                'category_id' => $meals->id,
                'name' => 'Nasi Rendang',
                'description' => 'Slow-cooked beef rendang with steamed rice and vegetables.',
                'original_price' => 35000,
                'discounted_price' => 18000,
                'stock' => 7,
                'pickup_deadline' => now()->addHours(4),
            ],
            [
                'merchant_id' => $merchant2->id,
                'category_id' => $drinks->id,
                'name' => 'Es Teh Tarik',
                'description' => 'Malaysian-style pulled tea with creamy condensed milk.',
                'original_price' => 15000,
                'discounted_price' => 8000,
                'stock' => 15,
                'pickup_deadline' => now()->addHours(5),
            ],
            [
                'merchant_id' => $merchant->id,
                'category_id' => $snacks->id,
                'name' => 'Pisang Goreng Madu (5 pcs)',
                'description' => 'Crispy fried banana drizzled with honey.',
                'original_price' => 25000,
                'discounted_price' => 12000,
                'stock' => 6,
                'pickup_deadline' => now()->addHours(3),
            ],
        ];

        foreach ($foods as $food) {
            FoodListing::create($food);
        }

        // Demo customer
        User::firstOrCreate(
            ['email' => 'customer@savefood.com'],
            [
                'name' => 'Test Customer',
                'password' => Hash::make('password123'),
                'role' => 'customer',
            ]
        );
    }
}
