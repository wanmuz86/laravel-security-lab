<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                File::types(['pdf', 'png', 'jpg', 'jpeg'])->max('2mb'),
                'extensions:pdf,png,jpg,jpeg',
            ],
        ];
    }
}
