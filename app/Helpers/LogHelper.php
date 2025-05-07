<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Clase para gestionar el envío de logs al sistema centralizado ELK.
 *
 * Este helper proporciona una interfaz para enviar logs directamente a Logstash,
 * garantizando la entrega incluso en caso de problemas de conexión temporales.
 *
 * Para obtener más información sobre el sistema de logging, consulta docs/logging.md
 *
 * @example
 * // Uso básico
 * LogHelper::toLogstash('Mensaje simple');
 *
 * // Con contexto adicional
 * LogHelper::toLogstash('Usuario creado', ['id' => 1, 'email' => 'user@example.com']);
 *
 * // Log de error
 * LogHelper::toLogstash('Error de validación', ['errores' => $errores], 'error');
 */
class LogHelper
{
    /**
     * Envía un log directamente a Logstash usando socket TCP
     *
     * Este método establece una conexión directa con Logstash y envía un mensaje formateado
     * en JSON para su procesamiento en la stack ELK.
     *
     * @param  string  $message  El mensaje de log principal
     * @param  array  $context  Datos adicionales para enriquecer el log
     * @param  string  $level  Nivel de log (debug, info, warning, error, critical)
     * @return bool Retorna true si el mensaje fue enviado correctamente, false en caso contrario
     */
    public static function toLogstash($message, array $context = [], $level = 'info')
    {
        try {
            // Obtenemos la configuración de conexión de Logstash desde el archivo de configuración
            $connectionString = Config::get('logging.channels.logstash.handler_with.connectionString');
            $timeout = Config::get('logging.channels.logstash.handler_with.timeout', 0.5);

            // Parsear la dirección de conexión
            $parts = parse_url($connectionString);
            if (! $parts || ! isset($parts['host']) || ! isset($parts['port'])) {
                Log::error("Configuración de conexión a Logstash inválida: $connectionString");

                return self::fallbackLog($message, $context, $level);
            }

            $host = $parts['host'];
            $port = $parts['port'];

            // Intentar conexión con timeout reducido
            $socket = @fsockopen('tcp://' . $host, $port, $errno, $errstr, $timeout);
            if (! $socket) {
                Log::warning("Error al conectar a Logstash ($host:$port): $errstr ($errno)");

                return self::fallbackLog($message, $context, $level);
            }

            $payload = [
                'message' => $message,
                '@timestamp' => date('c'),
                'level' => $level,
                'context' => $context,
                'app' => config('app.name', 'Laravel'),
                'env' => config('app.env', 'production'),
            ];

            fwrite($socket, json_encode($payload) . "\n");
            fclose($socket);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar log a Logstash: ' . $e->getMessage());

            return self::fallbackLog($message, $context, $level);
        }
    }

    /**
     * Método de respaldo para guardar logs cuando Logstash no está disponible
     */
    private static function fallbackLog($message, array $context, $level)
    {
        // Aseguramos que el nivel sea válido
        if (! in_array($level, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])) {
            $level = 'info';
        }

        // Usar el logger de Laravel (usualmente single o daily)
        Log::$level($message, $context);

        // Retornamos true para no interrumpir el flujo de la aplicación
        return true;
    }
}
