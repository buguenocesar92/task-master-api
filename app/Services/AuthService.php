<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Interfaces\AuthServiceInterface;
use App\Services\Interfaces\LoggingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class AuthService implements AuthServiceInterface
{
    protected UserRepositoryInterface $userRepository;

    protected LoggingServiceInterface $logger;

    /**
     * Constructor
     */
    public function __construct(
        UserRepositoryInterface $userRepository,
        LoggingServiceInterface $logger
    ) {
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    /**
     * Registrar un nuevo usuario
     */
    public function register(array $data): User
    {
        $user = $this->userRepository->create($data);

        // Asignar rol "user" por defecto
        try {
            $role = Role::findByName('user', 'api');
            $user->assignRole($role);
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
            // Log error específico de roles usando el servicio
            $this->logger->log('Error al asignar rol de usuario', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'email' => $user->email,
            ], 'error');

            throw new \RuntimeException('No se pudo asignar el rol al usuario. Verifique la configuración de roles.');
        } catch (\Exception $e) {
            // Log error general usando el servicio
            $this->logger->log('Error inesperado al registrar usuario', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'email' => $user->email,
            ], 'error');

            throw new \RuntimeException('Ocurrió un error durante el registro del usuario.');
        }

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
    public function respondWithToken(string $token, int $status = 200): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Usar el nuevo método para obtener datos de usuario
        $userData = $this->getUserData($user);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 3600, // 1 hora fija por ahora
            'user' => $userData,
        ], $status);
    }

    /**
     * Obtener datos formateados del usuario incluyendo roles y permisos
     */
    public function getUserData(User $user): array
    {
        // Datos del usuario básicos
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        // Añadir roles y permisos
        $userData['roles'] = $user->getRoleNames();
        $userData['permissions'] = $user->getAllPermissions()->pluck('name');

        return $userData;
    }
}
