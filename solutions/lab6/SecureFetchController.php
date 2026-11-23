<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FetchUrlRequest;
use App\Services\SecureHttpFetcher;
use InvalidArgumentException;
use RuntimeException;

class SecureFetchController extends Controller
{
    public function fetch(FetchUrlRequest $request, SecureHttpFetcher $fetcher)
    {
        try {
            $result = $fetcher->fetch($request->validated('url'));

            return response()->json([
                'status' => $result['status'],
                'content' => $result['body'],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'Destination is not permitted.',
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Unable to fetch the resource.',
            ], 502);
        }
    }
}
