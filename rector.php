<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        // Nunca tocar dependencias ni vistas compiladas.
        __DIR__.'/bootstrap/cache',
        __DIR__.'/vendor',
        // A plain `!== null` on a nullable param reads clearer than the
        // inline-FQN instanceof this rule swaps in.
        FlipTypeControlToUseExclusiveTypeRector::class,
        // Livewire hydrates public properties at runtime, which static analysis
        // cannot see: a `readonly` there breaks the component.
        ReadOnlyPropertyRector::class,
    ])
    // Aplica las mejoras de sintaxis hasta la version de PHP del composer.json.
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    );
