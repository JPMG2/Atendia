<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\PlanSeeder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
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

    /**
     * Plans are catalog data every screen and gate reads: RefreshDatabase
     * seeds them once, when it rebuilds the test database.
     */
    protected bool $seed = true;

    protected string $seeder = PlanSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstProductionDatabase();
        $this->fakeLeakedPasswordCheck();
    }

    /**
     * Password::defaults() asks HIBP over the network whether a password
     * leaked; the suite must never depend on that. Every password passes
     * here — the test that proves the gate rebinds a failing verifier.
     */
    private function fakeLeakedPasswordCheck(): void
    {
        $this->app->bind(UncompromisedVerifier::class, fn (): UncompromisedVerifier => new class implements UncompromisedVerifier
        {
            public function verify($data): bool
            {
                return true;
            }
        });
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
