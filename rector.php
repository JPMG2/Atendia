<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

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
    ])
    // Aplica las mejoras de sintaxis hasta la version de PHP del composer.json.
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
