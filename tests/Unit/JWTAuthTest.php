<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Tests\TestCase;

class JWTAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que la generación de tokens JWT funciona.
     */
    public function testJwtTokenCanBeGenerated(): void
    {
        // Crear un usuario para la prueba
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Autenticar al usuario
        Auth::login($user);

        // Obtener el token
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');
        $token = $guard->refresh();

        // Verificar que se generó un token
        $this->assertNotEmpty($token);
        $this->assertTrue(is_string($token));
    }

    /**
     * Test que se puede autenticar con un token JWT.
     */
    public function testUserCanBeAuthenticatedWithToken(): void
    {
        // Crear un usuario para la prueba
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Autenticar al usuario
        Auth::login($user);

        // Obtener el token
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');
        $token = $guard->refresh();

        // Intentar autenticar con el token
        $guard->setToken($token);

        /** @var User|null $authenticatedUser */
        $authenticatedUser = $guard->user();

        // Verificar que se autenticó al usuario correcto
        $this->assertNotNull($authenticatedUser);
        $this->assertInstanceOf(User::class, $authenticatedUser);

        // Ahora que sabemos que es una instancia de User, podemos acceder con seguridad a sus propiedades
        $this->assertEquals($user->id, $authenticatedUser->id);
        $this->assertEquals($user->email, $authenticatedUser->email);
    }

    /**
     * Test que se puede invalidar un token JWT.
     */
    public function testTokenCanBeInvalidated(): void
    {
        // Crear un usuario para la prueba
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Autenticar al usuario
        Auth::login($user);

        // Obtener el token
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');
        $token = $guard->refresh();

        // Invalidar el token
        $guard->setToken($token);
        $guard->logout();

        // Verificar que el token fue invalidado
        $this->assertNull($guard->user());
    }
}
