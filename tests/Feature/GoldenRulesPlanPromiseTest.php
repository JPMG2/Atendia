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
