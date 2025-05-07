<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Tests\TestCase;

class ProtectedRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que verifica que las rutas protegidas requieren autenticación.
     */
    public function testProtectedRoutesRequireAuthentication(): void
    {
        // Rutas protegidas para probar con sus métodos permitidos
        $protectedRoutes = [
            ['route' => '/api/auth/me', 'method' => 'GET'],
            ['route' => '/api/auth/logout', 'method' => 'POST'],
        ];

        foreach ($protectedRoutes as $routeInfo) {
            $route = $routeInfo['route'];
            $method = $routeInfo['method'];

            // Hacer la solicitud con el método correcto
            $response = $this->json($method, $route);

            // Verificar que requiere autenticación
            $response->assertStatus(401);
        }
    }

    /**
     * Test que verifica que las rutas protegidas son accesibles con un token válido.
     */
    public function testProtectedRoutesAreAccessibleWithValidToken(): void
    {
        // Crear usuario y obtener token
        /** @var User $user */
        $user = User::factory()->create();
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');
        $token = $guard->login($user);

        // Verificar acceso a ruta 'me'
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'roles',
                'permissions',
            ]);
    }

    /**
     * Test que verifica que las rutas públicas son accesibles sin autenticación.
     */
    public function testPublicRoutesAreAccessibleWithoutAuthentication(): void
    {
        // Rutas públicas para probar
        $publicRoutes = [
            '/api/auth/login',
            '/api/auth/register',
            '/api/auth/refresh',
        ];

        // Verificar que cada ruta retorna un código diferente a 401 (Unauthorized)
        foreach ($publicRoutes as $route) {
            $response = $this->postJson($route, []);

            // El código puede ser 422 (Unprocessable Entity) debido a validación
            // u otro código, pero nunca 401 (Unauthorized)
            $this->assertNotEquals(401, $response->getStatusCode());
        }
    }

    /**
     * Test que verifica el comportamiento con un token inválido.
     */
    public function testInvalidTokenFails(): void
    {
        // Intentar acceder con un token inválido
        $this->withHeaders([
            'Authorization' => 'Bearer invalidtoken',
        ])->getJson('/api/auth/me')
            ->assertStatus(401);
    }
}
