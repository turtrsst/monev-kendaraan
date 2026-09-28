# monev-kendaraan — Fleet Logbook & Monitoring System

**Aplikasi pengelolaan & monitoring kendaraan dinas dan ambulans** — digital journey & vehicle operational logbook (bukan aplikasi booking).

> Native PHP 8.2 · MySQL/MariaDB · Bootstrap 5 · PDO · Vanilla JS ES6+ — mobile-first untuk driver di lapangan.

## Status Modul

| Phase | Nama Modul | Status |
|---|---|---|
| **0** | System Blueprint | ✅ **Approved** — arsitektur & spesifikasi |
| **1** | Foundation (Kernel, Auth, Security, UI Base) | ✅ **Completed** (PR #1 merged) |
| **2** | Master Data & Assignment (Kendaraan, Driver, Ambulans, Penugasan) | 🔄 **Implemented** (Phase 2 runtime gate masih pending) |
| **3** | Trip Execution & Digital Logbook (READY → START → ARRIVAL → RETURNING → COMPLETED → SUBMITTED) | ✅ **Implemented; runtime verification not runnable in this sandbox** |
| **4–12** | Bukti Foto, Biaya/BBM/Toll, OCR, Verifikasi, Monitoring, Hardening | ⏳ TODO (Belum dimulai) |

## Dokumen Teknis

- **[docs/PHASE-0-SYSTEM-BLUEPRINT.md](docs/PHASE-0-SYSTEM-BLUEPRINT.md)** — arsitektur, ERD, trip lifecycle, strategi GPS/evidence/OCR/security.
- **[docs/PHASE-2-DATA-DESIGN.md](docs/PHASE-2-DATA-DESIGN.md)** — desain data, integritas referensial & concurrency control Phase 2.
- **[docs/PHASE-2-REPORT.md](docs/PHASE-2-REPORT.md)** — laporan implementasi dan verifikasi Phase 2; runtime gate tetap pending.
- **[docs/PHASE-3-TRIP-EXECUTION.md](docs/PHASE-3-TRIP-EXECUTION.md)** — lifecycle, transisi, keamanan, GPS event, concurrency, dan API Phase 3.
- **[docs/database.md](docs/database.md)** — skema `kendaraan_logbook`, migrasi 001–007, dan seeder.
- **[docs/api.md](docs/api.md)** — dokumentasi endpoint Web & REST API Phase 1–3.
- **[docs/testing.md](docs/testing.md)** — panduan pengujian dan gate Phase 1–3.

## Konsep Inti

```text
PENUGASAN (Phase 2) → TRIP → ASSIGNED → READY → STARTED → ARRIVED
→ RETURNING → COMPLETED → SUBMITTED

GPS hanya dicatat pada event START, ARRIVAL, dan COMPLETED; bukan tracking berkelanjutan.
```

## Requirements

- PHP 8.2.x (PDO+pdo_mysql, fileinfo, mbstring, session, filter, tokenizer, ctype)
- MySQL 8 / MariaDB 10.4+
- Web server (Apache/Nginx/PHP built-in CLI) — **DocumentRoot ke `public/`**

## Instalasi & Migrasi

1. Clone repositori dan arahkan DocumentRoot web server ke folder `public/`.
2. Salin `.env.example` menjadi `.env`, sesuaikan konfigurasi database.
3. Jalankan migrasi database:
   ```bash
   php database/migrate.php
   ```
4. Jalankan bootstrap admin & data awal:
   ```bash
   php database/seeds/seed_admin.php
   ```
5. Untuk menjalankan unit test Phase 3:
   ```bash
   php tests/unit_phase3.php
   ```
6. Terapkan migrasi hingga 007 pada database `kendaraan_logbook`, lalu jalankan integration test hanya pada database terisolasi:
   ```bash
   PHASE3_RUN_INTEGRATION=1 PHASE3_TEST_DB_OK=1 tests/run_phase3_gate.sh
   ```
7. Gate Phase 2 terdahulu tetap berstatus **runtime verification pending**. Jalankan `tests/run_phase2_gate.sh` hanya pada environment PHP 8.2+ dan MariaDB/MySQL yang disiapkan untuk pengujian; hasil Phase 3 tidak mengubah verdict Phase 2.
