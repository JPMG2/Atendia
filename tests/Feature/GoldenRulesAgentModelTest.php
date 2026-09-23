<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;

/*
|--------------------------------------------------------------------------
| Golden rule: no temperature on reasoning models
|--------------------------------------------------------------------------
| The gpt-6 family answers "temperature is not supported" with a 400. Five
| agents carried #[Temperature(0.0)] and failed in production while every
| test passed on fakes (found 2026-09-23 through the message triage).
*/

test('no agent on a gpt-6 model declares a temperature', function (): void {
    $offenders = [];

    foreach (File::files(app_path('Ai/Agents')) as $file) {
        $class = 'App\\Ai\\Agents\\'.$file->getFilenameWithoutExtension();
        $reflection = new ReflectionClass($class);
        $model = $reflection->getAttributes(Model::class)[0] ?? null;

        if ($model !== null
            && str_starts_with((string) $model->getArguments()[0], 'gpt-6')
            && $reflection->getAttributes(Temperature::class) !== []) {
            $offenders[] = $class;
        }
    }

    expect($offenders)->toBe([]);
});
