<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthService implements AuthServiceInterface
{
    protected UserRepositoryInterface $userRepository;

    /**
     * Constructor
     */
    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Registrar un nuevo usuario
     */
    public function register(array $data): User
    {
        $user = $this->userRepository->create($data);

        // Asignar rol de usuario por defecto
        $user->assignRole('user');

        return $user;
    }

    /**
     * Iniciar sesión de usuario
     */
    public function login(array $credentials): string|bool
    {
        if (! $token = Auth::attempt($credentials)) {
            return false;
        }

        return $token;
    }

    /**
     * Construir respuesta con token JWT
     */
    public function respondWithToken(string $token): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Datos del usuario básicos
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        // Añadir roles y permisos
        $userData['roles'] = $user->getRoleNames();
        $userData['permissions'] = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 3600, // 1 hora fija por ahora
            'user' => $userData,
        ]);
    }
}
