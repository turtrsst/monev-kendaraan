<?php

/**
 * Front controller — SEMUA request web masuk lewat sini.
 * Apache docroot menunjuk ke folder ini (lihat README).
 */

declare(strict_types=1);

// CLI server (php -S): biarkan aset statis dilayani langsung oleh server
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_string($path) && $path !== '/' && !str_ends_with($path, '.php')) {
        $file = __DIR__ . $path;
        if (is_file($file)) {
            return false;
        }
    }
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/core/Autoloader.php';
require BASE_PATH . '/app/helpers/general.php';
require BASE_PATH . '/app/helpers/log.php';

use App\Core\App;
use App\Core\Autoloader;

Autoloader::register();

App::run();
