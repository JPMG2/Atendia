<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The ONLY database testing is allowed to touch. The working one is shielded:
     * if the environment ever points elsewhere, this aborts before a single row
     * is touched.
     */
    private const string TESTING_DATABASE = 'atendia_testing';

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
