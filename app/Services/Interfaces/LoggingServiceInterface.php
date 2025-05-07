<?php

namespace App\Services\Interfaces;

interface LoggingServiceInterface
{
    /**
     * Envía un log al sistema centralizado
     *
     * @param string $message El mensaje principal
     * @param array $context Datos adicionales para el log
     * @param string $level Nivel de log (debug, info, warning, error, critical)
     * @return bool
     */
    public function log(string $message, array $context = [], string $level = 'info'): bool;
}
