<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function receive(Request $request)
    {
        $event = $request->validate([
            'type' => [
                'required',
                'string',
                'in:order.created,order.updated',
            ],
            'data' => [
                'required',
                'array',
            ],
        ]);

        return response()->json([
            'received' => true,
            'event_id' => $request->attributes->get('verified_webhook_id'),
            'type' => $event['type'],
        ]);
    }
}
