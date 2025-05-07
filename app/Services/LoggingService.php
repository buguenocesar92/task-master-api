<?php

namespace App\Services;

use App\Helpers\LogHelper;
use App\Services\Interfaces\LoggingServiceInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class LoggingService implements LoggingServiceInterface
{
    /**
     * Envía un log al sistema centralizado
     *
     * @param  string  $message  El mensaje principal
     * @param  array  $context  Datos adicionales para el log
     * @param  string  $level  Nivel de log (debug, info, warning, error, critical)
     */
    public function log(string $message, array $context = [], string $level = 'info'): bool
    {
        // Asegurarse de que el nivel sea válido
        if (! in_array($level, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])) {
            $level = 'info';
        }

        // Añadir información del entorno al contexto
        $context = array_merge($context, [
            'environment' => App::environment(),
            'app' => config('app.name', 'Laravel'),
            'timestamp' => now()->toIso8601String(),
        ]);

        // En ambiente de pruebas, no intentamos conectarnos a Logstash
        if (App::environment('testing')) {
            // Simplemente escribimos en el log de Laravel regular
            Log::$level($message, $context);

            return true;
        }

        // En desarrollo local, podemos optar por usar el logger normal o Logstash según configuración
        if (App::environment('local') && ! config('logging.use_logstash_in_local', false)) {
            Log::$level($message, $context);

            return true;
        }

        // En otros ambientes, usamos LogHelper normalmente
        try {
            return LogHelper::toLogstash($message, $context, $level);
        } catch (\Exception $e) {
            // Si hay un error en el LogHelper, aseguramos que al menos se guarde en el log local
            Log::error('Error al usar LogHelper: ' . $e->getMessage(), [
                'original_message' => $message,
                'original_context' => $context,
                'original_level' => $level,
                'exception' => get_class($e),
            ]);

            // Guardar el mensaje original en el log normal
            Log::$level($message, $context);

            return true; // Retornamos true para no interrumpir el flujo de la aplicación
        }
    }
}
