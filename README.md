# monev-kendaraan — Fleet Logbook & Monitoring System

**Aplikasi pengelolaan & monitoring kendaraan dinas dan ambulans** — digital journey & vehicle operational logbook (bukan aplikasi booking).

> Native PHP 8.2 · MySQL/MariaDB · Bootstrap 5 · PDO · Vanilla JS ES6+ — mobile-first untuk driver di lapangan.

## Status

| Phase | Nama | Status |
|---|---|---|
| **0** | System Blueprint | ✅ **Approved** (`0af1e4a`) — 7 keputusan binding |
| **1** | Foundation (struktur, DB, auth, layout, security) | ⏳ Dikerjakan — gate dilaporkan sebelum lanjut |
| 2–12 | Master → Trip → Driver Mobile → GPS → Expense → OCR → Documents → Verifikasi → Dashboard → Laporan → Hardening | ⏳ Belum dimulai (perlu approval) |

## Dokumen

- **[PHASE-0-SYSTEM-BLUEPRINT.md](docs/PHASE-0-SYSTEM-BLUEPRINT.md)** — arsitektur, ERD, trip lifecycle, strategi GPS/evidence/OCR/security, struktur folder & API, rencana phase, acceptance criteria, risk register.
- **[database.md](docs/database.md)** — skema `kendaraan_logbook`, migrasi, seed.
- **[testing.md](docs/testing.md)** — cara menjalankan gate & checklist manual.

## Konsep inti

```text
PENUGASAN → TRIP → START → PERJALANAN → ARRIVAL → BUKTI LOKASI
→ KEMBALI → END → BBM / E-TOLL / BIAYA → ST / SPPD
→ VERIFIKASI → PELAPORAN → EVALUASI
```

## Requirements

- PHP 8.2.x (PDO+pdo_mysql, fileinfo, mbstring, session, filter, tokenizer, ctype)
- MySQL 8 / MariaDB 10.4+
- Apache (XAMPP) — **DocumentRoot ke `public/`**

## Instalasi cepat (XAMPP)

1. Salin repo ke `htdocs/monev-kendaraan`, DocumentRoot → `public/` (atau `http://localhost/monev-kendaraan/public/`).
2. Salin `.env.example` → `.env`, isi kredensial DB (`DB_NAME=kendaraan_logbook`).
3. Buat database, lalu: `php database/migrate.php` → `php database/seeds/seed_admin.php`
   (password admin **hanya dicetak di konsol**; wajib ganti saat login pertama).
4. Aset Bootstrap 5 + ikon **di-vendor** (`public/assets/vendor/`) — tanpa CDN/ internet.

*(Detail: [docs/database.md](docs/database.md) — setup sandbox non-XAMPP: [tests/testing gate](docs/testing.md).)*

## Catatan

- Aplikasi lama hanya referensi konseptual — **tidak dicopy**, tidak dimodifikasi.
- Database baru `kendaraan_logbook`.
- `.env`, log, dan file evidence/private tidak masuk Git (lihat `.gitignore`).
