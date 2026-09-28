-- Phase 3 — Trip execution / digital logbook foundation.
-- Destination coordinates are optional, explicitly entered by authorized staff;
-- no geocoding or coordinate fabrication is performed.
SET NAMES utf8mb4;
SET time_zone = '+07:00';

ALTER TABLE assignments
    ADD COLUMN destination_latitude DECIMAL(10,7) NULL AFTER destination,
    ADD COLUMN destination_longitude DECIMAL(10,7) NULL AFTER destination_latitude,
    ADD CONSTRAINT chk_assignment_destination_coordinates CHECK (
        (destination_latitude IS NULL AND destination_longitude IS NULL) OR
        (destination_latitude IS NOT NULL AND destination_longitude IS NOT NULL
         AND destination_latitude BETWEEN -90 AND 90 AND destination_longitude BETWEEN -180 AND 180)
    );

CREATE TABLE trip_number_sequences (
    trip_year SMALLINT UNSIGNED NOT NULL,
    last_value INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (trip_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trip (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_number VARCHAR(16) NOT NULL,
    assignment_id INT UNSIGNED NOT NULL,
    create_uuid CHAR(36) NOT NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    driver_id INT UNSIGNED NOT NULL,
    destination_latitude DECIMAL(10,7) NULL,
    destination_longitude DECIMAL(10,7) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ASSIGNED',
    planned_departure_at DATETIME NULL,
    actual_started_at DATETIME NULL,
    actual_arrived_at DATETIME NULL,
    actual_returning_at DATETIME NULL,
    actual_completed_at DATETIME NULL,
    actual_submitted_at DATETIME NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_trip_number (trip_number),
    UNIQUE KEY uq_trip_assignment (assignment_id),
    UNIQUE KEY uq_trip_create_uuid (create_uuid),
    KEY idx_trip_driver_status (driver_id, status, created_at),
    KEY idx_trip_vehicle_status (vehicle_id, status),
    KEY idx_trip_status_created (status, created_at),
    CONSTRAINT chk_trip_status CHECK (status IN ('ASSIGNED','READY','STARTED','ARRIVED','RETURNING','COMPLETED','SUBMITTED')),
    CONSTRAINT chk_trip_destination_coordinates CHECK (
        (destination_latitude IS NULL AND destination_longitude IS NULL) OR
        (destination_latitude IS NOT NULL AND destination_longitude IS NOT NULL
         AND destination_latitude BETWEEN -90 AND 90 AND destination_longitude BETWEEN -180 AND 180)
    ),
    CONSTRAINT fk_trip_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE RESTRICT,
    CONSTRAINT fk_trip_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_trip_driver FOREIGN KEY (driver_id) REFERENCES drivers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_trip_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trip_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(20) NOT NULL,
    event_uuid CHAR(36) NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    accuracy_m DECIMAL(9,2) NULL,
    distance_to_destination_m DECIMAL(10,2) NULL,
    location_status VARCHAR(20) NULL,
    notes VARCHAR(1000) NULL,
    actor_user_id INT UNSIGNED NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_trip_event_uuid (event_uuid),
    KEY idx_trip_events_trip_time (trip_id, occurred_at, id),
    KEY idx_trip_events_trip_type (trip_id, event_type),
    KEY idx_trip_events_actor (actor_user_id, occurred_at),
    CONSTRAINT chk_trip_event_type CHECK (event_type IN ('READY','START','ARRIVAL','RETURNING','COMPLETED','SUBMITTED')),
    CONSTRAINT chk_trip_event_coordinates CHECK (
        (latitude IS NULL AND longitude IS NULL) OR
        (latitude IS NOT NULL AND longitude IS NOT NULL
         AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)
    ),
    CONSTRAINT fk_trip_events_trip FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE,
    CONSTRAINT fk_trip_events_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (skey, value, description) VALUES
    ('trip.destination_tolerance_m', '50', 'Toleransi di luar radius tujuan sebelum REVIEW_REQUIRED (meter)'),
    ('trip.gps_max_accuracy_m', '100', 'Akurasi GPS maksimum yang dianggap wajar (meter)')
ON DUPLICATE KEY UPDATE description = VALUES(description);
