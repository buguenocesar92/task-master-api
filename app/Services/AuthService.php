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
     *
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Registrar un nuevo usuario
     *
     * @param array $data
     * @return User
     */
    public function register(array $data): User
    {
        return $this->userRepository->create($data);
    }

    /**
     * Iniciar sesión de usuario
     *
     * @param array $credentials
     * @return string|bool
     */
    public function login(array $credentials): string|bool
    {
        if (!$token = Auth::attempt($credentials)) {
            return false;
        }

        return $token;
    }

    /**
     * Construir respuesta con token JWT
     *
     * @param string $token
     * @return JsonResponse
     */
    public function respondWithToken(string $token): JsonResponse
    {
        $user = Auth::user();

        // Datos del usuario básicos
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        // Añadir roles y permisos si el método existe (Spatie Permission)
        if (method_exists($user, 'getRoleNames')) {
            $userData['roles'] = $user->getRoleNames();
        }

        if (method_exists($user, 'getAllPermissions')) {
            $userData['permissions'] = $user->getAllPermissions()->pluck('name');
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
            'user' => $userData,
        ]);
    }
}
