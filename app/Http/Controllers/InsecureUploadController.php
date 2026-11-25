<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * LAB 5 PART A — FOR INSPECTION ONLY. This controller is intentionally insecure and is NOT routed.
 * Do not register it. Identify every weakness (lab Step A3), then build the secure pipeline in Parts B-E.
 */
class InsecureUploadController extends Controller
{

/* 1) No validation - file type and file size
    2) Originial filename should be renamed
    3)  Automatically upload in public folder
    4) No quarantine and malware scan
    5) No ownership record - Who can see what
*/
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
