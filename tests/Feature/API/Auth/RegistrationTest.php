<?php

namespace Tests\Feature\API\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear roles y permisos necesarios para las pruebas
        $this->createRolesAndPermissions();
    }

    /**
     * Crear los roles y permisos necesarios para las pruebas
     */
    protected function createRolesAndPermissions(): void
    {
        // Crear permisos
        $taskPermissions = [
            'task:list',
            'task:create',
            'task:update',
            'task:delete',
        ];

        foreach ($taskPermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }

        // Crear rol de usuario
        $userRole = Role::create(['name' => 'user', 'guard_name' => 'api']);
        $userRole->givePermissionTo($taskPermissions);
    }

    /**
     * Test user can register with valid data
     */
    public function testUserCanRegisterWithValidData(): void
    {
        $userData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        // Debuggear la respuesta para entender el error
        if ($response->getStatusCode() !== 201) {
            dump($response->getContent());
        }

        $response->assertStatus(201)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'roles',
                    'permissions',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'name' => $userData['name'],
        ]);

        // Verificar que el usuario tiene el rol "user"
        $user = User::query()->where('email', $userData['email'])->first();
        $this->assertTrue($user->hasRole('user', 'api'));
    }

    /**
     * Test registration validation for required fields
     */
    public function testRegistrationRequiresAllFields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /**
     * Test password confirmation validation
     */
    public function testRegistrationRequiresPasswordConfirmation(): void
    {
        $userData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'Password123',
            // Missing password_confirmation
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test email must be unique
     */
    public function testRegistrationRequiresUniqueEmail(): void
    {
        // Create a user
        /** @var User $user */
        $user = User::factory()->create();

        $userData = [
            'name' => $this->faker->name,
            'email' => $user->email, // Same email as existing user
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
