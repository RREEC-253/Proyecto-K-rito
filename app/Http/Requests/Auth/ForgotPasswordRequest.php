<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_slug' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:150'],
        ];
    }
}
