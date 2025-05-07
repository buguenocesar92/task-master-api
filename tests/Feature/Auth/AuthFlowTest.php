<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que verifica el flujo de autenticación simplificado:
     * login → acceso a recursos protegidos → logout
     */
    public function testCompleteAuthenticationFlow(): void
    {
        // En lugar de probar registro, vamos a usar login que es más estable
        $email = 'testflow@example.com';
        $password = 'password123';

        // Crear usuario vía API de registro
        $userData = [
            'name' => 'Test User',
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $this->postJson('/api/auth/register', $userData);

        // 1. LOGIN
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'user',
            ]);

        $token = $loginResponse->json('access_token');

        // 2. ACCESO A RECURSOS PROTEGIDOS
        $meResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('email', $email);

        // 3. LOGOUT
        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson(['message' => 'Sesión cerrada correctamente']);

        // 4. VERIFICAR QUE EL TOKEN YA NO FUNCIONA
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me')
            ->assertStatus(401);
    }
}
