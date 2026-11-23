<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;

class FileSignatureValidator
{
    public function isAllowed(UploadedFile $file): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $header = fread($handle, 16);
        } finally {
            fclose($handle);
        }

        if ($header === false) {
            return false;
        }

        $mime = $file->getMimeType();

        return match ($mime) {
            'application/pdf' => str_starts_with($header, '%PDF-'),
            'image/png' => str_starts_with($header, "\x89PNG\r\n\x1A\n"),
            'image/jpeg' => str_starts_with($header, "\xFF\xD8\xFF"),
            default => false,
        };
    }
}
