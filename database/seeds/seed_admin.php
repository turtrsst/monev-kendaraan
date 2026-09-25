<?php
/**
 * Seed akun admin awal (bootstrap).
 *
 * - Password hasilkan otomatis (acak) ATAU diberikan via --password=...
 * - Password ditampilkan SEKALI di console (bukan di UI aplikasi).
 * - Disimpan dengan password_hash() (bcrypt) — tidak pernah plaintext.
 * - force_password_change = 1 → wajib ganti password pada login pertama.
 *
 * Penggunaan:
 *   php database/seeds/seed_admin.php
 *   php database/seeds/seed_admin.php --username=admin --name="Administrator" --password=Rahasia123
 *   php database/seeds/seed_admin.php --reset-password   # ganti password lama (regenerasi acak)
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/app/core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\Env;

Env::load(BASE_PATH . '/.env');

$opts = getopt('', ['username::', 'name::', 'password::', 'reset-password']);
$username = $opts['username'] ?? 'admin';
$name = $opts['name'] ?? 'Administrator';
$explicit = $opts['password'] ?? null;
$reset = isset($opts['reset-password']);

$password = $explicit;
$passwordGenerated = false;
if ($password === null || $reset) {
    $password = bin2hex(random_bytes(8)); // 16 char acak — cukup untuk bootstrap
    $passwordGenerated = true;
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password minimal 8 karakter.\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'] ?? '127.0.0.1',
    (int)($_ENV['DB_PORT'] ?? 3306),
    $_ENV['DB_NAME'] ?? 'kendaraan_logbook'
);
try {
    $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal koneksi database: " . $e->getMessage() . "\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$roleId = (int)$pdo->query("SELECT id FROM roles WHERE slug='admin'")->fetchColumn();
if (!$roleId) {
    fwrite(STDERR, "Role admin belum ada — jalankan database/migrate.php terlebih dahulu.\n");
    exit(1);
}

$exists = false;
$st = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ? AND deleted_at IS NULL');
$st->execute([$username]);
$existing = $st->fetch();

if ($existing && !$reset && $explicit === null) {
    // Tidak menimpa password lama kecuali diminta.
    echo "Akun '$username' sudah ada. Tidak diubah (gunakan --reset-password untuk ganti).\n";
    echo "force_password_change tetap dipastikan = 1.\n";
    $pdo->prepare('UPDATE users SET force_password_change = 1, is_active = 1, role_id = ? WHERE id = ?')
        ->execute([$roleId, $existing['id']]);
    exit(0);
}

if ($existing) {
    $pdo->prepare('UPDATE users SET password_hash = ?, force_password_change = 1, is_active = 1, role_id = ?, failed_login_count = 0, locked_until = NULL WHERE id = ?')
        ->execute([$hash, $roleId, $existing['id']]);
    $exists = true;
} else {
    $pdo->prepare('INSERT INTO users (username, password_hash, name, role_id, is_active, force_password_change) VALUES (?,?,?,?,1,1)')
        ->execute([$username, $hash, $name, $roleId]);
}

echo "=== SEED ADMIN SELESAI ===\n";
echo "Username : $username\n";
echo "Role     : admin\n";
echo "Password : " . $password . "\n";
if ($passwordGenerated) {
    echo "          (password acak hasil generate — SIMPAN SEKARANG, hanya ditampilkan sekali)\n";
}
echo "Catatan  : wajib ganti password pada login pertama (force_password_change = 1).\n";
echo "           Password disimpan dengan password_hash() — tidak pernah plaintext.\n";
echo ($exists ? "Aksi     : akun diperbarui.\n" : "Aksi     : akun dibuat.\n");
exit(0);
