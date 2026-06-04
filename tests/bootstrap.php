<?php

declare(strict_types=1);

// Run this package's suite against the monorepo's root vendor (Symfony Mailer/Mime, PHPUnit),
// autoloading this package's src/tests and the sibling php-sdk package it depends on.

require dirname(__DIR__, 3).'/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Mailer\\Symfony\\Tests\\' => __DIR__,
        'Mailer\\Symfony\\' => dirname(__DIR__).'/src',
        'Mailer\\Sdk\\' => dirname(__DIR__, 2).'/php-sdk/src',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $baseDir.'/'.$relative.'.php';
        if (is_file($file)) {
            require $file;
        }

        return;
    }
});
