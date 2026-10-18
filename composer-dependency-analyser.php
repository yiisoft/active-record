<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = (new Configuration())
    ->disableComposerAutoloadPathScan()
    ->setFileExtensions(['php'])
    ->addPathToScan(__DIR__ . '/config', isDev: false)
    ->addPathToScan(__DIR__ . '/src', isDev: false)
    ->addPathToScan(__DIR__ . '/tests', isDev: true)
    // These are optional (suggested) dependencies used unconditionally in opt-in traits;
    // consumers who don't `use` the trait don't need the package. See the "suggest" section
    // in composer.json.
    ->ignoreErrorsOnPackages(
        ['psr/log', 'yiisoft/arrays', 'yiisoft/event-dispatcher', 'yiisoft/factory'],
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
    // psr/event-dispatcher is the PSR interface backing the optional yiisoft/event-dispatcher
    // integration above; it's only needed when a consumer wires that integration up.
    ->ignoreErrorsOnPackages(
        ['psr/event-dispatcher'],
        [ErrorType::SHADOW_DEPENDENCY],
    )
    // config/bootstrap.php is only invoked by a DI container, so a PSR-11 implementation
    // is always present at runtime even though this package doesn't require one itself.
    ->ignoreErrorsOnPackageAndPath(
        'psr/container',
        __DIR__ . '/config/bootstrap.php',
        [ErrorType::SHADOW_DEPENDENCY],
    );

if (PHP_VERSION_ID < 80200) {
    // Native PHP attribute available since PHP 8.2; not autoloadable on lower PHP versions,
    // which are still within the supported range (see "php" in composer.json).
    $config->ignoreUnknownClasses(['AllowDynamicProperties']);
}

return $config;
