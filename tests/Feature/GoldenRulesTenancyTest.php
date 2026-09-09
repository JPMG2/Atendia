<?php

declare(strict_types=1);

use App\Models\User;
use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — tenant isolation is a trait, not discipline
|--------------------------------------------------------------------------
| Every model whose table carries `business_id` must use BelongsToBusiness:
| the global scope plus the creating stamp are what make a cross-tenant read
| impossible instead of merely avoided. The list is not maintained by hand —
| the schema itself says which models are tenant-owned, so a future model
| (conversations, messages) fails the day it is born without the trait.
|
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md · guide: tenancy.md
*/

/**
 * Membership columns, not tenant data: scoping User by business would hide
 * the admin from itself and break auth. Never add tenant DATA models here.
 */
const TENANCY_ALLOWLIST = [User::class];

/**
 * Every Eloquent model class in app/Models.
 *
 * @return array<int, class-string<Model>>
 */
function tenancyModels(): array
{
    return collect(File::files(app_path('Models')))
        ->map(fn ($file): string => 'App\\Models\\'.$file->getFilenameWithoutExtension())
        ->filter(fn (string $class): bool => is_subclass_of($class, Model::class))
        ->values()
        ->all();
}

/**
 * The models whose table carries a business_id column — the tenant-owned ones.
 *
 * @return array<int, class-string<Model>>
 */
function tenantOwnedModels(): array
{
    return collect(tenancyModels())
        ->filter(fn (string $class): bool => Schema::hasColumn((new $class)->getTable(), 'business_id'))
        ->reject(fn (string $class): bool => in_array($class, TENANCY_ALLOWLIST, true))
        ->values()
        ->all();
}

test('every model with a business_id column carries the BelongsToBusiness trait', function (): void {
    $unguarded = collect(tenantOwnedModels())
        ->reject(fn (string $class): bool => in_array(BelongsToBusiness::class, class_uses_recursive($class), true))
        ->values();

    expect($unguarded->implode("\n"))->toBe('');
});

test('every tenant table forces row level security, table owner included', function (): void {
    $unfenced = collect(tenantOwnedModels())
        ->map(fn (string $class): string => (new $class)->getTable())
        ->reject(function (string $table): bool {
            $class = DB::selectOne('select relrowsecurity, relforcerowsecurity from pg_class where relname = ?', [$table]);
            $policies = DB::selectOne('select count(*) as total from pg_policies where tablename = ?', [$table]);

            return $class !== null && $class->relrowsecurity && $class->relforcerowsecurity && (int) $policies->total > 0;
        })
        ->values();

    expect($unfenced->implode("\n"))->toBe('');
});

test('nothing strips the business scope — no withoutGlobalScope in app or views', function (): void {
    $offenders = collect([app_path(), resource_path('views')])
        ->flatMap(fn (string $base): array => File::allFiles($base))
        ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.php'))
        ->filter(fn ($file): bool => str_contains($file->getContents(), 'withoutGlobalScope'))
        ->map(fn ($file): string => $file->getPathname())
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});
