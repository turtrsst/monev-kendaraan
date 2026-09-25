<?php
/** @var array{title?:string, content:string, user?:array} $content — layout utama (terotentikasi) */
$user = $user ?? auth_user();
$flashes = flashes();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isLogin = $path === '/login';
$nav = [
    ['href' => '/beranda', 'label' => 'Beranda', 'icon' => 'bi-house-door'],
    ['href' => '#', 'label' => 'Perjalanan', 'icon' => 'bi-route', 'disabled' => true, 'phase' => '3'],
    ['href' => '#', 'label' => 'Monitoring', 'icon' => 'bi-broadcast-pin', 'disabled' => true, 'phase' => '10'],
    ['href' => '#', 'label' => 'Laporan', 'icon' => 'bi-file-earmark-bar-graph', 'disabled' => true, 'phase' => '11'],
];
if (($user['role'] ?? '') === 'admin') {
    $nav[] = ['href' => '/pengaturan', 'label' => 'Pengaturan', 'icon' => 'bi-gear'];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Beranda') ?> · <?= e(setting('app.name', 'Fleet Logbook')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="body-app">
<div id="offline-banner" class="offline-banner" hidden>⚠ Koneksi terputus — draft Anda tetap tersimpan di perangkat.</div>

<header class="topbar">
    <a class="topbar-brand" href="/beranda">
        <img src="<?= e(url(ltrim((string)setting('app.logo_path', 'assets/images/logo.svg'), '/'))) ?>"
             onerror="this.style.display='none'" alt="" width="30" height="30">
        <span class="topbar-title"><?= e(setting('app.hospital_name', 'Rumah Sakit')) ?></span>
    </a>
    <div class="topbar-actions">
        <span class="badge rounded-pill text-bg-secondary d-none d-sm-inline-flex" title="Role">
            <?= e($user['role_name'] ?? '') ?>
        </span>
        <div class="dropdown">
            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i> <span class="d-none d-md-inline"><?= e($user['name'] ?? '') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text small text-muted"><?= e($user['username'] ?? '') ?></span></li>
                <li><a class="dropdown-item" href="/ganti-password"><i class="bi bi-key me-2"></i>Ganti Password</a></li>
                <li>
                    <form method="post" action="/logout" class="m-0">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit">
                            <i class="bi bi-box-arrow-right me-2"></i>Keluar
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<div class="app-shell">
    <nav class="sidenav d-none d-md-flex" aria-label="Menu utama">
        <?php foreach ($nav as $item): ?>
            <?php if (!empty($item['disabled'])): ?>
                <span class="nav-item disabled" title="Modul Phase <?= e($item['phase']) ?> — menyusul">
                    <i class="bi <?= e($item['icon']) ?>"></i><?= e($item['label']) ?>
                    <span class="badge text-bg-light ms-auto phase-badge">P<?= e($item['phase']) ?></span>
                </span>
            <?php else: ?>
                <a class="nav-item<?= str_starts_with($path, $item['href']) && $item['href'] !== '#' ? ' active' : '' ?>"
                   href="<?= e($item['href']) ?>">
                    <i class="bi <?= e($item['icon']) ?>"></i><?= e($item['label']) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="sidenav-foot small text-muted">
            Phase 1 · Foundation<br>Fleet Logbook v2
        </div>
    </nav>

    <main class="content">
        <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= e($f['type'] === 'error' ? 'danger' : $f['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
</div>

<nav class="bottomnav d-md-none" aria-label="Menu bawah">
    <?php foreach (array_slice($nav, 0, 4) as $i => $item): ?>
        <?php if (!empty($item['disabled'])): ?>
            <span class="bottomnav-item disabled"><i class="bi <?= e($item['icon']) ?>"></i><small><?= e($item['label']) ?></small></span>
        <?php else: ?>
            <a class="bottomnav-item<?= str_starts_with($path, $item['href']) ? ' active' : '' ?>" href="<?= e($item['href']) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i><small><?= e($item['label']) ?></small>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<!-- Peringatan sesi (hanya muncul saat user aktif & mendekati timeout) -->
<div id="session-warning" class="session-warning" hidden role="alertdialog" aria-labelledby="sw-title">
    <div class="session-warning-card">
        <strong id="sw-title">Sesi akan berakhir</strong>
        <p class="small mb-2">Anda akan keluar otomatis karena tidak ada aktivitas.</p>
        <button id="session-stay" class="btn btn-sm btn-primary w-100" type="button">Tetap masuk</button>
    </div>
</div>
<div id="session-expired" class="session-warning" hidden role="alertdialog">
    <div class="session-warning-card">
        <strong>Sesi berakhir</strong>
        <p class="small mb-2">Silakan masuk kembali. Draft formulir Anda tetap tersimpan di perangkat.</p>
        <a class="btn btn-sm btn-primary w-100" href="/login">Masuk lagi</a>
    </div>
</div>

<div id="toast-region" aria-live="polite"></div>
<script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
