<?php

declare(strict_types=1);

// Works both as a standalone repository (its own vendor/) and inside the monorepo, where the
// packages share the root vendor/ instead of installing their own.
$standalone = dirname(__DIR__).'/vendor/autoload.php';
$monorepo = dirname(__DIR__, 3).'/vendor/autoload.php';

require is_file($standalone) ? $standalone : $monorepo;

// In the monorepo neither this package nor its sibling SDK is installed, so map both namespaces at
// their source. Composer's autoloader is registered first, so this never shadows a real install.
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'PufferPost\\Symfony\\Tests\\' => __DIR__,
        'PufferPost\\Symfony\\' => dirname(__DIR__).'/src',
        'PufferPost\\Sdk\\' => dirname(__DIR__, 2).'/php-sdk/src',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $baseDir.'/'.str_replace('\\', '/', substr($class, \strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }

        return;
    }
});
