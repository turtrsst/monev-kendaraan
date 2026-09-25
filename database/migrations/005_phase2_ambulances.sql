-- ============================================================
-- Migration 005 — Phase 2 Ambulance Profile (1:1 with vehicles)
-- Fleet Logbook & Monitoring System
-- Timezone: Asia/Jakarta (+07:00)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

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
