<?php

namespace App\Helpers;

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
            $socket = @fsockopen('tcp://logstash-dev', 5000, $errno, $errstr, 3);
            if (! $socket) {
                Log::error("Error al conectar a Logstash: $errstr ($errno)");

                return false;
            }

            $payload = [
                'message' => $message,
                '@timestamp' => date('c'),
                'level' => $level,
                'context' => $context,
            ];

            fwrite($socket, json_encode($payload) . "\n");
            fclose($socket);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar log a Logstash: ' . $e->getMessage());

            return false;
        }
    }
}
