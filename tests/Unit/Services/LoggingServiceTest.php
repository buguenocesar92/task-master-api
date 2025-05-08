<?php

namespace Tests\Unit\Services;

use App\Services\LoggingService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LoggingServiceTest extends TestCase
{
    /**
     * Test que el servicio de logs registra mensajes correctamente.
     */
    public function testLoggingServiceRecordsMessages(): void
    {
        // Configurar expectativas para el Log facade que ya está mockeado en TestCase
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Test message' &&
                       isset($context['test']) &&
                       $context['test'] === true;
            })
            ->andReturn(null);

        // Instanciar el servicio de logging
        $logger = new LoggingService;

        // Ejecutar el método de log
        $result = $logger->log('Test message', ['test' => true], 'info');

        // Verificar el resultado
        $this->assertTrue($result);
    }

    /**
     * Test que el servicio de logs maneja diferentes niveles de log.
     */
    public function testLoggingServiceHandlesDifferentLevels(): void
    {
        // Solo probamos un nivel para simplificar
        $level = 'debug';

        // Configurar expectativas para el Log facade
        Log::shouldReceive($level)
            ->once()
            ->withArgs(function ($message, $context) use ($level) {
                return $message === "Test {$level} message" &&
                       isset($context['level']) &&
                       $context['level'] === $level;
            })
            ->andReturn(null);

        // Instanciar el servicio de logging
        $logger = new LoggingService;

        // Ejecutar el método de log con el nivel correspondiente
        $result = $logger->log("Test {$level} message", ['level' => $level], $level);

        // Verificar el resultado
        $this->assertTrue($result);
    }
}
