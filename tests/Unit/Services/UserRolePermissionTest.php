<?php

namespace Tests\Unit\Services;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos de prueba
        Permission::create(['name' => 'test:create', 'guard_name' => 'api']);
        Permission::create(['name' => 'test:read', 'guard_name' => 'api']);
        Permission::create(['name' => 'test:update', 'guard_name' => 'api']);
        Permission::create(['name' => 'test:delete', 'guard_name' => 'api']);

        // Crear roles de prueba
        $adminRole = Role::create(['name' => 'test-admin', 'guard_name' => 'api']);
        $adminRole->givePermissionTo(Permission::all());

        $userRole = Role::create(['name' => 'test-user', 'guard_name' => 'api']);
        $userRole->givePermissionTo(['test:read']);
    }

    public function testUserCanBeAssignedRole(): void
    {
        // Crear un usuario
        $user = User::factory()->create();

        // Asignar un rol
        $user->assignRole('test-admin');

        // Verificar que el rol fue asignado
        $this->assertTrue($user->hasRole('test-admin'));
        $this->assertFalse($user->hasRole('test-user'));
    }

    public function testUserCanHaveMultipleRoles(): void
    {
        // Crear un usuario
        $user = User::factory()->create();

        // Asignar múltiples roles
        $user->assignRole('test-admin');
        $user->assignRole('test-user');

        // Verificar que ambos roles fueron asignados
        $this->assertTrue($user->hasRole('test-admin'));
        $this->assertTrue($user->hasRole('test-user'));
    }

    public function testUserInheritsPermissionsFromRole(): void
    {
        // Crear un usuario
        $user = User::factory()->create();

        // Asignar un rol
        $user->assignRole('test-user');

        // Verificar que el usuario tiene los permisos del rol
        $this->assertTrue($user->hasPermissionTo('test:read'));
        $this->assertFalse($user->hasPermissionTo('test:create'));
    }

    public function testUserCanGetAllPermissions(): void
    {
        // Crear un usuario
        $user = User::factory()->create();

        // Asignar un rol
        $user->assignRole('test-admin');

        // Verificar que el usuario tiene todos los permisos
        $this->assertTrue($user->hasPermissionTo('test:create'));
        $this->assertTrue($user->hasPermissionTo('test:read'));
        $this->assertTrue($user->hasPermissionTo('test:update'));
        $this->assertTrue($user->hasPermissionTo('test:delete'));

        // Obtener todos los permisos
        $permissions = $user->getAllPermissions();

        // Verificar que son 4 permisos
        $this->assertCount(4, $permissions);
    }

    public function testGettingRoleNamesWorks(): void
    {
        // Crear un usuario
        $user = User::factory()->create();

        // Asignar múltiples roles
        $user->assignRole('test-admin');
        $user->assignRole('test-user');

        // Obtener nombres de roles
        $roleNames = $user->getRoleNames();

        // Verificar que contiene los roles correctos
        $this->assertCount(2, $roleNames);
        $this->assertTrue($roleNames->contains('test-admin'));
        $this->assertTrue($roleNames->contains('test-user'));
    }
}
