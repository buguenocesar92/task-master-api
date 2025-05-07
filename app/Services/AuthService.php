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
     * Generar un token de refresco para el usuario
     */
    public function generateRefreshToken(): string|bool
    {
        $claims = [
            'refresh' => true, // Marcar como token de refresco
            'sub' => Auth::id(), // Asegurar que el sujeto es el usuario correcto
            'iat' => time(), // Hora de emisión
        ];

        try {
            // Generar un token de refresco con TTL corto para pruebas (5 minutos)
            // En producción usar: ->setTTL(60 * 24 * 7) para 7 días
            $refreshToken = Auth::claims($claims)->setTTL(5)->fromUser(Auth::user());

            $this->logger->log('Token de refresco generado', [
                'user_id' => Auth::id(),
                'exp' => time() + (5 * 60), // Tiempo de expiración (5 minutos)
            ], 'debug');

            return $refreshToken;
        } catch (\Exception $e) {
            $this->logger->log('Error al generar token de refresco', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ], 'error');

            return false;
        }
    }

    /**
     * Refrescar un token JWT existente
     */
    public function refreshToken(string $token): string|bool
    {
        try {
            // Establecer el token en el contexto de autenticación
            Auth::setToken($token);

            // Verificar que el token es válido
            if (!Auth::check()) {
                $this->logger->log('Intento de refresco con token inválido', [], 'warning');
                return false;
            }

            // Verificar si es un token de refresco
            $payload = Auth::payload();
            if (!$payload->get('refresh')) {
                $this->logger->log('Intento de refresco con token no designado para refresco', [
                    'user_id' => Auth::id(),
                ], 'warning');
                return false;
            }

            // Generar un nuevo token de acceso
            $newToken = Auth::refresh();

            $this->logger->log('Token refrescado exitosamente', [
                'user_id' => Auth::id(),
            ], 'info');

            return $newToken;
        } catch (\Exception $e) {
            $this->logger->log('Error al refrescar token', [
                'error' => $e->getMessage(),
            ], 'error');

            return false;
        }
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

        // Generar un token de refresco
        $refreshToken = $this->generateRefreshToken();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl', 60) * 60, // en segundos
            'refresh_token' => $refreshToken ?: null,
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
