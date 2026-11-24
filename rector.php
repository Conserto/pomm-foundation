<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/sources'])
    // No argument: Rector reads the minimum supported version from composer.json "require.php".
    // Sets must follow the minimum, not the newest PHP, so no rule produces syntax it cannot parse.
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        rectorPreset: true,
    )
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        // `@return $this` documents a fluent contract the native return type cannot express
        // without a BC break (switching to `static` would reject existing overrides).
        RemoveUselessReturnTagRector::class => [
            __DIR__ . '/sources/lib/ParameterHolder.php',
            __DIR__ . '/sources/lib/Pomm.php',
        ],
        // Custom assert* helpers in tests stay static, like PHPUnit's own assertions.
        LocallyCalledStaticMethodToNonStaticRector::class => [
            __DIR__ . '/sources/tests',
        ],
    ]);
