<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderProcessingService
{
    public function process(Order $order, string $operationKey): Order
    {
        try {
            return DB::transaction(function () use ($order, $operationKey) {
                // Lock the order row for update to prevent race conditions
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedOrder->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'order' => 'Order has already been processed or is not in a processable state.',
                    ]);
                }

                // Idempotent processing ledger: unique(order_id) and unique(operation_key)
                DB::table('order_processings')->insert([
                    'order_id' => $lockedOrder->id,
                    'operation_key' => $operationKey,
                    'status' => 'completed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $lockedOrder->status = 'processed';
                $lockedOrder->processed_at = now();
                $lockedOrder->save();

                return $lockedOrder->refresh();
            }, 3); // retry up to 3 times on deadlocks
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request won the race, or the operation key was already used.
            throw ValidationException::withMessages([
                'order' => 'Order has already been processed or the operation key was already used.',
            ]);
        }
    }
}
