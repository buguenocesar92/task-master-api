<?php

namespace App\Services\Interfaces;

use App\Models\User;
use Illuminate\Http\JsonResponse;

interface AuthServiceInterface
{
    /**
     * Registrar un nuevo usuario
     */
    public function register(array $data): User;

    /**
     * Iniciar sesión de usuario
     */
    public function login(array $credentials): string|bool;

    /**
     * Construir respuesta con token JWT
     */
    public function respondWithToken(string $token): JsonResponse;
}
