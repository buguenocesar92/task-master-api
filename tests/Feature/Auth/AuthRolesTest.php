<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos
        $taskPermissions = [
            'task:list',
            'task:create',
            'task:update',
            'task:delete',
        ];

        $userPermissions = [
            'user:list',
            'user:create',
            'user:update',
            'user:delete',
        ];

        foreach (array_merge($taskPermissions, $userPermissions) as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }

        // Crear roles
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $adminRole->givePermissionTo(Permission::all());

        $userRole = Role::create(['name' => 'user', 'guard_name' => 'api']);
        $userRole->givePermissionTo($taskPermissions);
    }

    public function testRegisterEndpointAssignsUserRole(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        // Verificar que el usuario tiene el rol "user"
        $user = User::query()->where('email', 'test@example.com')->first();
        $this->assertTrue($user->hasRole('user', 'api'));

        // Verificar que la respuesta incluye los roles
        $response->assertJsonPath('user.roles.0', 'user');

        // Verificar que la respuesta incluye los permisos
        $this->assertCount(4, $response->json('user.permissions'));
    }

    public function testLoginEndpointReturnsRolesAndPermissions(): void
    {
        // Crear un usuario con rol de admin
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Asegurarnos que estamos usando el rol correcto
        $role = Role::findByName('admin', 'api');
        $user->assignRole($role);

        // Login
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Verificar que la respuesta incluye los roles
        $response->assertJsonPath('user.roles.0', 'admin');

        // Verificar que la respuesta incluye todos los permisos
        $this->assertCount(8, $response->json('user.permissions'));
    }

    public function testMeEndpointReturnsRolesAndPermissions(): void
    {
        // Crear un usuario con rol de admin
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Asegurarnos que estamos usando el rol correcto
        $role = Role::findByName('admin', 'api');
        $user->assignRole($role);

        // Login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('access_token');

        // Llamar al endpoint me
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/auth/me');

        $response->assertStatus(200);

        // Verificar que la respuesta incluye los roles
        $response->assertJsonPath('roles.0', 'admin');

        // Verificar que la respuesta incluye todos los permisos
        $this->assertCount(8, $response->json('permissions'));
    }
}
