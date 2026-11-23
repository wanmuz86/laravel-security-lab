<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * LAB 7 Part A4 — INTENTIONALLY VULNERABLE (instructor-provided). Do not copy this pattern.
 * Routed under /api/lab-vulnerable/orders/... so the flaws can be demonstrated after authentication.
 */
class VulnerableOrderController extends Controller
{
    public function show(int $id)
    {
        return response()->json(
            Order::findOrFail($id)
        );
    }

    public function update(Request $request, int $id)
    {
        $order = Order::findOrFail($id);
        $order->update($request->all());

        return response()->json($order);
    }

    public function process(int $id)
    {
        $order = Order::findOrFail($id);

        // Vulnerable: no ownership checks or state verification
        $order->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Order processed.',
            'order' => $order,
        ]);
    }
}
