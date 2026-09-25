-- ============================================================
-- Migration 006 — Phase 2 Assignment Management
-- Fleet Logbook & Monitoring System
-- Timezone: Asia/Jakarta (+07:00)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

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
