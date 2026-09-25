# monev-kendaraan — Fleet Logbook & Monitoring System

**Aplikasi pengelolaan & monitoring kendaraan dinas dan ambulans** — digital journey & vehicle operational logbook (bukan aplikasi booking).

> Native PHP 8.2 · MySQL/MariaDB · Bootstrap 5 · PDO · Vanilla JS ES6+ — mobile-first untuk driver di lapangan.

## Status Modul

| Phase | Nama Modul | Status |
|---|---|---|
| **0** | System Blueprint | ✅ **Approved** — arsitektur & spesifikasi |
| **1** | Foundation (Kernel, Auth, Security, UI Base) | ✅ **Completed** (PR #1 merged) |
| **2** | Master Data & Assignment (Kendaraan, Driver, Ambulans, Penugasan) | 🔄 **Implemented** (Awaiting Runtime Gate Approval) |
| **3** | Trip Lifecycle & State Machine (START, ARRIVAL, END) | ⏳ TODO (Belum dimulai) |
| **4–12** | GPS, Bukti Foto, Biaya/BBM/Toll, OCR, Verifikasi, Monitoring, Hardening | ⏳ TODO (Belum dimulai) |

## Dokumen Teknis

- **[docs/PHASE-0-SYSTEM-BLUEPRINT.md](docs/PHASE-0-SYSTEM-BLUEPRINT.md)** — arsitektur, ERD, trip lifecycle, strategi GPS/evidence/OCR/security.
- **[docs/PHASE-2-DATA-DESIGN.md](docs/PHASE-2-DATA-DESIGN.md)** — desain data, integritas referensial & concurrency control Phase 2.
- **[docs/PHASE-2-REPORT.md](docs/PHASE-2-REPORT.md)** — laporan implementasi dan verifikasi Phase 2.
- **[docs/database.md](docs/database.md)** — skema `kendaraan_logbook`, daftar tabel, migrasi 001–006, dan seeder.
- **[docs/api.md](docs/api.md)** — dokumentasi lengkap endpoint Web & REST API Phase 1 & 2.
- **[docs/testing.md](docs/testing.md)** — panduan pengujian dan eksekusi gate.

## Konsep Inti

```text
PENUGASAN (Phase 2) → TRIP (Phase 3) → START → PERJALANAN → ARRIVAL → BUKTI LOKASI
→ KEMBALI → END → BBM / E-TOLL / BIAYA → ST / SPPD
→ VERIFIKASI → PELAPORAN → EVALUASI
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
5. Untuk menguji Phase 2:
   ```bash
   tests/run_phase2_gate.sh
   ```
