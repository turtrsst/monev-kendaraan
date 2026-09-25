-- ============================================================
-- Migration 003 — Phase 2 Vehicles Master
-- Fleet Logbook & Monitoring System
-- Timezone: Asia/Jakarta (+07:00)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

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
