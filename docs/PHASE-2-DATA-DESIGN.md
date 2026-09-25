# Phase 2 — Data Design & Architecture Specification

Dokumen ini mendefinisikan desain skema database, aturan validasi, penanganan konkurensi, dan kebijakan otorisasi untuk implementasi Phase 2 (Master Data Kendaraan, Driver, Profil Ambulans, dan Manajemen Penugasan) pada sistem **Fleet Logbook & Monitoring System**.

---

## 1. Skema Database Phase 2

Target: MySQL 8 / MariaDB 10.4+  
Engine: InnoDB  
Charset: `utf8mb4`  
Collation: `utf8mb4_unicode_ci`  
Timezone: `Asia/Jakarta (+07:00)`

### 1.1 Tabel `vehicles` (Master Kendaraan)
Menyimpan data master semua kendaraan operasional dan ambulans.

```sql
CREATE TABLE IF NOT EXISTS vehicles (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_code      VARCHAR(32)  NOT NULL,
    plate_number      VARCHAR(20)  NOT NULL,
    vehicle_name      VARCHAR(100) NOT NULL,
    vehicle_type      VARCHAR(50)  NOT NULL DEFAULT 'OPERASIONAL',
    ownership         VARCHAR(50)  NOT NULL DEFAULT 'DINAS',
    year              SMALLINT UNSIGNED NULL,
    stnk_expiry       DATE         NULL,
    kir_expiry        DATE         NULL,
    status            VARCHAR(20)  NOT NULL DEFAULT 'ACTIVE',
    current_odometer  INT UNSIGNED NOT NULL DEFAULT 0,
    notes             TEXT         NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicles_code (vehicle_code),
    UNIQUE KEY uq_vehicles_plate (plate_number),
    KEY idx_vehicles_status (status),
    KEY idx_vehicles_type (vehicle_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Status Kendaraan:**
* `ACTIVE`: Kendaraan aktif siap pakai / siap ditugaskan.
* `MAINTENANCE`: Kendaraan sedang dalam perbaikan / servis bengkel.
* `INACTIVE`: Kendaraan sedang diistirahatkan / nonaktif operasional.
* `RETIRED`: Kendaraan sudah purna tugas / dihapus dari daftar operasional dinas.

---

### 1.2 Tabel `drivers` (Master Pengemudi)
Menyimpan data master driver dinas dan driver ambulans.

```sql
CREATE TABLE IF NOT EXISTS drivers (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id            INT UNSIGNED NULL,
    driver_code        VARCHAR(32)  NOT NULL,
    name               VARCHAR(120) NOT NULL,
    phone              VARCHAR(32)  NOT NULL,
    license_type       VARCHAR(20)  NOT NULL DEFAULT 'SIM A',
    license_number     VARCHAR(50)  NOT NULL,
    license_expiry     DATE         NOT NULL,
    status             VARCHAR(20)  NOT NULL DEFAULT 'ACTIVE',
    notes              TEXT         NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_drivers_code (driver_code),
    UNIQUE KEY uq_drivers_user_id (user_id),
    KEY idx_drivers_status (status),
    KEY idx_drivers_license_expiry (license_expiry),
    CONSTRAINT fk_drivers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Status Driver:**
* `ACTIVE`: Pengemudi aktif bertugas.
* `INACTIVE`: Pengemudi nonaktif / cuti.
* `SUSPENDED`: Pengemudi diskorsing / tidak diizinkan bertugas.

---

### 1.3 Tabel `ambulance_details` (Profil Ambulans 1:1)
Ambulans adalah ekstensi profil spesifik pada tabel `vehicles`, **bukan** entitas kendaraan terpisah.

Relasi:
```text
vehicles (1) <----> (1) ambulance_details
(FK & PK: vehicle_id)
```

```sql
CREATE TABLE IF NOT EXISTS ambulance_details (
    vehicle_id        INT UNSIGNED NOT NULL,
    ambulance_code    VARCHAR(32)  NOT NULL,
    ambulance_name    VARCHAR(100) NOT NULL,
    ambulance_type    VARCHAR(50)  NOT NULL DEFAULT 'TRANSPORT',
    base_location     VARCHAR(100) NOT NULL DEFAULT 'Pool Ambulans RS',
    readiness         VARCHAR(20)  NOT NULL DEFAULT 'READY',
    chassis_number    VARCHAR(64)  NULL,
    engine_number     VARCHAR(64)  NULL,
    stnk_expiry       DATE         NULL,
    kir_expiry        DATE         NULL,
    insurance_expiry  DATE         NULL,
    fuel_level        VARCHAR(20)  NOT NULL DEFAULT 'FULL',
    equipment_notes   TEXT         NULL,
    last_check_at     DATETIME     NULL,
    notes             TEXT         NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (vehicle_id),
    UNIQUE KEY uq_ambulance_code (ambulance_code),
    KEY idx_ambulance_readiness (readiness),
    CONSTRAINT fk_ambulance_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Kesiapan (Readiness):**
* `READY`: Unit siaga dan siap meluncur untuk panggilan gawat darurat / rujukan.
* `STANDBY`: Unit berada di pangkalan / standby jadwal rutin.
* `MAINTENANCE`: Unit sedang perbaikan alat medis atau kendaraan.
* `UNAVAILABLE`: Unit tidak dapat dioperasikan.

---

### 1.4 Tabel `assignments` (Manajemen Penugasan)
Penugasan resmi operasional kendaraan atau rujukan ambulans sebelum pelaksanaan perjalanan.

> **Catatan Arsitektur:** Assignment **berbeda** dari Trip. Assignment adalah instruksi / surat tugas administratif. Trip (Phase 3) adalah rekaman aktual perjalanan dengan odometer, GPS, bukti foto, dan lifecycle perjalanan.

```sql
CREATE TABLE IF NOT EXISTS assignments (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_number  VARCHAR(32)  NOT NULL,
    assignment_date    DATE         NOT NULL,
    vehicle_id         INT UNSIGNED NOT NULL,
    driver_id          INT UNSIGNED NOT NULL,
    destination        VARCHAR(255) NOT NULL,
    purpose            TEXT         NOT NULL,
    passenger_count    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    passenger_notes    TEXT         NULL,
    st_reference       VARCHAR(100) NULL,
    sppd_reference     VARCHAR(100) NULL,
    status             VARCHAR(20)  NOT NULL DEFAULT 'ASSIGNED',
    notes              TEXT         NULL,
    created_by         INT UNSIGNED NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assignment_number (assignment_number),
    KEY idx_assignments_date (assignment_date),
    KEY idx_assignments_status (status),
    KEY idx_assignments_vehicle (vehicle_id, assignment_date, status),
    KEY idx_assignments_driver (driver_id, assignment_date, status),
    CONSTRAINT fk_assignments_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_driver FOREIGN KEY (driver_id) REFERENCES drivers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Status Penugasan:**
* `DRAFT`: Konsep awal penugasan sebelum diterbitkan.
* `ASSIGNED`: Resmi ditugaskan kepada driver dan kendaraan.
* `CANCELLED`: Dibatalkan sebelum perjalanan dimulai.

---

## 2. Aturan Bisnis & Validasi Concurrency

1. **Penomoran Otomatis Server-Side:**
   * Nomor penugasan (`assignment_number`) digenerate otomatis dengan pola `ASG-YYYY-NNNNN` pada sisi server.
   * Input client tidak dipercaya untuk menentukan nomor surat tugas.

2. **Pencegahan Konflik Jadwal (Concurrency Protection):**
   * Pengecekan dilakukan di dalam transaksi database (`DB::transaction`) menggunakan row-locking `SELECT ... FOR UPDATE`:
     * Satu kendaraan **tidak boleh** memiliki dua penugasan berstatus `ASSIGNED` pada tanggal yang sama.
     * Satu driver **tidak boleh** memiliki dua penugasan berstatus `ASSIGNED` pada tanggal yang sama.
   * Apabila terdeteksi bentrok jadwal, sistem melempar `HttpException(409, ...)` yang mencegah double-booking.

3. **Integritas Driver & Kendaraan:**
   * Kendaraan berstatus selain `ACTIVE` (`MAINTENANCE`, `INACTIVE`, `RETIRED`) **ditolak** untuk penugasan baru.
   * Driver berstatus selain `ACTIVE` (`INACTIVE`, `SUSPENDED`) **ditolak** untuk penugasan baru.
   * Driver dengan tanggal masa berlaku SIM (`license_expiry`) yang sudah terlewati pada tanggal penugasan **ditolak secara otomatis**.

4. **Hak Akses & Otorisasi:**
   * Admin: Kelola penuh master data dan penugasan.
   * Operator: Membuat dan memperbarui penugasan, memantau master kendaraan & driver.
   * Driver: Melihat penugasan khusus dirinya sendiri (IDOR protected: driver tidak dapat melihat atau mengubah penugasan pengemudi lain).
   * Pimpinan: Melihat daftar master data dan penugasan secara terpusat (read-only).
