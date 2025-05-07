<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que verifica el inicio de sesión exitoso.
     */
    public function testSuccessfulLogin(): void
    {
        // Crear un usuario para la prueba
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Intentar login
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Verificar respuesta
        $response->assertStatus(200)
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
    }

    /**
     * Test que verifica el rechazo de login con credenciales incorrectas.
     */
    public function testLoginFailsWithIncorrectCredentials(): void
    {
        // Crear un usuario para la prueba
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Intentar login con contraseña incorrecta
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        // Verificar respuesta
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Credenciales incorrectas',
            ]);
    }

    /**
     * Test que verifica que el login requiere todos los campos obligatorios.
     */
    public function testLoginRequiresAllFields(): void
    {
        // Intentar login sin email
        $response = $this->postJson('/api/auth/login', [
            'password' => 'password123',
        ]);

        // Verificar validación
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Intentar login sin contraseña
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
        ]);

        // Verificar validación
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test que verifica que el email debe ser válido.
     */
    public function testLoginRequiresValidEmail(): void
    {
        // Intentar login con email inválido
        $response = $this->postJson('/api/auth/login', [
            'email' => 'notemail',
            'password' => 'password123',
        ]);

        // Verificar validación
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test que verifica el login con un usuario inexistente.
     */
    public function testLoginFailsWithNonexistentUser(): void
    {
        // Intentar login con usuario inexistente
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        // Verificar respuesta
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Credenciales incorrectas',
            ]);
    }
}
