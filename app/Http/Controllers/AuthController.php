<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Interfaces\AuthServiceInterface;
use App\Services\Interfaces\LoggingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    protected AuthServiceInterface $authService;

    protected LoggingServiceInterface $logger;

    /**
     * Constructor
     */
    public function __construct(
        AuthServiceInterface $authService,
        LoggingServiceInterface $logger
    ) {
        $this->authService = $authService;
        $this->logger = $logger;
    }

    /**
     * Registrar un nuevo usuario
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Registrar al usuario
            $user = $this->authService->register($request->validated());

            // Generar token para auto-login
            $credentials = $request->only(['email', 'password']);
            $token = $this->authService->login($credentials);

            if (! $token) {
                // Error inesperado, pero respondemos con el usuario creado
                $this->logger->log('Usuario creado pero no se pudo iniciar sesión', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ], 'warning');

                return response()->json([
                    'message' => 'Usuario creado pero no se pudo iniciar sesión',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ], 201);
            }

            // Registro exitoso
            $this->logger->log('Usuario registrado correctamente', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            // Devolver respuesta con token y código 201 (Created)
            return $this->authService->respondWithToken($token, 201);
        } catch (\RuntimeException $e) {
            // Capturar excepciones específicas de registro
            $this->logger->log('Error específico durante el registro', [
                'error' => $e->getMessage(),
                'request' => $request->validated(),
            ], 'error');

            return response()->json(['error' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            // Capturar cualquier otra excepción
            $this->logger->log('Error inesperado durante el registro', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'request' => $request->validated(),
            ], 'error');

            return response()->json(['error' => 'Ocurrió un error durante el registro'], 500);
        }
    }

    /**
     * Iniciar sesión
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $token = $this->authService->login($credentials);

        if (! $token) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        return $this->authService->respondWithToken($token);
    }

    /**
     * Obtener información del usuario autenticado
     */
    public function me(): JsonResponse
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

        return response()->json($userData);
    }

    /**
     * Cerrar sesión
     */
    public function logout(): JsonResponse
    {
        Auth::logout();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    /**
     * Refrescar token - en implementación real deberíamos usar JWT refresh tokens
     * Por ahora, simplemente requerimos que el usuario vuelva a iniciar sesión
     */
    public function refresh(): JsonResponse
    {
        return response()->json([
            'message' => 'Para obtener un nuevo token, por favor inicie sesión nuevamente',
        ], 401);
    }
}
