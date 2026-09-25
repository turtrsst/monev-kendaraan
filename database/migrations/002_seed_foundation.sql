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
