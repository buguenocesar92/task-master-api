<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    /**
     * Configuración común para todos los tests
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Configurar logging para evitar conexiones a servicios externos
        Config::set('logging.default', 'null');
        Config::set('logging.channels.stack.channels', ['null']);

        // Mock the Log facade for all tests
        Log::shouldReceive('emergency')->andReturn(null)->byDefault();
        Log::shouldReceive('alert')->andReturn(null)->byDefault();
        Log::shouldReceive('critical')->andReturn(null)->byDefault();
        Log::shouldReceive('error')->andReturn(null)->byDefault();
        Log::shouldReceive('warning')->andReturn(null)->byDefault();
        Log::shouldReceive('notice')->andReturn(null)->byDefault();
        Log::shouldReceive('info')->andReturn(null)->byDefault();
        Log::shouldReceive('debug')->andReturn(null)->byDefault();
    }

    /**
     * Clean up Mockery after each test
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
