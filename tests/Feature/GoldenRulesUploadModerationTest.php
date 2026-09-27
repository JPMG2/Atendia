<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden rule: nothing a business uploads or writes skips moderation
|--------------------------------------------------------------------------
| Images go through AttributeValidator::imageUpload() (formats moderation
| can see + the SafeUpload gate); texts through the knowledge document
| observer. Born 2026-09-27 with zero offenders. Mirrored by the hook
| check-upload-moderation-golden-rules.sh — touch one, touch the other.
| Out of scope on purpose: the admin's own forms (Configuration, Admin).
*/

const UPLOAD_MIMES_PATTERN = '/mimes:[^\'"]*\b(png|jpe?g|webp|gif|svg|pdf)\b/';
const UPLOAD_IMAGE_RULE_PATTERN = '/[\'"](image|file)[\'"]\s*[,\]]/';

/** @return list<string> */
function tenantUploadSources(): array
{
    return collect([...File::allFiles(app_path()), ...File::allFiles(resource_path('views'))])
        ->map(fn ($file): string => $file->getPathname())
        ->filter(fn (string $path): bool => str_ends_with($path, '.php'))
        ->reject(fn (string $path): bool => str_ends_with($path, 'Rules/AttributeValidator.php')
            || str_contains($path, 'Livewire/Forms/Configuration/')
            || str_contains($path, 'Livewire/Forms/Admin/'))
        ->values()
        ->all();
}

test('no tenant upload declares image formats by hand', function (): void {
    $offenders = collect(tenantUploadSources())
        ->filter(fn (string $path): bool => preg_match(UPLOAD_MIMES_PATTERN, File::get($path)) === 1)
        ->map(fn (string $path): string => str_replace(base_path().'/', '', $path))
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});

test('no tenant form validates a bare image or file rule', function (): void {
    $offenders = collect(tenantUploadSources())
        ->filter(fn (string $path): bool => str_contains($path, 'Livewire/Forms/'))
        ->filter(fn (string $path): bool => preg_match(UPLOAD_IMAGE_RULE_PATTERN, File::get($path)) === 1)
        ->map(fn (string $path): string => str_replace(base_path().'/', '', $path))
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});

test('every knowledge document change is screened, like it is indexed', function (): void {
    $observer = File::get(app_path('Observers/KnowledgeDocumentObserver.php'));

    expect(substr_count($observer, 'ModerateKnowledgeDocument::dispatch('))
        ->toBe(substr_count($observer, 'IndexKnowledgeDocument::dispatch('));
});
