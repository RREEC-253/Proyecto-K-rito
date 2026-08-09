<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        // Global Owner role (company_id = null = system-wide, available to all tenants)
        $ownerRole = Role::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'Owner', 'company_id' => null],
            [
                'description' => 'Propietario/Administrador principal de la empresa con acceso total.',
                'is_system' => true,
                'is_editable' => false,
                'is_deletable' => false,
            ]
        );

        // Assign all permissions to Owner role
        $allPermissions = Permission::all()->pluck('id')->toArray();
        $ownerRole->permissions()->syncWithoutDetaching(
            array_fill_keys($allPermissions, ['created_at' => now()])
        );

        // Global Admin role
        $adminRole = Role::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'Admin', 'company_id' => null],
            [
                'description' => 'Administrador con acceso a gestión de usuarios y roles.',
                'is_system' => true,
                'is_editable' => true,
                'is_deletable' => false,
            ]
        );

        // Assign user/role management permissions to Admin
        $adminPermissions = Permission::whereIn('code', [
            'auth.login', 'auth.logout',
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'companies.view', 'companies.update',
        ])->pluck('id')->toArray();

        $adminRole->permissions()->syncWithoutDetaching(
            array_fill_keys($adminPermissions, ['created_at' => now()])
        );
    }
}
