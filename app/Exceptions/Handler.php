<?php

namespace App\Exceptions;

use App\Helpers\LogHelper;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            // Registrando excepción en el sistema de logs centralizado
            LogHelper::toLogstash('Error de aplicación', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], 'error');
        });
    }

    /**
     * A function to handle exceptions
     *
     * @param  Request  $request
     * @return JsonResponse|SymfonyResponse
     */
    public function render($request, Throwable $e)
    {
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'The specified URL could not be found on this server.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($e instanceof ValidationException) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => 'The given data failed to pass validation.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return parent::render($request, $e);
    }
}
