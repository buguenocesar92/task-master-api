<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class JWTAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que la generación de tokens JWT funciona.
     */
    public function test_jwt_token_can_be_generated(): void
    {
        // Crear un usuario para la prueba
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Generar un token para el usuario
        $token = JWTAuth::fromUser($user);

        // Verificar que se generó un token
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    /**
     * Test que se puede autenticar con un token JWT.
     */
    public function test_user_can_be_authenticated_with_token(): void
    {
        // Crear un usuario para la prueba
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Generar un token para el usuario
        $token = JWTAuth::fromUser($user);

        // Intentar autenticar con el token
        $authenticatedUser = JWTAuth::setToken($token)->authenticate();

        // Verificar que se autenticó al usuario correcto
        $this->assertNotNull($authenticatedUser);
        $this->assertEquals($user->id, $authenticatedUser->id);
        $this->assertEquals($user->email, $authenticatedUser->email);
    }

    /**
     * Test que se puede invalidar un token JWT.
     */
    public function test_token_can_be_invalidated(): void
    {
        // Crear un usuario para la prueba
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Generar un token para el usuario
        $token = JWTAuth::fromUser($user);

        // Invalidar el token
        JWTAuth::setToken($token)->invalidate();

        // Intentar autenticar con el token invalidado (debería fallar)
        $this->expectException(\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException::class);
        JWTAuth::setToken($token)->authenticate();
    }
}
