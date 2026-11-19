<?php

declare(strict_types=1);

use App\Dto\SensorReadingDto;
use App\Enums\PlatformSensorStatus as Status;
use App\Services\PlatformSensors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| The nightly backups leave a status file each; the board shows the worst.
| On 19/11/2026 the full bundle failed at 03:50 and nothing was watching it.
*/

beforeEach(function (): void {
    Http::fake();
    // A storage of its own: the real status files of the server are never touched.
    $this->storage = sys_get_temp_dir().'/backup-sensor-'.uniqid();
    File::makeDirectory($this->storage.'/app', 0755, true);
    app()->useStoragePath($this->storage);
});

afterEach(function (): void {
    File::deleteDirectory($this->storage);
});

function writeBackupStatus(string $file, array $status): void
{
    file_put_contents(storage_path('app/'.$file), json_encode($status));
}

function backupReading(): SensorReadingDto
{
    return collect(app(PlatformSensors::class)->read())->firstWhere('key', 'backup');
}

function allBackupsFresh(): void
{
    foreach (['backup-status.json', 'backup-full-status.json', 'backup-eloquent-status.json'] as $file) {
        writeBackupStatus($file, ['at' => now()->subHour()->toIso8601String(), 'bytes' => 1_048_576]);
    }
}

it('is green only when the three nightly backups are fresh', function (): void {
    allBackupsFresh();

    expect(backupReading()->status)->toBe(Status::Ok);
});

it('turns red at once when one backup reports a failure, naming which and where', function (): void {
    allBackupsFresh();
    writeBackupStatus('backup-full-status.json', [
        'at' => now()->subHours(5)->toIso8601String(),
        'failed_at' => now()->subMinutes(10)->toIso8601String(),
        'step' => 'datos de Redis',
    ]);

    $reading = backupReading();

    expect($reading->status)->toBe(Status::Crit)
        ->and($reading->detail)->toContain(__('platform_events.detail.backup_kinds.full'))
        ->and($reading->detail)->toContain('datos de Redis');
});

it('goes back to green when a later run succeeds after the failure', function (): void {
    allBackupsFresh();
    writeBackupStatus('backup-eloquent-status.json', [
        'at' => now()->toIso8601String(),
        'failed_at' => now()->subHour()->toIso8601String(),
        'step' => 'volcado',
    ]);

    expect(backupReading()->status)->toBe(Status::Ok);
});

it('warns when one backup stops running, even if the others are fresh', function (): void {
    allBackupsFresh();
    writeBackupStatus('backup-eloquent-status.json', ['at' => now()->subHours((int) config('atendia.health.backup_warn_hours') + 2)->toIso8601String()]);

    $reading = backupReading();

    expect($reading->status)->toBe(Status::Warn)
        ->and($reading->detail)->toContain(__('platform_events.detail.backup_kinds.eloquent'));
});

it('never shows green while one backup has no record at all', function (): void {
    writeBackupStatus('backup-status.json', ['at' => now()->toIso8601String()]);
    writeBackupStatus('backup-full-status.json', ['at' => now()->toIso8601String()]);

    expect(backupReading()->status)->toBe(Status::Unknown);
});
