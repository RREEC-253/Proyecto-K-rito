<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_slug' => ['required', 'string', 'max:60'],
            'identifier' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_slug.required' => 'El slug de la empresa es obligatorio.',
            'identifier.required' => 'El usuario o correo es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ];
    }
}
