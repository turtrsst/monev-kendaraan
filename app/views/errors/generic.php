<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kesalahan</title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="body-error"><main class="error-wrap">
<div class="error-code"><?= (int)($status ?? 500) ?></div>
<h1>Terjadi kesalahan</h1>
<p><?= e($message ?? 'Silakan coba lagi.') ?></p>
<a class="btn btn-primary" href="/beranda">Kembali</a>
</main></body></html>
