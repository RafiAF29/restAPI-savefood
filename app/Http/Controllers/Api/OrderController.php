<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FoodListing;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Customer: Create a new order.
     * Uses DB transaction to prevent overselling.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.food_listing_id' => 'required|exists:food_listings,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = DB::transaction(function () use ($request) {
                $customerId = $request->user()->id;
                $items = $request->items;
                $merchantId = null;
                $totalAmount = 0;
                $orderItems = [];

                foreach ($items as $item) {
                    $food = FoodListing::lockForUpdate()->find($item['food_listing_id']);

                    if (!$food) {
                        throw new \Exception("Food listing not found.");
                    }

                    // Validate same merchant
                    if ($merchantId === null) {
                        $merchantId = $food->merchant_id;
                    } elseif ($food->merchant_id !== $merchantId) {
                        throw new \Exception("All items must be from the same merchant.");
                    }

                    // Validate merchant is not ordering own food
                    if ($food->merchant_id === $customerId) {
                        throw new \Exception("You cannot order your own food.");
                    }

                    // Validate active & not expired
                    if (!$food->is_active) {
                        throw new \Exception("{$food->name} is no longer available.");
                    }

                    if ($food->pickup_deadline->isPast()) {
                        throw new \Exception("{$food->name} has passed the pickup deadline.");
                    }

                    // Validate stock
                    if ($food->stock < $item['quantity']) {
                        throw new \Exception("{$food->name} only has {$food->stock} left in stock.");
                    }

                    // Deduct stock
                    $food->decrement('stock', $item['quantity']);

                    $subtotal = $food->discounted_price * $item['quantity'];
                    $totalAmount += $subtotal;

                    $orderItems[] = [
                        'food_listing_id' => $food->id,
                        'food_name' => $food->name,
                        'quantity' => $item['quantity'],
                        'unit_price' => $food->discounted_price,
                        'subtotal' => $subtotal,
                    ];
                }

                // Create order
                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'customer_id' => $customerId,
                    'merchant_id' => $merchantId,
                    'status' => 'pending',
                    'total_amount' => $totalAmount,
                    'notes' => $request->notes,
                ]);

                // Create order items
                foreach ($orderItems as $oi) {
                    $order->items()->create($oi);
                }

                return $order;
            });

            $order->load(['items.foodListing', 'merchant.merchantProfile']);

            return response()->json([
                'message' => 'Order placed successfully!',
                'order' => $order,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Customer: List my orders.
     */
    public function customerOrders(Request $request)
    {
        $query = Order::where('customer_id', $request->user()->id)
            ->with(['items.foodListing', 'merchant.merchantProfile'])
            ->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(20);

        return response()->json([
            'orders' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Customer: View single order detail.
     */
    public function customerOrderDetail(Request $request, $id)
    {
        $order = Order::where('customer_id', $request->user()->id)
            ->with(['items.foodListing', 'merchant.merchantProfile'])
            ->findOrFail($id);

        return response()->json(['order' => $order]);
    }

    /**
     * Customer: Cancel an order (only if pending).
     */
    public function cancelOrder(Request $request, $id)
    {
        $order = Order::where('customer_id', $request->user()->id)
            ->findOrFail($id);

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending orders can be cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            // Restore stock
            foreach ($order->items as $item) {
                $food = FoodListing::find($item->food_listing_id);
                if ($food) {
                    $food->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => 'cancelled']);
        });

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'order' => $order->fresh(['items.foodListing', 'merchant.merchantProfile']),
        ]);
    }

    /**
     * Merchant: List orders for my store.
     */
    public function merchantOrders(Request $request)
    {
        $query = Order::where('merchant_id', $request->user()->id)
            ->with(['items.foodListing', 'customer'])
            ->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(20);

        return response()->json([
            'orders' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Merchant: View single order detail.
     */
    public function merchantOrderDetail(Request $request, $id)
    {
        $order = Order::where('merchant_id', $request->user()->id)
            ->with(['items.foodListing', 'customer'])
            ->findOrFail($id);

        return response()->json(['order' => $order]);
    }

    /**
     * Merchant: Update order status.
     * Flow: pending → accepted → ready → picked_up
     *        pending → rejected
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::where('merchant_id', $request->user()->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,ready,picked_up,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newStatus = $request->status;
        $validTransitions = [
            'pending' => ['accepted', 'rejected'],
            'accepted' => ['ready'],
            'ready' => ['picked_up'],
        ];

        $allowed = $validTransitions[$order->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            return response()->json([
                'message' => "Cannot change status from '{$order->status}' to '{$newStatus}'.",
            ], 422);
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'rejected') {
            $updateData['rejection_reason'] = $request->rejection_reason;

            // Restore stock on rejection
            DB::transaction(function () use ($order, $updateData) {
                foreach ($order->items as $item) {
                    $food = FoodListing::find($item->food_listing_id);
                    if ($food) {
                        $food->increment('stock', $item->quantity);
                    }
                }
                $order->update($updateData);
            });
        } elseif ($newStatus === 'picked_up') {
            $updateData['pickup_time'] = now();
            $order->update($updateData);
        } else {
            $order->update($updateData);
        }

        return response()->json([
            'message' => "Order status updated to {$newStatus}.",
            'order' => $order->fresh(['items.foodListing', 'customer']),
        ]);
    }
}
