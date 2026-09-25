<?php

/**
 * Bootstrap test unit Phase 1 (CLI).
 * Menjalankan: php tests/unit_phase1.php [--admin-password=xxx]
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
ob_start(); // CLI: tunda output — session_start() butuh headers belum terkirim
require BASE_PATH . '/app/core/Autoloader.php';
require BASE_PATH . '/app/helpers/general.php';
require BASE_PATH . '/app/helpers/log.php';
App\Core\Autoloader::register();

date_default_timezone_set('Asia/Jakarta');

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
$GLOBALS['__results'] = [];

function check(string $name, bool $cond, string $detail = ''): void
{
    if ($cond) {
        $GLOBALS['__pass']++;
        $GLOBALS['__results'][] = ['PASS', $name, $detail];
        echo "[PASS] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    } else {
        $GLOBALS['__fail']++;
        $GLOBALS['__results'][] = ['FAIL', $name, $detail];
        echo "[FAIL] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

/* ---------------------------------------------------------------- */
echo "=== PHASE 1 UNIT TESTS ===\n\n";

/* 1. Konfigurasi */
check('config: timezone Asia/Jakarta', config('app.timezone') === 'Asia/Jakarta', (string)config('app.timezone'));
check('config: session idle = 1800 dtk (30 mnt)', (int)config('session.idle_seconds') === 1800);
check('config: session absolute = 43200 dtk (720 mnt)', (int)config('session.absolute_seconds') === 43200);
check('config: DB name benar', config('db.name') === 'kendaraan_logbook');
check('config: login max attempts = 5', (int)config('security.login_max_attempts') === 5);
check('config: .env TIDAK di-commit', !shell_exec('cd ' . escapeshellarg(BASE_PATH) . ' && git ls-files --error-unmatch .env 2>/dev/null'));

/* 2. XSS helper */
$evil = '<script>alert(1)</script>"\'&';
$escaped = e($evil);
check('e(): escape HTML', $escaped === '&lt;script&gt;alert(1)&lt;/script&gt;&quot;&#039;&amp;', $escaped);
check('e(): tanpa tag mentah', !str_contains($escaped, '<script>'));

/* 3. CSRF token */
$token = csrf_token();
check('csrf: token 64 hex', (bool)preg_match('/^[a-f0-9]{64}$/', $token), substr($token, 0, 12) . '…');
check('csrf: verify token benar', App\Core\Session::verifyCsrf($token));
check('csrf: tolak token salah', !App\Core\Session::verifyCsrf($token . 'x'));
check('csrf: tolak token kosong', !App\Core\Session::verifyCsrf(''));
check('csrf: tolak null', !App\Core\Session::verifyCsrf(null));

/* 4. Validator */
$errors = App\Validators\AuthValidator::login('', '');
check('validator: login wajib isi', isset($errors['username'], $errors['password']));
$errors = App\Validators\AuthValidator::login('ab', 'x');
check('validator: username min 3', isset($errors['username']));
$errors = App\Validators\AuthValidator::login('bad user!', 'x');
check('validator: username charset', isset($errors['username']));
$errors = App\Validators\AuthValidator::login('admin.ok_1', 'secret');
check('validator: login valid', $errors === []);

$hash = password_hash('Secret123', PASSWORD_DEFAULT);
$errors = App\Validators\AuthValidator::changePassword('wrong', 'Secret456', 'Secret456', $hash);
check('validator: password salah saat ini', isset($errors['current']));
$errors = App\Validators\AuthValidator::changePassword('Secret123', 'short', 'short', $hash);
check('validator: password baru min 8', isset($errors['new']));
$errors = App\Validators\AuthValidator::changePassword('Secret123', 'allletters', 'allletters', $hash);
check('validator: password harus ada angka', isset($errors['new']));
$errors = App\Validators\AuthValidator::changePassword('Secret123', 'Secret456', 'Secret789', $hash);
check('validator: konfirmasi harus sama', isset($errors['confirm']));
$errors = App\Validators\AuthValidator::changePassword('Secret123', 'Secret123', 'Secret123', $hash);
check('validator: password baru ≠ lama', isset($errors['new']));
$errors = App\Validators\AuthValidator::changePassword('Secret123', 'NewPass456', 'NewPass456', $hash);
check('validator: ganti password valid', $errors === []);

/* 5. Database — koneksi & tabel */
try {
    $pdo = App\Core\DB::pdo();
    check('db: koneksi PDO (emulasi prepare OFF)', $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) !== true);
    check('db: time_zone koneksi = +07:00', (string)App\Core\DB::scalar("SELECT @@session.time_zone") === '+07:00');
} catch (Throwable $e) {
    check('db: koneksi PDO', false, $e->getMessage());
    $pdo = null;
}

if ($pdo !== null) {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['roles', 'users', 'audit_logs', 'login_attempts', 'settings', 'notifications', 'schema_migrations'] as $t) {
        check("db: tabel `$t` ada", in_array($t, $tables, true));
    }
    $pending = [];
    foreach (glob(BASE_PATH . '/database/migrations/*.sql') as $f) {
        $n = basename($f);
        $applied = (bool)App\Core\DB::scalar('SELECT COUNT(*) FROM schema_migrations WHERE filename = ?', [$n]);
        if (!$applied) {
            $pending[] = $n;
        }
    }
    check('db: semua migrasi diterapkan', $pending === [], implode(',', $pending));

    /* 6. Seed */
    $roleCount = (int)App\Core\DB::scalar('SELECT COUNT(*) FROM roles');
    check('seed: 4 role (admin/operator/driver/pimpinan)', $roleCount === 4, "jumlah=$roleCount");
    $admin = App\Models\User::findByUsername('admin');
    check('seed: akun admin ada', $admin !== null);
    if ($admin !== null) {
        check('seed: admin role=admin', $admin['role_slug'] === 'admin');
        check('seed: admin force_password_change=1', !empty($admin['force_password_change']));
        check('seed: admin password memakai password_hash (bcrypt/argon2)',
            (bool)preg_match('/^\$(2y|2b|2a|argon2)/', (string)$admin['password_hash']));
        check('seed: password BUKAN plaintext (bukan tebakan umum)',
            !in_array((string)$admin['password_hash'], ['admin', 'password', 'admin123', '123456'], true));
        $pw = getopt('', ['admin-password::'])['admin-password'] ?? getenv('TEST_ADMIN_PASSWORD') ?: '';
        if ($pw !== '') {
            check('seed: password_verify(TEST_ADMIN_PASSWORD) cocok', password_verify($pw, (string)$admin['password_hash']));
        }
    }
    $hospital = App\Core\DB::scalar("SELECT value FROM settings WHERE skey = 'app.hospital_name'");
    check('seed: hospital = RSUP dr. Soeradji Tirtonegoro Klaten', $hospital === 'RSUP dr. Soeradji Tirtonegoro Klaten', (string)$hospital);
    $radius = App\Core\DB::scalar("SELECT value FROM settings WHERE skey = 'trip.destination_radius_default_m'");
    check('seed: radius default = 100 m', $radius === '100', (string)$radius);
    $fuelReq = App\Core\DB::scalar("SELECT value FROM settings WHERE skey = 'fuel.receipt_photo_required'");
    check('seed: foto struk BBM NON-blocking (0)', $fuelReq === '0', (string)$fuelReq);
    $logo = App\Core\DB::scalar("SELECT value FROM settings WHERE skey = 'app.logo_path'");
    check('seed: logo via setting (bukan hard-code)', is_string($logo) && $logo !== '');

    /* 7. Settings service */
    App\Services\SettingsService::flush();
    check('settings: get logo_path', App\Services\SettingsService::get('app.logo_path') === $logo);
    check('settings: getInt radius', App\Services\SettingsService::getInt('trip.destination_radius_default_m') === 100);
    check('settings: getBool struk non-blocking', App\Services\SettingsService::getBool('fuel.receipt_photo_required') === false);

    /* 8. Audit */
    $before = (int)App\Core\DB::scalar('SELECT COUNT(*) FROM audit_logs');
    App\Services\AuditService::log('TEST_EVENT', 'unit_test', '1', ['a' => 1], ['b' => 2]);
    $after = (int)App\Core\DB::scalar('SELECT COUNT(*) FROM audit_logs');
    check('audit: log tersimpan', $after === $before + 1);
    $row = App\Core\DB::fetch("SELECT * FROM audit_logs WHERE action='TEST_EVENT' ORDER BY id DESC LIMIT 1");
    check('audit: old_data/new_data JSON terisi', $row !== null && str_contains((string)$row['old_data'], '"a":1'));
    App\Core\DB::run("DELETE FROM audit_logs WHERE action='TEST_EVENT'");

    /* 9. Login attempt throttle data layer */
    $ip = '203.0.113.10';
    App\Models\LoginAttempt::record('throttle_user', $ip, false);
    App\Models\LoginAttempt::record('throttle_user', $ip, false);
    $fails = App\Models\LoginAttempt::recentFailures('throttle_user', $ip, 15);
    check('throttle: hitung kegagalan 15 menit', $fails === 2, "count=$fails");
    check('throttle: tidak bocor ke user lain', App\Models\LoginAttempt::recentFailures('other_user', $ip, 15) === 0);
    App\Core\DB::run('DELETE FROM login_attempts WHERE username = ?', ['throttle_user']);

    /* 10. Session timeout (simulasi data sesi) */
    $restart = static function (): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_start(); // CLI session file
        $_SESSION = [];
    };
    $restart();
    $_SESSION['_auth_user'] = ['id' => 1, 'username' => 'x', 'name' => 'x', 'role' => 'driver', 'role_name' => 'Driver', 'force_password_change' => false];
    $_SESSION['_login_at'] = time();
    $_SESSION['_last_activity'] = time() - 1801; // > 30 menit idle
    check('session: idle 30 menit → expired', App\Core\Session::checkTimeouts() === false);
    // ulang untuk absolute timeout
    $restart();
    $_SESSION['_auth_user'] = ['id' => 1, 'username' => 'x', 'name' => 'x', 'role' => 'driver', 'role_name' => 'Driver', 'force_password_change' => false];
    $_SESSION['_login_at'] = time() - 43201;
    $_SESSION['_last_activity'] = time(); // aktif tapi lewat absolute
    check('session: absolute 720 menit → expired', App\Core\Session::checkTimeouts() === false);
    // activity TIDAK diperpanjang oleh checkTimeouts biasa
    $restart();
    $_SESSION['_auth_user'] = ['id' => 1, 'username' => 'x', 'name' => 'x', 'role' => 'driver', 'role_name' => 'Driver', 'force_password_change' => false];
    $_SESSION['_login_at'] = time();
    $_SESSION['_last_activity'] = time() - 900;
    App\Core\Session::checkTimeouts();
    check('session: checkTimeouts TIDAK memperpanjang idle',
        $_SESSION['_last_activity'] === time() - 900,
        'last_activity tidak berubah'
    );
    // heartbeat MEMPERPANJANG
    App\Core\Session::markActivity();
    check('session: markActivity (heartbeat) memperbarui last_activity',
        $_SESSION['_last_activity'] >= time() - 1
    );
    $_SESSION = [];
    session_write_close();

    /* 11. Identifier guard (anti SQL injection pada helper DB) */
    try {
        App\Core\DB::insert('users; DROP TABLE users', ['x' => 1]);
        check('db: tolak identifier berbahaya', false);
    } catch (InvalidArgumentException $e) {
        check('db: tolak identifier berbahaya', true);
    }
}

/* 12. Logger */
logger('info', 'unit test log line');
$logFile = BASE_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log';
check('log: file log harian dibuat', is_file($logFile));

/* 13. Autoloader / struktur */
check('struktur: public/index.php ada', is_file(BASE_PATH . '/public/index.php'));
check('struktur: docroot publik TIDAK memuat .env', !is_file(BASE_PATH . '/public/.env'));
check('struktur: tidak ada file >800 baris di app/ & modules/',
    (function () {
        foreach (['app', 'modules', 'api'] as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/' . $dir));
            foreach ($it as $f) {
                if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                    if (count(file($f->getPathname())) > 800) {
                        return false;
                    }
                }
            }
        }
        return true;
    })());

/* ---------------------------------------------------------------- */
echo "\n=== RINGKASAN: " . $GLOBALS['__pass'] . " PASS, " . $GLOBALS['__fail'] . " FAIL ===\n";
file_put_contents(
    BASE_PATH . '/tests/RESULTS-unit-phase1.txt',
    implode("\n", array_map(static fn ($r) => implode("\t", $r), $GLOBALS['__results'])) . "\n"
);
exit($GLOBALS['__fail'] === 0 ? 0 : 1);
