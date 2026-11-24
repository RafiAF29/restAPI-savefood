<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', [
                'pending',
                'accepted',
                'ready',
                'picked_up',
                'rejected',
                'cancelled',
            ])->default('pending');
            $table->decimal('total_amount', 12, 2);
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('pickup_time')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
