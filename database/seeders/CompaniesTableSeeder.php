<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompaniesTableSeeder extends Seeder
{
    public function run(): void
    {
        Company::create([
            'name'            => 'prueba',
            'slug'            => 'prueba',
            'business_name'   => 'Empresa Principal S.A.C.',
            'document_type'   => 'RUC',
            'document_number' => '20123456789',
            'email'           => 'contacto@empresa.com',
            'phone'           => '987654321',
            'address'         => 'Av. Principal 123',
            'status'          => 'active', // Indispensable para el middleware EnsureCompanyIsActive
            'timezone'        => 'America/Lima',
            'settings'        => [],
        ]);
    }
}