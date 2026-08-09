<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Auth module
            ['module' => 'auth', 'action' => 'login', 'code' => 'auth.login', 'description' => 'Iniciar sesión'],
            ['module' => 'auth', 'action' => 'logout', 'code' => 'auth.logout', 'description' => 'Cerrar sesión'],

            // Users module
            ['module' => 'users', 'action' => 'view', 'code' => 'users.view', 'description' => 'Ver usuarios'],
            ['module' => 'users', 'action' => 'create', 'code' => 'users.create', 'description' => 'Crear usuarios'],
            ['module' => 'users', 'action' => 'update', 'code' => 'users.update', 'description' => 'Actualizar usuarios'],
            ['module' => 'users', 'action' => 'delete', 'code' => 'users.delete', 'description' => 'Eliminar usuarios'],

            // Roles module
            ['module' => 'roles', 'action' => 'view', 'code' => 'roles.view', 'description' => 'Ver roles'],
            ['module' => 'roles', 'action' => 'create', 'code' => 'roles.create', 'description' => 'Crear roles'],
            ['module' => 'roles', 'action' => 'update', 'code' => 'roles.update', 'description' => 'Actualizar roles'],
            ['module' => 'roles', 'action' => 'delete', 'code' => 'roles.delete', 'description' => 'Eliminar roles'],

            // Companies module
            ['module' => 'companies', 'action' => 'view', 'code' => 'companies.view', 'description' => 'Ver empresa'],
            ['module' => 'companies', 'action' => 'update', 'code' => 'companies.update', 'description' => 'Actualizar empresa'],

            // Audit module
            ['module' => 'audit', 'action' => 'view', 'code' => 'audit.view', 'description' => 'Ver registros de auditoría'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['code' => $permission['code']],
                $permission
            );
        }
    }
}
