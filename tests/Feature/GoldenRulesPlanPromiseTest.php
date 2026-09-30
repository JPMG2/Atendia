<?php

declare(strict_types=1);

use App\Classes\Main\Plan;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden rule: a figure we sell has a lock that makes it true
|--------------------------------------------------------------------------
| The single-source rule keeps every screen reading the same figure; it never
| made the figure TRUE. "2 WhatsApp numbers" rode from the landing to "Mi
| plan" for months with nothing in the code honouring it — the owner caught
| it, not the suite (2026-09-29). Every dial a card sells lands in exactly
| one bucket below, so a promise can no longer travel alone.
*/

/**
 * The dial's accessors on `Plan`: a declared lock has to actually read one,
 * so renaming or gutting the lock turns this red.
 *
 * @var array<string, list<string>>
 */
const DIALS = [
    'conversations' => ['conversationsPerMonth'],
    'seats' => ['teamSeats', 'canOfferSeatTo', 'canFillOfferedSeat'],
    'pace' => ['messagesPerHour'],
    'audio' => ['allowsAudio', 'audioMinutesPerMonth'],
    'statistics' => ['statisticsAtLeast', 'statisticsLevel'],
    'photos' => ['catalogPhotos', 'photosPerItem'],
    'departments' => ['hasDepartments'],
    'media' => ['readsMedia'],
    'ask' => ['allowsAsk', 'askPerMonth'],
];

/**
 * Where each sold dial is made true.
 *
 * @var array<string, list<string>>
 */
const LOCKS = [
    'seats' => ['app/Models/Business.php', 'app/Actions/Team/InviteTeamMember.php', 'app/Actions/Team/AcceptTeamInvitation.php'],
    'pace' => ['app/Jobs/ProcessIncomingWhatsAppMessage.php'],
    'audio' => ['app/Jobs/ProcessIncomingWhatsAppMessage.php'],
    'media' => ['app/Jobs/ProcessIncomingWhatsAppMessage.php'],
    'statistics' => ['resources/views/components/statistics/⚡index.blade.php', 'app/Ai/Tools/OwnerStatistics.php'],
    'photos' => ['app/Livewire/Forms/Catalog/CatalogPhotoForm.php'],
    'departments' => ['app/Ai/Tools/EscalateToHuman.php', 'app/Classes/Main/Team.php'],
    'ask' => ['resources/views/components/client/⚡ask-atendia.blade.php'],
];

/**
 * Sold soft ON PURPOSE: the cap is a figure, not a wall. Each one carries the
 * decision behind it.
 *
 * @var array<string, string>
 */
const SOFT = [
    'conversations' => 'The assistant never stops answering at the cap: that WhatsApp is the client cash register. It warns the owner at 80% instead.',
];

/**
 * Sold with nothing behind it. The ratchet: an entry LEAVES this list when its
 * lock gets built, and adding one is a deliberate edit somebody has to make. It
 * was born holding `numbers` and reached zero the same day.
 *
 * @var array<string, string>
 */
const PENDING = [];

/** A place that prints the figure is not a lock: it can never be declared as one. */
const PRINTS_ONLY = [
    'app/Ai/Tools/OwnerPlanUsage.php',
    'resources/views/components/plan/',
];

/**
 * A card also sells lines that are not a dial ("Agenda o catálogo"): they ride
 * `landing.pricing.{code}.extras` and the buckets above never saw them. Each
 * line lands here with the file that makes it true.
 *
 * @var array<string, list<string>>
 */
const EXTRAS_LOCKS = [
    // Kept by there being NO plan dial: the agenda answers to the business's
    // own switch, and the test below fails the day someone gates it.
    'Agenda y catálogo' => ['app/Classes/Main/Agenda.php'],
    'Resumen diario y reportes a pedido' => [
        'app/Console/Commands/SendWhatsAppDigests.php',
        'app/Console/Commands/SendKnowledgeDigests.php',
    ],
];

/**
 * Sold with nothing behind it, said out loud. NOT the same debt as a dial's:
 * capping what a card offers is the owner's call, so the audit reports these
 * and she decides (skill `client`, point 11). The ratchet still holds — a line
 * only LEAVES when its lock is built, and a new one cannot be sold without
 * landing in one of the two lists.
 *
 * @var array<string, string>
 */
const EXTRAS_PENDING = [
    'Tu equipo entra cuando hace falta' => 'Carried by the seats dial, which IS locked; kept declared so the line cannot drift away from it.',
    'Tu asistente a tu medida' => 'Work we do by hand for that plan, not something code can hold.',
    'Configuración asistida incluida' => 'Work we do by hand for that plan, not something code can hold.',
];

/** @return list<string> */
function soldDials(): array
{
    return collect(Plan::ladder())
        ->flatMap(fn (Plan $plan): array => array_column($plan->features, 'key'))
        ->unique()
        ->values()
        ->all();
}

test('every figure a plan card sells says where it stands: locked, soft on purpose, or pending', function (): void {
    $unclassified = array_values(array_diff(
        soldDials(),
        array_keys(LOCKS),
        array_keys(SOFT),
        array_keys(PENDING),
    ));

    expect($unclassified)->toBe([]);
});

test('every sold figure names the accessor its lock has to read', function (): void {
    expect(array_values(array_diff(soldDials(), array_keys(DIALS))))->toBe([]);
});

test('every declared lock exists and still reads its dial', function (): void {
    $broken = [];

    foreach (LOCKS as $key => $files) {
        foreach ($files as $file) {
            $path = base_path($file);

            if (! File::exists($path)) {
                $broken[] = "{$key}: {$file} is gone";

                continue;
            }

            $source = File::get($path);

            if (! collect(DIALS[$key])->contains(fn (string $dial): bool => str_contains($source, $dial))) {
                $broken[] = "{$key}: {$file} no longer reads the dial";
            }
        }
    }

    expect($broken)->toBe([]);
});

test('a place that only prints the figure is never declared as its lock', function (): void {
    $printers = collect(LOCKS)
        ->flatten()
        ->filter(fn (string $file): bool => collect(PRINTS_ONLY)->contains(fn (string $printer): bool => str_starts_with($file, $printer)))
        ->values()
        ->all();

    expect($printers)->toBe([]);
});

test('nothing is sold without a lock', function (): void {
    expect(array_keys(PENDING))->toBe([]);
});

test('every extra line a landing card sells is classified too', function (): void {
    $sold = collect(Plan::ladder())
        ->flatMap(fn (Plan $plan): array => (array) __("landing.pricing.{$plan->code}.extras"))
        ->filter(fn (mixed $line): bool => is_string($line))
        ->values();

    $unclassified = $sold
        ->reject(fn (string $line): bool => array_key_exists($line, EXTRAS_LOCKS) || array_key_exists($line, EXTRAS_PENDING))
        ->values()
        ->all();

    expect($unclassified)->toBe([]);
});

test('the agenda stays outside the plan ladder, as its card promises', function (): void {
    // "Agenda y catálogo" is kept by the ABSENCE of a dial: the agenda answers
    // to the business's own switch. Gate it behind a plan and the copy becomes
    // a lie, so this fails first.
    expect(File::get(base_path('app/Classes/Main/Agenda.php')))->not->toContain('Plan');
});

test('the digest commands read the dial that sells them', function (): void {
    // File-based like its siblings: this guardian judges the code, not a row.
    expect(File::get(base_path('app/Classes/Main/Plan.php')))->toContain('hasDailyDigest');

    foreach (EXTRAS_LOCKS['Resumen diario y reportes a pedido'] as $command) {
        expect(File::get(base_path($command)))->toContain('hasDailyDigest');
    }
});

test('every declared extra lock exists and is not a place that only prints', function (): void {
    $broken = [];

    foreach (EXTRAS_LOCKS as $line => $files) {
        foreach ($files as $file) {
            if (! File::exists(base_path($file))) {
                $broken[] = "{$line}: {$file} is gone";
            }

            if (collect(PRINTS_ONLY)->contains(fn (string $printer): bool => str_starts_with($file, $printer))) {
                $broken[] = "{$line}: {$file} only prints the promise";
            }
        }
    }

    expect($broken)->toBe([]);
});
