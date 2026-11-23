<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * LAB 5 PART A — FOR INSPECTION ONLY. This controller is intentionally insecure and is NOT routed.
 * Do not register it. Identify every weakness (lab Step A3), then build the secure pipeline in Parts B-E.
 */
class InsecureUploadController extends Controller
{
    public function store(Request $request)
    {
        $file = $request->file('document');
        $filename = $file->getClientOriginalName();
        $file->move(
            public_path('uploads'),
            $filename
        );

        return response()->json([
            'message' => 'Upload successful',
            'url' => '/uploads/'.$filename,
        ]);
    }
}
