<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    protected AuthServiceInterface $authService;

    /**
     * Constructor
     */
    public function __construct(AuthServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Registrar un nuevo usuario
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Registrar al usuario
        $user = $this->authService->register($request->validated());

        // Generar token para auto-login
        $credentials = $request->only(['email', 'password']);
        $token = $this->authService->login($credentials);

        if (! $token) {
            // Error inesperado, pero respondemos con el usuario creado
            return response()->json(['message' => 'Usuario creado pero no se pudo iniciar sesión'], 201);
        }

        // Devolver respuesta con token
        return $this->authService->respondWithToken($token);
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

        // Comentamos estas líneas porque están dando errores
        // if (method_exists($user, 'getRoleNames')) {
        //     $userData['roles'] = $user->getRoleNames();
        // }

        // if (method_exists($user, 'getAllPermissions')) {
        //     $userData['permissions'] = $user->getAllPermissions()->pluck('name');
        // }

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
