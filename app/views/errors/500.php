<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>500 · Kesalahan server</title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="body-error"><main class="error-wrap">
<div class="error-code">500</div>
<h1>Terjadi kesalahan</h1>
<p><?= e($message ?? 'Kesalahan server. Silakan coba lagi atau hubungi administrator.') ?></p>
<p class="small text-muted">Detail teknis hanya dicatat di log server — tidak ditampilkan untuk keamanan.</p>
<a class="btn btn-primary" href="/beranda">Kembali</a>
</main></body></html>
