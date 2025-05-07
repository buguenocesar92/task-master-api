<?php

namespace App\Services;

use App\Helpers\LogHelper;
use App\Services\Interfaces\LoggingServiceInterface;
use Illuminate\Support\Facades\App;

class LoggingService implements LoggingServiceInterface
{
    /**
     * Envía un log al sistema centralizado
     *
     * @param string $message El mensaje principal
     * @param array $context Datos adicionales para el log
     * @param string $level Nivel de log (debug, info, warning, error, critical)
     * @return bool
     */
    public function log(string $message, array $context = [], string $level = 'info'): bool
    {
        // En ambiente de pruebas, no intentamos conectarnos a Logstash
        if (App::environment('testing')) {
            // Simplemente escribimos en el log de Laravel regular
            $logMethod = $level;
            if (!in_array($level, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])) {
                $logMethod = 'info';
            }
            \Illuminate\Support\Facades\Log::$logMethod($message, $context);
            return true;
        }

        // En otros ambientes, usamos LogHelper normalmente
        return LogHelper::toLogstash($message, $context, $level);
    }
}
