<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** LAB 3 REFERENCE SOLUTION: server-side validation + authorization via the Project policy. */
class StoreProjectCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && $this->user()->can('view', $project);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
        ];
    }
}
