<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que un usuario puede cerrar sesión correctamente.
     */
    public function testUserCanLogout(): void
    {
        // Crear usuario y obtener token
        /** @var User $user */
        $user = User::factory()->create();
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');
        $token = $guard->login($user);

        // Llamar a logout
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        // Verificar respuesta
        $response->assertStatus(200)
            ->assertJson(['message' => 'Sesión cerrada correctamente']);

        // Verificar que el token ya no funciona
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    /**
     * Test que intenta hacer logout sin estar autenticado.
     */
    public function testLogoutFailsWithoutAuthentication(): void
    {
        // Intentar logout sin token
        $response = $this->postJson('/api/auth/logout');

        // Verificar que se requiere autenticación
        $response->assertStatus(401);
    }
}
