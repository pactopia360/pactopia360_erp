<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\InstallTestSchemas;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Instala los esquemas de prueba (SQLite en memoria)
        InstallTestSchemas::up();
    }
}
