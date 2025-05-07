<?php

namespace Tests\Unit\Services;

use App\Services\LoggingService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class LoggingServiceTest extends TestCase
{
    /**
     * Test que el servicio de logs registra mensajes correctamente.
     */
    public function testLoggingServiceRecordsMessages(): void
    {
        // Crear un mock para Log
        $logMock = Mockery::mock('alias:Illuminate\Support\Facades\Log');
        $logMock->shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Test message' &&
                       isset($context['test']) &&
                       $context['test'] === true;
            });

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
        // Niveles de log a probar
        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

        foreach ($levels as $level) {
            // Crear un mock para Log
            $logMock = Mockery::mock('alias:Illuminate\Support\Facades\Log');
            $logMock->shouldReceive($level)
                ->once()
                ->withArgs(function ($message, $context) use ($level) {
                    return $message === "Test {$level} message" &&
                           isset($context['level']) &&
                           $context['level'] === $level;
                });

            // Instanciar el servicio de logging
            $logger = new LoggingService;

            // Ejecutar el método de log con el nivel correspondiente
            $result = $logger->log("Test {$level} message", ['level' => $level], $level);

            // Verificar el resultado
            $this->assertTrue($result);

            // Limpiar el mock para evitar interferencias con el siguiente nivel
            Mockery::close();
        }
    }

    /**
     * Limpiar mocks después de cada test
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
