-- ============================================================
-- Fleet Logbook & Monitoring — SCHEMA SNAPSHOT
-- Cara pakai: import ke MySQL/MariaDB ATAU jalankan
--   php database/migrate.php   (disarankan: idempotent + tercatat)
-- Snapshot ini = gabungan database/migrations/*.sql
-- ============================================================

-- ============================================================
-- Migration 001 — Foundation schema
-- Fleet Logbook & Monitoring System
-- Database: kendaraan_logbook (MySQL 8 / MariaDB 10.4+)
-- Timezone penyimpanan: Asia/Jakarta (WIB) — diatur oleh koneksi aplikasi
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- ------------------------------------------------------------
-- roles (role configurable, bukan ENUM)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(32)  NOT NULL,
    name        VARCHAR(64)  NOT NULL,
    description VARCHAR(255) NULL,
    permissions JSON         NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username             VARCHAR(64)  NOT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    name                 VARCHAR(120) NOT NULL,
    email                VARCHAR(120) NULL,
    phone                VARCHAR(32)  NULL,
    role_id              INT UNSIGNED NOT NULL,
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,
    force_password_change TINYINT(1)  NOT NULL DEFAULT 0,
    last_login_at        DATETIME     NULL,
    failed_login_count   INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until         DATETIME     NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at           DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role (role_id),
    KEY idx_users_active (is_active, deleted_at),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- audit_logs (semua aktivitas penting)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED    NULL,
    action     VARCHAR(40)     NOT NULL,
    entity     VARCHAR(64)     NULL,
    entity_id  VARCHAR(64)     NULL,
    old_data   JSON            NULL,
    new_data   JSON            NULL,
    ip         VARCHAR(45)     NULL,
    user_agent VARCHAR(255)    NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_action_time (action, created_at),
    KEY idx_audit_entity (entity, entity_id),
    KEY idx_audit_user (user_id, created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- login_attempts (login throttling)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username    VARCHAR(64)     NOT NULL,
    ip          VARCHAR(45)     NOT NULL,
    success     TINYINT(1)      NOT NULL DEFAULT 0,
    attempted_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_user_time (username, attempted_at),
    KEY idx_attempts_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- settings (konfigurasi aplikasi — tanpa hard-code)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    skey        VARCHAR(64)  NOT NULL,
    value       TEXT         NULL,
    description VARCHAR(255) NULL,
    updated_by  INT UNSIGNED NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (skey),
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- notifications (in-app — SMTP/email menyusul, tanpa dependency di Phase 1)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED    NOT NULL,
    type       VARCHAR(40)     NOT NULL,
    title      VARCHAR(150)    NOT NULL,
    body       VARCHAR(500)    NULL,
    link       VARCHAR(255)    NULL,
    is_read    TINYINT(1)      NOT NULL DEFAULT 0,
    read_at    DATETIME        NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SEED (roles + settings)
-- ------------------------------------------------------------
-- ============================================================
-- Migration 002 — Seed foundation
-- Roles + settings default.
-- Catatan: akun admin TIDAK di-seed di sini (password_hash dihasilkan
-- oleh database/seeds/seed_admin.php — tidak ada hash di Git).
-- ============================================================

SET NAMES utf8mb4;

INSERT INTO roles (slug, name, description, permissions) VALUES
    ('admin', 'Administrator', 'Kelola master data, penugasan, verifikasi, laporan, konfigurasi',
        JSON_ARRAY('users.manage','drivers.manage','vehicles.manage','ambulances.manage','assignments.manage','trips.view','trips.verify','reports.view','reports.export','settings.manage','audit.view')),
    ('operator', 'Petugas / Operator', 'Penugasan, monitoring, logbook, dokumen, verifikasi',
        JSON_ARRAY('assignments.manage','trips.view','trips.verify','documents.manage','reports.view')),
    ('driver', 'Driver', 'Penugasan saya, trip lifecycle, bukti, biaya, submit logbook',
        JSON_ARRAY('trips.own','documents.own','expenses.own')),
    ('pimpinan', 'Pimpinan / Monitor', 'Dashboard, monitoring, laporan, statistik (read-only)',
        JSON_ARRAY('trips.view','reports.view'))
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO settings (skey, value, description) VALUES
    ('app.name', 'Fleet Logbook & Monitoring', 'Nama aplikasi'),
    ('app.hospital_name', 'RSUP dr. Soeradji Tirtonegoro Klaten', 'Nama rumah sakit (halaman login & header)'),
    ('app.logo_path', 'assets/images/logo.svg', 'Path logo (publik) — mudah diganti, tidak hard-coded'),
    ('app.timezone', 'Asia/Jakarta', 'Zona waktu aplikasi'),
    ('trip.destination_radius_default_m', '100', 'Radius validasi tujuan default (meter)'),
    ('trip.destination_tolerance_m', '50', 'Toleransi WARNING di luar radius (meter)'),
    ('trip.gps_max_accuracy_m', '100', 'Batas akurasi GPS yang wajar (meter)'),
    ('upload.max_mb', '5', 'Maksimum ukuran upload file (MB)'),
    ('auth.session_idle_minutes', '30', 'Idle timeout sesi (menit)'),
    ('auth.session_absolute_minutes', '720', 'Absolute timeout sesi (menit)'),
    ('auth.login_max_attempts', '5', 'Maksimum percobaan login gagal (per 15 menit, per username+IP)'),
    ('auth.login_lockout_minutes', '15', 'Durasi kunci akun setelah percobaan gagal (menit)'),
    ('ocr.enabled', '1', 'OCR struk aktif (1/0) — hasil selalu wajib direview driver'),
    ('watermark.enabled', '1', 'Preview watermark evidence aktif (1/0) — original tidak diubah'),
    ('fuel.receipt_photo_required', '0', 'Foto struk BBM WAJIB (0 = non-blocking, sangat dianjurkan)'),
    ('fuel.receipt_missing_needs_review', '1', 'Tanpa foto struk → admin dapat menandai NEEDS_REVIEW')
ON DUPLICATE KEY UPDATE value = VALUES(value), description = VALUES(description);

-- ============================================================
-- Migration 003 — Phase 2 Vehicles Master
-- ============================================================
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

-- ============================================================
-- Migration 004 — Phase 2 Drivers Master
-- ============================================================
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

-- ============================================================
-- Migration 005 — Phase 2 Ambulance Profile (1:1 with vehicles)
-- ============================================================
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

-- ============================================================
-- Migration 006 — Phase 2 Assignment Management
-- ============================================================
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
