<?php
/** @var array{title?:string, content:string} $content — layout guest (halaman publik) */
$flashes = flashes();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Masuk') ?> · <?= e(setting('app.name', 'Fleet Logbook')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="body-guest">
<main class="guest-wrap">
    <?= $content ?>
</main>
<div id="toast-region" aria-live="polite"></div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
