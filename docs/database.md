# Database — `kendaraan_logbook`

> Dokumen ini **mencerminkan implementasi Phase 1 yang aktual** (audit terhadap
> `database/migrations/`, `database/schema.sql`, `database/migrate.php`,
> `app/core/DB.php`, dan `app/models/`).
> Target: MySQL 8 / MariaDB 10.4+ (sandbox dibangun: MariaDB 11.4). Engine **InnoDB**,
> charset `utf8mb4`, collation `utf8mb4_unicode_ci`.
> **DB lama `kendaraan_app` / `Z:\sias\kendaraan-app` TIDAK digunakan/diubah.**

## Environment & zona waktu

- **Database:** `kendaraan_logbook` (dari `DB_NAME` di `.env`).
- **APP timezone = Asia/Jakarta** (`APP_TIMEZONE`, default config `Asia/Jakarta`;
  boot aplikasi menjalankan `date_default_timezone_set()`).
- **Connection timezone handling:** setiap koneksi PDO menjalankan
  `SET time_zone = '+07:00'` (di `App\Core\DB::pdo()`), sehingga `NOW()`,
  `CURRENT_TIMESTAMP`, dan kolom `DATETIME` dengan `DEFAULT CURRENT_TIMESTAMP`
  / `ON UPDATE CURRENT_TIMESTAMP` konsisten ditulis dalam WIB.
- File migrasi juga men-set `SET time_zone = '+07:00'` di bagian atas file.
- Kredensial DB hanya di `.env` (tidak di-commit; `.env.example` tanpa rahasia).

## Migrasi — mekanisme & idempotency

Cara menjalankan (XAMPP / sandbox):

```bash
# 1. Buat database (atau biarkan migrator yang membuat)
mysql -uroot -e "CREATE DATABASE kendaraan_logbook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 2. Isi .env (salin dari .env.example) — .env TIDAK pernah di-commit
#    DB_NAME=kendaraan_logbook, DB_USER=root (atau user khusus)

# 3. Migrasi (idempotent, tercatat di tabel schema_migrations)
php database/migrate.php            # mode default --run
php database/migrate.php --status   # daftar file: [OK] / [PENDING]
php database/migrate.php --fresh    # HANCURKAN semua tabel (CLI eksplisit, berbahaya)

# 4. Seed akun admin bootstrap (TERPISAH dari migrasi, CLI only)
php database/seeds/seed_admin.php
#    → password bootstrap hanya dicetak di KONSOLE, tidak pernah ditampilkan di UI.
#    Akun admin wajib ganti password saat login pertama (force_password_change=1).
```

**Tabel `schema_migrations`** dibuat oleh `database/migrate.php` (bootstrap, bukan oleh
file migrasi): kolom `filename VARCHAR(191)` **PRIMARY KEY** + `applied_at DATETIME`.

**Cara kerja idempotency:**

1. Migrator membaca semua `database/migrations/*.sql`, diurutkan (`sort`).
2. Nama file yang sudah ada di `schema_migrations` → `[SKIP]` (tidak dieksekusi ulang).
3. File baru dieksekusi penuh (`PDO::exec` multi-statement), lalu namanya dicatat
   (`INSERT INTO schema_migrations`) — **catatan dilakukan SETELAH sukses**.
4. Jika file gagal di tengah, file tidak tercatat → eksekusi ulang aman karena semua
   statement memakai `CREATE TABLE IF NOT EXISTS` / seed idempoten
   (`ON DUPLICATE KEY UPDATE`).
5. `--fresh` men-drop semua tabel (termasuk `schema_migrations`) dan mengulang dari nol.

**Snapshot:** `database/schema.sql` = gabungan 001 + 002 (terverifikasi identik dengan
file migrasi) untuk import cepat — **tetapi jalankan `database/migrate.php`** agar
tercatat & idempotent.

**Seed dalam migrasi (`002_seed_foundation.sql`):** 4 role
(`admin`/`operator`/`driver`/`pimpinan`, permissions JSON) + settings default
(nama RS `RSUP dr. Soeradji Tirtonegoro Klaten`, radius 100 m, struk BBM non-blocking,
dst.). Idempoten lewat `ON DUPLICATE KEY UPDATE`. **Akun admin TIDAK di-seed di sini**
(hash tidak pernah masuk Git) — bagian dari `database/seeds/seed_admin.php`.

Tabel Phase 2+ (trips, vehicles, assignments, laporan, …) menyusul di migrasi lanjutan.
Pola Phase 1 yang berlaku: **tanpa ENUM** (role berasal dari tabel `roles` yang
configurable) dan **foreign key eksplisit** bila relasi antar tabel dibutuhkan
(Phase 1 sudah memakai FK — lihat §Foreign Keys).

## Tabel aktual Phase 1

Enam tabel dibuat oleh `001_init_foundation.sql`, plus `schema_migrations` yang dibuat
migrator (total 7 tabel setelah `php database/migrate.php`).
**Tabel `sessions` dan `password_resets` TIDAK ada di Phase 1** (sesi memakai
file session PHP native; mekanisme reset password belum diimplementasikan).

### `roles` — daftar role aplikasi (configurable, bukan ENUM)

- **Purpose:** master role + daftar permission (`permissions` JSON, nullable).
- **PK:** `id`
- **Unique:** `uq_roles_slug (slug)`
- **Index lain:** — (PK/unique saja)
- **FK:** tidak ada.
- Seed: `admin`, `operator`, `driver`, `pimpinan`.

### `users` — akun pengguna

- **Purpose:** kredensial (`password_hash` via `password_hash()`), role, status aktif,
  paksa-ganti-password, throttle login (`failed_login_count`, `locked_until`),
  soft delete (`deleted_at`).
- **PK:** `id`
- **Unique:** `uq_users_username (username)`
- **Index:** `idx_users_role (role_id)`, `idx_users_active (is_active, deleted_at)`
- **FK:** `fk_users_role` → `roles(id)` (restrict/ default).
- Catatan: kolom PII (KTP/SIM) wajib di-hash/enkripsi di phase berikutnya (belum ada
  kolom tersebut di Phase 1).

### `audit_logs` — jejak audit aktivitas penting

- **Purpose:** siapa, apa (`action`), entitas (`entity`, `entity_id`),
  `old_data`/`new_data` JSON, `ip`, `user_agent`, waktu.
- **PK:** `id`
- **Index:** `idx_audit_action_time (action, created_at)`,
  `idx_audit_entity (entity, entity_id)`, `idx_audit_user (user_id, created_at)`
- **FK:** `fk_audit_user` → `users(id)` **ON DELETE SET NULL** (riwayat tetap ada
  walau akun dihapus).
- Ditulis hanya dari server (`AuditService`), bukan dari input client.

### `login_attempts` — data throttle login

- **Purpose:** mencatat setiap percobaan login (`success` 0/1) per `username` + `ip`
  untuk throttle 5 gagal / 15 menit (default, configurable via settings).
- **PK:** `id`
- **Index:** `idx_attempts_user_time (username, attempted_at)`,
  `idx_attempts_ip_time (ip, attempted_at)`
- **FK:** tidak ada — **sengaja**: percobaan untuk username yang tidak dikenal pun
  tetap tercatat.

### `settings` — konfigurasi aplikasi (bukan hard-code)

- **Purpose:** `skey` → `value` (TEXT) + `description`, `updated_by`, `updated_at`.
  Nama RS, logo, radius tujuan, dsb. **dibaca dari sini** (`SettingsService`,
  cache in-memory per request) — bukan hard-coded.
- **PK:** `skey`
- **Unique:** melekat pada PK.
- **FK:** `fk_settings_user` → `users(id)` **ON DELETE SET NULL** (`updated_by`).
- Ditulis admin via `POST /pengaturan` (whitelist kunci — lihat `docs/api.md`).

### `notifications` — notifikasi in-app

- **Purpose:** notifikasi dalam aplikasi (`type`, `title`, `body`, `link`, `is_read`,
  `read_at`) — email/SMTP belum ada di fase ini.
- **PK:** `id`
- **Index:** `idx_notifications_user (user_id, is_read, created_at)`
- **FK:** `fk_notifications_user` → `users(id)` **ON DELETE CASCADE**.

### `schema_migrations` — catatan migrasi (bootstrap migrator)

- **Purpose:** daftar file migrasi yang sudah diterapkan.
- **PK:** `filename`
- **FK:** tidak ada.

## Foreign Keys (aktual — 4 buah)

| Constraint | Kolom | Referensi | ON DELETE |
|---|---|---|---|
| `fk_users_role` | `users.role_id` | `roles(id)` | (default/restrict) |
| `fk_audit_user` | `audit_logs.user_id` | `users(id)` | `SET NULL` |
| `fk_settings_user` | `settings.updated_by` | `users(id)` | `SET NULL` |
| `fk_notifications_user` | `notifications.user_id` | `users(id)` | `CASCADE` |

Dokumen ini **tidak** lagi mengklaim "tanpa foreign key" — Phase 1 foundation memakai
FK di atas; migrasi berikutnya menambahkan FK sesuai relasinya masing-masing.

## Database access layer (`App\Core\DB`)

API yang **benar-benar digunakan** oleh source code Phase 1:

```php
DB::pdo(): PDO                                  // koneksi singleton (ERRMODE_EXCEPTION, emulate OFF)
DB::run(string $sql, array $params): PDOStatement
DB::fetch(string $sql, array $params): ?array
DB::fetchAll(string $sql, array $params): array
DB::scalar(string $sql, array $params): mixed
DB::insert(string $table, array $data): int     // return lastInsertId
DB::update(string $table, array $data, string $where, array $whereParams): int
DB::lastInsertId(): int
DB::transaction(callable $fn): mixed
```

- **Koreksi nama API:** nama `DB::identifier()` yang pernah tercantum di dokumen ini
  **tidak ada** di implementasi. Validasi identifier dilakukan metode **privat
  `DB::assertIdentifier()`** (regex `^[A-Za-z_][A-Za-z0-9_]*$`), dipanggil otomatis
  oleh `DB::insert()`/`DB::update()` untuk nama tabel & kolom.
- **Semua query memakai prepared statement** (`PDO::ATTR_EMULATE_PREPARES = false`,
  `ATTR_STRINGIFY_FETCHES = false`) — nilai user tidak pernah diinterpolasi ke SQL.
- Model Phase 1 yang memakai layer ini: `User`, `Role`, `Setting`, `LoginAttempt`,
  `AuditLog`, `Notification` (lihat `app/models/`).

## Transaction behavior

- Helper aktual: **`DB::transaction(callable $fn): mixed`** — `beginTransaction()`,
  mengeksekusi callback, `commit()`; jika callback melempar exception → `rollBack()`
  otomatis (selama masih dalam transaksi) lalu exception diteruskan; nilai kembalian
  callback dikembalikan ke pemanggil.
- **Phase 1: belum ada pemanggilan `DB::transaction()`** — seluruh operasi foundation
  (login attempt, update password, audit, settings) bersifat per-statement tunggal.
  Helper sudah tersedia dan wajib dipakai untuk operasi multi-tabel di phase berikutnya.

## Aturan keamanan data

- Kredensial DB hanya di `.env` (tidak di-commit; `.env.example` tanpa rahasia).
- Semua query lewat **prepared statement**; identifier tabel/kolom divalidasi
  `DB::assertIdentifier()` (bukan `DB::identifier()`).
- Evidence foto/GPS (phase berikutnya) disimpan di `storage/private/` —
  **tidak pernah di `public/`**.
- Waktu selalu Asia/Jakarta; server adalah sumber kebenaran waktu.
