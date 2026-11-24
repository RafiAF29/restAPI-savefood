<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FoodController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Food (public)
Route::get('/foods', [FoodController::class, 'index']);
Route::get('/foods/featured', [FoodController::class, 'featured']);
Route::get('/foods/{id}', [FoodController::class, 'show']);
Route::get('/categories', [FoodController::class, 'categories']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Customer order routes
    Route::middleware('role:customer')->prefix('customer')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders', [OrderController::class, 'customerOrders']);
        Route::get('/orders/{id}', [OrderController::class, 'customerOrderDetail']);
        Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
    });

    // Merchant routes
    Route::middleware('role:merchant')->prefix('merchant')->group(function () {
        Route::get('/dashboard', [MerchantController::class, 'dashboard']);
        Route::get('/profile', [MerchantController::class, 'profile']);
        Route::put('/profile', [MerchantController::class, 'updateProfile']);
        Route::get('/foods', [MerchantController::class, 'listings']);
        Route::post('/foods', [MerchantController::class, 'store']);
        Route::get('/foods/{id}', [MerchantController::class, 'show']);
        Route::post('/foods/{id}', [MerchantController::class, 'update']);
        Route::delete('/foods/{id}', [MerchantController::class, 'destroy']);
        Route::patch('/foods/{id}/toggle', [MerchantController::class, 'toggleActive']);

        Route::get('/orders', [OrderController::class, 'merchantOrders']);
        Route::get('/orders/{id}', [OrderController::class, 'merchantOrderDetail']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    });
});
