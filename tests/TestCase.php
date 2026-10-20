<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\ModerationVerdictDto;
use App\Services\CertificateReader;
use App\Services\ContentModeration;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The ONLY database testing is allowed to touch. The working one is shielded:
     * if the environment ever points elsewhere, this aborts before a single row
     * is touched.
     */
    private const string TESTING_DATABASE = 'atendia_testing';

    /** @var array<class-string, true> */
    private static array $checkedClasses = [];

    /**
     * Rows per table when the run began, taken after the rebuild and its seeds.
     *
     * @var array<string, int>|null
     */
    private static ?array $baseline = null;

    private static bool $leakReported = false;

    private static ?string $lastTest = null;

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
        $this->fakeCertificateReader();

        // A new report asks the AI for a draft over the network, and the .env key
        // reaches the suite: off here, and the tests that prove it turn it on against a faked agent.
        config()->set('atendia.support.ai_suggestions', 0);

        // Enforced here, report-only in production: a browser test that needs an
        // origin the policy forgot fails now, and not for a visitor later.
        config()->set('atendia.csp.mode', 'enforce');

        // Once per class, and before its own setup: a row already here was
        // committed by something that ran earlier in this same run.
        if (! isset(self::$checkedClasses[static::class]) && in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            self::$checkedClasses[static::class] = true;
            $this->assertNoCommittedRowsLeft();
        }
    }

    protected function tearDown(): void
    {
        self::$lastTest = static::class.'::'.$this->name();

        parent::tearDown();
    }

    /**
     * Fails, once per run, when a table holds more rows than when the run began.
     *
     * Those rows make a test pass alone and fail in the full suite. The baseline
     * is the first look, just after the rebuild, so what the rebuild seeds is
     * never a leak. The message names the last test that finished before it.
     */
    protected function assertNoCommittedRowsLeft(): void
    {
        if (self::$leakReported) {
            return;
        }

        $counts = $this->tableRowCounts();

        if (self::$baseline === null) {
            self::$baseline = $counts;

            return;
        }

        $leaked = collect($counts)
            ->filter(fn (int $rows, string $table): bool => $rows > (self::$baseline[$table] ?? 0))
            ->keys()
            ->map(fn (string $table): string => Str::afterLast($table, '.'))
            ->all();

        if ($leaked === []) {
            return;
        }

        self::$leakReported = true;

        throw new RuntimeException(
            'Committed rows are left in "'.self::TESTING_DATABASE.'" ('.implode(', ', $leaked).') before '.static::class.'. '
            .'A test that ran earlier in this run did not roll back; the last one to finish was '
            .(self::$lastTest ?? 'none').'. Look for DB::commit(), a second connection, or a process that writes on its own.'
        );
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
     * The certificate sensor opens a TLS socket to the real Traefik; the suite
     * never does. Every certificate is two months from expiry here — the test
     * that proves the thresholds rebinds its own reader.
     */
    private function fakeCertificateReader(): void
    {
        $this->app->instance(CertificateReader::class, new class extends CertificateReader
        {
            public function expiresAt(string $connectTo, string $serverName, int $port = 443): CarbonImmutable
            {
                return CarbonImmutable::now()->addDays(60);
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

    /**
     * Rows per table, in one query: the guard looks once per class and the suite has hundreds.
     *
     * @return array<string, int>
     */
    private function tableRowCounts(): array
    {
        $grammar = DB::connection()->getQueryGrammar();

        $probes = collect(Schema::getTableListing())
            ->map(fn (string $table): string => 'select '.$grammar->quoteString($table).' as t, count(*) as n from '.$grammar->wrapTable($table));

        if ($probes->isEmpty()) {
            return [];
        }

        return collect(DB::select($probes->implode(' union all ')))
            ->mapWithKeys(fn (object $row): array => [$row->t => (int) $row->n])
            ->all();
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
