<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets()
    ->withTypeCoverageLevel(level: 1)
    ->withDeadCodeLevel(level: 1)
    ->withCodeQualityLevel(level: 1)
    ->withImportNames(removeUnusedImports: true);
