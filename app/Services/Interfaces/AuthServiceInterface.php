<?php

namespace App\Services\Interfaces;

use App\Models\User;
use Illuminate\Http\JsonResponse;

interface AuthServiceInterface
{
    /**
     * Registrar un nuevo usuario
     *
     * @param array $data
     * @return User
     */
    public function register(array $data): User;

    /**
     * Iniciar sesión de usuario
     *
     * @param array $credentials
     * @return string|bool
     */
    public function login(array $credentials): string|bool;

    /**
     * Construir respuesta con token JWT
     *
     * @param string $token
     * @return JsonResponse
     */
    public function respondWithToken(string $token): JsonResponse;
}
