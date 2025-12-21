<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('department_number_settings')) {
            return;
        }

        Order::query()
            ->select(['id', 'order_number', 'department'])
            ->orderBy('id')
            ->chunkById(500, function ($orders): void {
                foreach ($orders as $order) {
                    $expected = Order::formatOrderNumber((int) $order->id, $order->department, $order->order_number);

                    if ($order->order_number === $expected) {
                        continue;
                    }

                    Order::query()
                        ->whereKey($order->id)
                        ->update(['order_number' => $expected]);
                }
            });
    }

    public function down(): void
    {
        // �?�? �?�?�?�? �?�?�?�? �?�?�?�?�?�?�? �?�? �?���?�?�? �?�?�?�?�?�?�?�? �?���?.
    }
};
