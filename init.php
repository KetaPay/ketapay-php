<?php

// For projects without Composer (for example a WordPress plugin that bundles this library):
//
//     require_once __DIR__ . '/ketapay-php/init.php';
//     $ketapay = new \KetaPay\KetaPay($secretKey);
//
// With Composer, ignore this file: `composer require ketapay/ketapay-php` sets up autoloading.

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'KetaPay\\', 8) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
