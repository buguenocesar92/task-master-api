<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_basic_assertion(): void
    {
        $value = 1 + 1;
        $this->assertEquals(2, $value);
    }
}
