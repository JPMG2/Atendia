<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden rule: the name field comes first (the verifiable slice)
|--------------------------------------------------------------------------
| Field order is judgement (formularios.md §5.7), but one slice is
| deterministic: when a blade holds the name="name" field AND a file drop or
| textarea, the name must appear before them — the name identifies the
| record, media and prose describe it. Born 2026-09-06: the identity card
| shipped with the logo above the business name.
|
| Mirrored by the hook .claude/hooks/check-field-order.sh: touch one, touch
| the other.
*/

test('the name field precedes any file drop or textarea in every blade', function (): void {
    $offenders = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $relative = str_replace(resource_path('views').'/', '', $file->getPathname());

        // The component library defines the controls; the rule binds their users.
        if (str_starts_with($relative, 'components/inputsform/') || str_starts_with($relative, 'components/ui/')) {
            continue;
        }

        $content = $file->getContents();

        $namePosition = strpos($content, 'name="name"');

        if ($namePosition === false) {
            continue;
        }

        preg_match('/<x-(?:inputsform\.(?:file|textarea)|ui\.textarea)/', $content, $match, PREG_OFFSET_CAPTURE);

        if ($match !== [] && $match[0][1] < $namePosition) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([]);
});
