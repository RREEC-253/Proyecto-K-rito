<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        User::create([
            'company_id' => $company->id, // Asigna el UUID de la empresa
            'username'       => 'admin',
            'email'      => 'admin@admin.com',
            'password'   => Hash::make('admin'),
        ]);
    }
}