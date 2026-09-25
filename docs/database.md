# Database — `kendaraan_logbook`

> MySQL 8 / MariaDB 10.4+ (sandbox dibangun: MariaDB 11.4). Waktu simpan: **Asia/Jakarta**
> (`SET time_zone='+07:00'` per koneksi — lihat `App\Core\DB`).
> **DB lama `kendaraan_app`/`Z:\sias\kendaraan-app` TIDAK digunakan/diubah.**

## Cara menjalankan (XAMPP / sandbox)

```bash
# 1. Buat database (atau biarkan migrator yang membuat)
mysql -uroot -e "CREATE DATABASE kendaraan_logbook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 2. Isi .env (salin dari .env.example) — .env TIDAK pernah di-commit
#    DB_NAME=kendaraan_logbook, DB_USER=root (atau user khusus)

# 3. Migrasi (idempotent, tercatat di tabel schema_migrations)
php database/migrate.php

# 4. Seed awal (roles + settings + admin bootstrap)
php database/seeds/seed_admin.php
#    → password bootstrap hanya dicetak di KONSOLE, tidak pernah ditampilkan di UI.
#    Akun admin wajib ganti password saat login pertama (force_password_change=1).
```

Snapshot skema (gabungan migrasi) ada di `database/schema.sql` — untuk import cepat,
**tetapi jalankan `database/migrate.php`** agar tercatat & idempotent.

## Migrasi

| File | Isi |
|---|---|
| `001_init_foundation.sql` | Tabel foundation: `roles`, `users`, `audit_logs`, `login_attempts`, `sessions`, `schema_migrations`, `settings`, `notifications`, `password_resets` |
| `002_seed_foundation.sql` | 4 role (admin/operator/driver/pimpinan), pengaturan default (nama RS, radius 100 m, struk BBM non-blocking, dsb.) |

Tabel perjalanan/vehicle/assignment/laporan (fase 2+) menyusul di migrasi lanjutan —
**tanpa ENUM, tanpa foreign key migrasi** (disengaja agar aman di MySQL 5.7/XAMPP;
relasi tetap dipertahankan secara aplikasi).

## Tabel foundation (ringkas)

- **roles** — `slug` (admin/operator/driver/pimpinan), `permissions` JSON. *Role configurable, bukan ENUM.*
- **users** — `username` unique, `password_hash` (`password_hash()`), `force_password_change`,
  `driver_license_no`, `phone`, `is_active`. Kolom PII (KTP/SIM) wajib di-hash/enkripsi di fase berikutnya.
- **audit_logs** — siapa, apa, entitas, `old_data`/`new_data` JSON, IP, UA, `created_at`.
- **login_attempts** — throttle 5 percobaan / 15 menit (per user + IP).
- **settings** — `skey`/`svalue`; dibaca via `SettingsService` (cache in-memory per request).
  Nama RS, logo, radius tujuan, dsb. **dari sini — bukan hard-coded.**
- **notifications** — in-app (belum ada email di fase ini; arsitektur siap SMTP nanti).
- **schema_migrations** — migrasi yang sudah diterapkan.

## Aturan keamanan data

- Kredensial DB hanya di `.env` (tidak di-commit; `.env.example` tanpa rahasia).
- Semua query memakai **prepared statement** (PDO, `ATTR_EMULATE_PREPARES=false`).
- Identifier (tabel/kolom) divalidasi `App\Core\DB::identifier()`.
- Evidence foto/GPS (fase berikutnya) disimpan di `storage/private/` — **tidak pernah di `public/`**.
- Waktu selalu Asia/Jakarta; server adalah sumber kebenaran waktu.
