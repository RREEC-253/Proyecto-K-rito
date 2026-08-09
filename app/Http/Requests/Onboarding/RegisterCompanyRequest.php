<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Company data
            'company.name' => ['required', 'string', 'max:150'],
            'company.business_name' => ['nullable', 'string', 'max:200'],
            'company.document_type' => ['nullable', 'string', 'max:10'],
            'company.document_number' => ['nullable', 'string', 'max:20'],
            'company.email' => ['nullable', 'email', 'max:150'],
            'company.phone' => ['nullable', 'string', 'max:20'],

            // Admin user data
            'admin.username' => ['required', 'string', 'max:50', 'alpha_dash'],
            'admin.email' => ['required', 'email', 'max:150'],
            'admin.password' => ['required', 'string', 'min:8'],
            'admin.first_name' => ['nullable', 'string', 'max:100'],
            'admin.last_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'company.name.required' => 'El nombre de la empresa es obligatorio.',
            'admin.username.required' => 'El nombre de usuario es obligatorio.',
            'admin.email.required' => 'El correo del administrador es obligatorio.',
            'admin.password.required' => 'La contraseña es obligatoria.',
            'admin.password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
