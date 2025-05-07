<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Interfaces\AuthServiceInterface;
use App\Services\Interfaces\LoggingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Task Master API",
 *     description="API para la gestión de tareas con autenticación JWT",
 *
 *     @OA\Contact(
 *         email="admin@taskmaster.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
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
     *
     * @OA\Post(
     *     path="/auth/register",
     *     summary="Registra un nuevo usuario",
     *     tags={"Autenticación"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del usuario a registrar",
     *
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *
     *             @OA\Property(property="name", type="string", example="Juan Pérez", description="Nombre del usuario"),
     *             @OA\Property(property="email", type="string", format="email", example="juan@example.com", description="Correo electrónico"),
     *             @OA\Property(property="password", type="string", format="password", example="Secret123", description="Contraseña (mínimo 8 caracteres)"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="Secret123", description="Confirmación de contraseña")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Usuario registrado correctamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbG..."),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Juan Pérez"),
     *                 @OA\Property(property="email", type="string", example="juan@example.com"),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string", example="user")),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string", example="task:create"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string", example="El correo ya está en uso."))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Ocurrió un error durante el registro")
     *         )
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/auth/login",
     *     summary="Inicia sesión de usuario",
     *     tags={"Autenticación"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Credenciales de acceso",
     *
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="juan@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Secret123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Inicio de sesión exitoso",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbG..."),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Juan Pérez"),
     *                 @OA\Property(property="email", type="string", example="juan@example.com"),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string", example="user")),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string", example="task:create"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Credenciales incorrectas",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Credenciales incorrectas")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string", example="El campo email es requerido."))
     *             )
     *         )
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/auth/me",
     *     summary="Obtiene información del usuario autenticado",
     *     tags={"Autenticación"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Información del usuario",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Juan Pérez"),
     *             @OA\Property(property="email", type="string", example="juan@example.com"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string", example="user")),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string", example="task:create"))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Usar el método del servicio para obtener datos formateados
        $userData = $this->authService->getUserData($user);

        return response()->json($userData);
    }

    /**
     * Cerrar sesión
     *
     * @OA\Post(
     *     path="/auth/logout",
     *     summary="Cierra la sesión del usuario actual",
     *     tags={"Autenticación"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Sesión cerrada correctamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Sesión cerrada correctamente")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function logout(): JsonResponse
    {
        Auth::logout();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    /**
     * Refrescar token - usando el token de refresco generado específicamente para esta finalidad
     *
     * @OA\Post(
     *     path="/auth/refresh",
     *     summary="Refresca el token JWT obteniendo uno nuevo",
     *     description="Utiliza un token de refresco (refresh_token) para obtener un nuevo token de acceso sin necesidad de credenciales",
     *     tags={"Autenticación"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Token de refresco obtenido previamente",
     *
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *
     *             @OA\Property(property="refresh_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbG...")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Token refrescado correctamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbG..."),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600),
     *             @OA\Property(property="refresh_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbG..."),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Juan Pérez"),
     *                 @OA\Property(property="email", type="string", example="juan@example.com"),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string", example="user")),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string", example="task:create"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Token inválido o expirado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Invalid refresh token")
     *         )
     *     )
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        // Validar que se proporciona un token de refresco
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Debe proporcionar un token de refresco válido'], 422);
        }

        $refreshToken = $request->input('refresh_token');

        // Intentar refrescar el token
        $newToken = $this->authService->refreshToken($refreshToken);

        if (! $newToken) {
            $this->logger->log('Intento fallido de refrescar token', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], 'warning');

            return response()->json(['error' => 'Token de refresco inválido o expirado'], 401);
        }

        // Devolver nueva respuesta con token actualizado
        return $this->authService->respondWithToken($newToken);
    }
}
