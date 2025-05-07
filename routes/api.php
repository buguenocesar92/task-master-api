<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas de autenticación
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    // Ruta de refresh fuera del middleware auth:api
    Route::post('refresh', [AuthController::class, 'refresh']);

    // Rutas protegidas por JWT
    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

/**
 * @OA\Get(
 *     path="/test-log",
 *     summary="Prueba el sistema de logging",
 *     tags={"Desarrollo"},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Log registrado correctamente",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="message", type="string", example="Log de prueba realizado. Revisa storage/logs/laravel.log")
 *         )
 *     )
 * )
 */
// Ruta de prueba para el sistema de logging
Route::get('test-log', function (\App\Services\Interfaces\LoggingServiceInterface $logger) {
    $logger->log('Test del sistema de logging', ['source' => 'test-route']);

    return response()->json(['message' => 'Log de prueba realizado. Revisa storage/logs/laravel.log']);
});
