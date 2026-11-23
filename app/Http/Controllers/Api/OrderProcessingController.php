<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderProcessingController extends Controller
{
    public function store(
        Request $request,
        Order $order,
        OrderProcessingService $service
    ) {
        Gate::authorize('process', $order);

        $data = $request->validate([
            'operation_key' => [
                'required',
                'string',
                'uuid',
            ],
        ]);

        $processed = $service->process($order, $data['operation_key']);

        return new OrderResource($processed);
    }
}
