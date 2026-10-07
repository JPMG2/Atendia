<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\ModerationVerdictDto;
use App\Services\ContentModeration;
use Database\Seeders\PlanSeeder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
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

        // `once()` memoizes for the PROCESS, not for the test: a test that saved
        // a company left every later one reading that row, which is what made
        // the footer, the landing and the hub fail only in a full run.
        Once::flush();

        $this->guardAgainstProductionDatabase();
        $this->fakeLeakedPasswordCheck();
        $this->fakeContentModeration();
    }

    /**
     * Moderation asks OpenAI over the network, and the .env key reaches the
     * suite: every upload and text passes clean here — the tests that prove
     * the gate rebind a scripted verdict or the real service over Http::fake.
     */
    private function fakeContentModeration(): void
    {
        $this->app->instance(ContentModeration::class, new class extends ContentModeration
        {
            public function image(string $path): ModerationVerdictDto
            {
                return ModerationVerdictDto::clean();
            }

            public function text(string $text): ModerationVerdictDto
            {
                return ModerationVerdictDto::clean();
            }
        });
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
