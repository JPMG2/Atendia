<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Nombre de la ÚNICA base de datos donde se permite testear.
     * Producción ('atendia') queda blindada: si por cualquier motivo el
     * entorno apunta a otra base, abortamos antes de tocar un solo registro.
     */
    private const TESTING_DATABASE = 'atendia_testing';

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstProductionDatabase();
    }

    private function guardAgainstProductionDatabase(): void
    {
        $env = app()->environment();
        $database = DB::connection()->getDatabaseName();

        if ($env !== 'testing' || $database !== self::TESTING_DATABASE) {
            throw new RuntimeException(
                '🛑 BLINDAJE DE TESTING: los tests solo pueden correr en entorno "testing" '
                .'sobre la base "'.self::TESTING_DATABASE.'". '
                .'Detectado entorno "'.$env.'" y base "'.$database.'". '
                .'Abortado para proteger la base de producción.'
            );
        }
    }
}
