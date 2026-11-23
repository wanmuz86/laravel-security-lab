<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REFERENCE SOLUTION (instructor): server-side type and size restrictions (R03).
 */
class StoreProjectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // tenant check is performed in the controller against the parent project
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,txt,docx,xlsx,png,jpg', 'mimetypes:application/pdf,text/plain,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/png,image/jpeg'],
        ];
    }
}
