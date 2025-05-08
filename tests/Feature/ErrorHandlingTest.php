<?php

namespace Tests\Feature;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Definir rutas de prueba para simular errores
        Route::get('/api/test/error/500', function () {
            throw new Exception('Error interno simulado');
        })->middleware('api');

        Route::get('/api/test/error/database', function () {
            throw new QueryException(
                'mysql',
                'SELECT * FROM non_existent_table',
                [],
                new Exception('Tabla no encontrada')
            );
        })->middleware('api');

        Route::get('/api/test/error/validation', function () {
            request()->validate([
                'required_field' => 'required',
            ]);
        })->middleware('api');
    }

    /**
     * Test que verifica que los errores internos devuelven un 500 con formato JSON.
     */
    public function testInternalErrorReturnsJsonResponse(): void
    {
        $response = $this->getJson('/api/test/error/500');

        $response->assertStatus(500)
            ->assertJsonStructure([
                'message',
            ]);
    }

    /**
     * Test que verifica que los errores de base de datos devuelven un formato adecuado.
     */
    public function testDatabaseErrorReturnsFormattedResponse(): void
    {
        $response = $this->getJson('/api/test/error/database');

        $response->assertStatus(500)
            ->assertJsonStructure([
                'message',
            ]);
    }

    /**
     * Test que verifica que los errores de validación devuelven 422 con detalles.
     */
    public function testValidationErrorReturnsDetailsInResponse(): void
    {
        $response = $this->getJson('/api/test/error/validation');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['required_field']);
    }

    /**
     * Test que verifica que las rutas no encontradas devuelven 404 en formato JSON.
     */
    public function testNotFoundRoutesReturn404Json(): void
    {
        $response = $this->getJson('/api/non-existent-route');

        $response->assertStatus(404)
            ->assertJsonStructure([
                'message',
            ]);
    }

    /**
     * Test que verifica que los métodos no permitidos devuelven 405 en formato JSON.
     */
    public function testMethodNotAllowedReturns405Json(): void
    {
        // Definir una ruta que solo acepta GET
        Route::get('/api/test/only-get', function () {
            return response()->json(['message' => 'ok']);
        })->middleware('api');

        // Intentar acceder con POST
        $response = $this->postJson('/api/test/only-get');

        $response->assertStatus(405)
            ->assertJsonStructure([
                'message',
            ]);
    }
}
