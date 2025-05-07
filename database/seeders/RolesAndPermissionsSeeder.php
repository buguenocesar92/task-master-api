<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar cache de Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos para tareas
        $taskPermissions = [
            'task:list',
            'task:create',
            'task:update',
            'task:delete',
            'task:view',
        ];

        // Crear permisos para usuarios
        $userPermissions = [
            'user:list',
            'user:create',
            'user:update',
            'user:delete',
            'user:view',
        ];

        // Crear todos los permisos
        $allPermissions = array_merge($taskPermissions, $userPermissions);
        foreach ($allPermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }

        // Crear roles
        $roles = [
            'admin' => $allPermissions,
            'manager' => array_merge(
                $taskPermissions,
                ['user:list', 'user:view'],
            ),
            'user' => [
                'task:list',
                'task:create',
                'task:update',
                'task:view',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::create(['name' => $roleName, 'guard_name' => 'api']);
            $role->givePermissionTo($permissions);
        }

        // Asignar rol admin al primer usuario
        $adminUser = User::find(1);
        if ($adminUser) {
            $adminUser->assignRole('admin');
        }
    }
}
