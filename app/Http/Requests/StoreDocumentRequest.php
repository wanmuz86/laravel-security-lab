<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreDocumentRequest extends FormRequest
{
public function authorize(): bool
{
return $this->user() !== null; #User needs to be logged in to upload the file
}
public function rules(): array
{
    return [
        'document' => [
            'required',
            File::types([ # check the type as well
                'pdf',
                'png',
                'jpg',
                'jpeg',
            ])->max('2mb'),   # Max is 2Mb
            'extensions:pdf,png,jpg,jpeg', #Extension
        ],
    ];
}
}
