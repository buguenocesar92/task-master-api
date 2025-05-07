<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

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
    }
}
