# Testing — Gate Phase 1

## Menjalankan gate penuh

```bash
# Prasyarat: binary PHP 8.2 (bootstrap sandbox: /home/user/build/opt/php/bin/php)
#            MariaDB aktif (atau biarkan runner menyalakannya bila binary tersedia)

PHP_BIN=/home/user/build/opt/php/bin/php tests/run_phase1_gate.sh
```

Keluaran: `tests/RESULTS-phase1-gate.txt` + ringkasan `[PASS]/[FAIL]` di stdout.
Exit code `0` = **semua PASS**.

Tahapan gate:

1. **Runtime PHP** — versi ≥ 8.2 + ekstensi wajib (`pdo_mysql`, `mbstring`, `session`, `fileinfo`, `filter`, `tokenizer`, `ctype`, `posix`).
2. **Database** — MariaDB running → buat DB/user → `php database/migrate.php` → `seed_admin.php --reset-password` (password bootstrap **dirotasi tiap gate**, hanya untuk gate ini, tidak disimpan di repo/UI).
3. **Unit** — `tests/unit_phase1.php`: konfigurasi, `e()`/XSS, CSRF, validator, koneksi PDO + `time_zone +07:00`, tabel + migrasi, seed (role/RS/radius/struk non-blocking), `SettingsService`, audit, throttle, timeout sesi (idle 30 mnt & absolute 720 mnt; heartbeat = satu-satunya yang memperpanjang aktivitas), identifier guard, logger, struktur.
4. **HTTP** — `tests/http_phase1.sh` (server `php -S`):
   - redirect guest/auth, form login + CSRF,
   - POST tanpa CSRF → **403 `csrf_invalid`**,
   - SQLi username → 422 (bukan 500, tanpa detail DB),
   - XSRF/refleksi → tidak dipantulkan mentah,
   - login gagal → pesan generik; **5× gagal → 429**,
   - login sukses → `/api/auth/me` 200 + `force_password_change` true,
   - **gate ganti-password**: `/beranda` 403, heartbeat tetap 200 (whitelisted),
   - ganti password → gate lolos → `/beranda` 200, `/pengaturan` 200,
   - POST `/pengaturan` tanpa CSRF → 403,
   - API tanpa sesi → 401; logout → sesi mati → 401,
   - `/.env` 404/403; `storage/logs` tak terjangkau; security headers; halaman 404 tanpa stack trace; audit `LOGIN_SUCCESS` tercatat.
5. **Security checks** — `.env` tidak di-commit; rahasia tak bocor ke file ter-track; **tanpa CDN** (aset vendor lokal); nama RS tidak hard-coded; `.htaccess` storage; seeder `password_hash()`; tidak ada query konkatenasi `$_GET/$_POST`; form semua punya `_csrf`; vendor bootstrap/ikon ada; tanpa file >2 MB ter-track.

## Checklist manual (produk, dilakukan per fase)

- [ ] Login admin → dipaksa ganti password → lanjut.
- [ ] Nama RS & logo dari **Pengaturan** (bukan hard-coded); logo tampil di login & navbar.
- [ ] Radius default 100 m; ubah via Pengaturan memengaruhi verifikasi tujuan (fase berikutnya).
- [ ] Form BBM: foto struk **opsional** — tanpa struk tetap bisa selesai, hanya peringatan.
- [ ] Sesi: diam 30 menit → expired; ada peringatan sebelumnya; **keep-alive hanya saat user aktif**; draft form selamat saat sesi berakhir (localStorage).
- [ ] Driver flow di HP (360px): navigasi bawah, tombol besar, tanpa zoom.
- [ ] Aksi trip idempotent (tekan ganda/tombol mundur tidak membuat data ganda) — fase perjalanan.

## Konvensi

- Gate per fase: **PASS/FAIL + daftar file + migrasi + cek keamanan** → berhenti menunggu approval.
- Test assertion ditulis sekali di `tests/`, bukan manual spreadsheet.
- Waktu selalu Asia/Jakarta; kegagalan dievaluasi dari `storage/logs/app-YYYY-MM-DD.log`.
