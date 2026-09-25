-- ============================================================
-- Migration 004 — Phase 2 Drivers Master
-- Fleet Logbook & Monitoring System
-- Timezone: Asia/Jakarta (+07:00)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

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
