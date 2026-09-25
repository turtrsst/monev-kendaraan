<?php
/**
 * Migration runner — CLI.
 * Penggunaan:
 *   php database/migrate.php           # jalankan migrasi yang belum diterapkan
 *   php database/migrate.php --status  # tampilkan status
 *   php database/migrate.php --fresh   # HAPUS semua tabel aplikasi lalu migrasi ulang (hati-hati!)
 *
 * Migrasi dicatat di tabel schema_migrations (idempotent).
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/core/Autoloader.php';
require BASE_PATH . '/app/helpers/log.php';
\App\Core\Autoloader::register();

use App\Core\Env;
use App\Services\SettingsService; // (tidak dipakai di sini, kebutuhan autoload saja)

Env::load(BASE_PATH . '/.env');

function connect(): PDO {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'] ?? '127.0.0.1',
        (int)($_ENV['DB_PORT'] ?? 3306),
        $_ENV['DB_NAME'] ?? 'kendaraan_logbook'
    );
    return new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function tableExists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}

$mode = $argv[1] ?? '--run';

try {
    $pdo = connect();
    echo "Koneksi OK → " . ($_ENV['DB_NAME'] ?? 'kendaraan_logbook') . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "GAGAL koneksi database: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Pastikan .env benar dan server MySQL/MariaDB berjalan.\n");
    exit(1);
}

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
sort($files);

if ($mode === '--status') {
    foreach ($files as $f) {
        $name = basename($f);
        echo (in_array($name, $applied, true) ? '[OK]      ' : '[PENDING] ') . $name . "\n";
    }
    exit(0);
}

if ($mode === '--fresh') {
    echo "!! --fresh: menghapus semua tabel aplikasi...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rows as $t) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $t) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    echo "   " . count($rows) . " tabel dihapus.\n";
    $applied = [];
}

$ran = 0;
foreach ($files as $f) {
    $name = basename($f);
    if (in_array($name, $applied, true)) {
        echo "[SKIP]    $name (sudah diterapkan)\n";
        continue;
    }
    $sql = file_get_contents($f);
    if ($sql === false) {
        fwrite(STDERR, "[GAGAL]   $name tidak terbaca\n");
        exit(1);
    }
    try {
        $pdo->exec($sql);
        $st = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
        $st->execute([$name]);
        echo "[APPLY]   $name\n";
        $ran++;
    } catch (Throwable $e) {
        fwrite(STDERR, "[GAGAL]   $name → " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Selesai. " . count($files) . " file migrasi, " . $ran . " diterapkan baru.\n";
exit(0);
