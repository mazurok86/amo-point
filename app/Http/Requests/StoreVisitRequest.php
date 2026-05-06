<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor_uid' => ['nullable', 'uuid'],
            'page_url' => ['required', 'url', 'max:2048'],
            'referrer' => ['nullable', 'url', 'max:2048'],
            'user_agent' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
