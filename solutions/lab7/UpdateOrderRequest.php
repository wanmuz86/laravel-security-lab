<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'description' => [
                'sometimes',
                'required',
                'string',
                'max:500',
            ],
            'user_id' => ['prohibited'],
            'total_cents' => ['prohibited'],
            'status' => ['prohibited'],
            'payment_reference' => ['prohibited'],
            'internal_notes' => ['prohibited'],
            'processed_at' => ['prohibited'],
        ];
    }
}
