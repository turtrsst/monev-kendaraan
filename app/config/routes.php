<?php

declare(strict_types=1);

use App\Core\Router;
use Api\Auth\AuthApiController;
use Api\Vehicles\VehicleApiController;
use Api\Drivers\DriverApiController;
use Api\Ambulances\AmbulanceApiController;
use Api\Assignments\AssignmentApiController;
use Modules\Auth\Controllers\AuthController;
use Modules\Dashboard\Controllers\DashboardController;
use Modules\Settings\Controllers\SettingsController;
use Modules\Vehicles\Controllers\VehicleController;
use Modules\Drivers\Controllers\DriverController;
use Modules\Ambulances\Controllers\AmbulanceController;
use Modules\Assignments\Controllers\AssignmentController;

/**
 * Definisi route Phase 1 & Phase 2.
 * urutan middleware: auth → force_password_change → role → csrf → ratelimit
 */
return function (Router $r): void {
    // --- Publik ---
    $r->get('/login', [AuthController::class, 'showLogin'], ['guest', 'ratelimit:60']);
    $r->post('/login', [AuthController::class, 'login'], ['guest', 'csrf', 'ratelimit:10']);

    // --- Terotentikasi Umum (Phase 1) ---
    $r->get('/', [DashboardController::class, 'index'], ['auth', 'force_password_change']);
    $r->get('/beranda', [DashboardController::class, 'index'], ['auth', 'force_password_change']);
    $r->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);

    $r->get('/ganti-password', [AuthController::class, 'showChangePassword'], ['auth']);
    $r->post('/ganti-password', [AuthController::class, 'changePassword'], ['auth', 'csrf', 'ratelimit:20']);

    // --- Admin only (Settings) ---
    $r->get('/pengaturan', [SettingsController::class, 'index'], ['auth', 'force_password_change', 'role:admin']);
    $r->post('/pengaturan', [SettingsController::class, 'update'], ['auth', 'force_password_change', 'role:admin', 'csrf', 'ratelimit:30']);

    // ========================================================
    // PHASE 2 — WEB UI ROUTES
    // ========================================================

    // --- Master Kendaraan (Admin & Operator) ---
    $r->get('/kendaraan', [VehicleController::class, 'index'], ['auth', 'force_password_change', 'role:admin,operator,pimpinan']);
    $r->get('/kendaraan/tambah', [VehicleController::class, 'create'], ['auth', 'force_password_change', 'role:admin,operator']);
    $r->post('/kendaraan/tambah', [VehicleController::class, 'store'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->get('/kendaraan/edit/{id}', [VehicleController::class, 'edit'], ['auth', 'force_password_change', 'role:admin,operator']);
    $r->post('/kendaraan/edit/{id}', [VehicleController::class, 'update'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->post('/kendaraan/hapus/{id}', [VehicleController::class, 'destroy'], ['auth', 'force_password_change', 'role:admin', 'csrf']);

    // --- Master Driver (Admin & Operator) ---
    $r->get('/driver', [DriverController::class, 'index'], ['auth', 'force_password_change', 'role:admin,operator,pimpinan']);
    $r->get('/driver/tambah', [DriverController::class, 'create'], ['auth', 'force_password_change', 'role:admin,operator']);
    $r->post('/driver/tambah', [DriverController::class, 'store'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->get('/driver/edit/{id}', [DriverController::class, 'edit'], ['auth', 'force_password_change', 'role:admin,operator']);
    $r->post('/driver/edit/{id}', [DriverController::class, 'update'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->post('/driver/hapus/{id}', [DriverController::class, 'destroy'], ['auth', 'force_password_change', 'role:admin', 'csrf']);

    // --- Profil Ambulans (Admin & Operator) ---
    $r->get('/ambulans', [AmbulanceController::class, 'index'], ['auth', 'force_password_change', 'role:admin,operator,pimpinan']);
    $r->get('/ambulans/tambah', [AmbulanceController::class, 'create'], ['auth', 'force_password_change', 'role:admin,operator']);
    $r->post('/ambulans/tambah', [AmbulanceController::class, 'store'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->get('/ambulans/edit/{id}', [AmbulanceController::class, 'edit'], ['auth', 'force_password_change', 'role:admin,operator']);
    $r->post('/ambulans/edit/{id}', [AmbulanceController::class, 'update'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->post('/ambulans/hapus/{id}', [AmbulanceController::class, 'destroy'], ['auth', 'force_password_change', 'role:admin', 'csrf']);

    // --- Assignment Management ---
    // List: Admin, Operator, Pimpinan, dan Driver (Driver otomatis difilter penugasan miliknya)
    $r->get('/penugasan', [AssignmentController::class, 'index'], ['auth', 'force_password_change']);
    $r->get('/penugasan/tambah', [AssignmentController::class, 'create'], ['auth', 'force_password_change', 'role:admin,operator']);
    $r->post('/penugasan/tambah', [AssignmentController::class, 'store'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->get('/penugasan/edit/{id}', [AssignmentController::class, 'edit'], ['auth', 'force_password_change', 'role:admin,operator']);
    $r->post('/penugasan/edit/{id}', [AssignmentController::class, 'update'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->post('/penugasan/batal/{id}', [AssignmentController::class, 'cancel'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);

    // ========================================================
    // API (JSON) ROUTES
    // ========================================================

    // Auth
    $r->get('/api/auth/me', [AuthApiController::class, 'me'], ['auth', 'force_password_change']);
    $r->post('/api/auth/heartbeat', [AuthApiController::class, 'heartbeat'], ['auth', 'csrf']);
    $r->post('/api/auth/logout', [AuthApiController::class, 'logout'], ['auth', 'csrf']);

    // Vehicles API
    $r->get('/api/vehicles', [VehicleApiController::class, 'index'], ['auth', 'force_password_change']);
    $r->get('/api/vehicles/{id}', [VehicleApiController::class, 'show'], ['auth', 'force_password_change']);
    $r->post('/api/vehicles', [VehicleApiController::class, 'store'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->put('/api/vehicles/{id}', [VehicleApiController::class, 'update'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->delete('/api/vehicles/{id}', [VehicleApiController::class, 'destroy'], ['auth', 'force_password_change', 'role:admin', 'csrf']);

    // Drivers API
    $r->get('/api/drivers', [DriverApiController::class, 'index'], ['auth', 'force_password_change']);
    $r->get('/api/drivers/{id}', [DriverApiController::class, 'show'], ['auth', 'force_password_change']);
    $r->post('/api/drivers', [DriverApiController::class, 'store'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->put('/api/drivers/{id}', [DriverApiController::class, 'update'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->delete('/api/drivers/{id}', [DriverApiController::class, 'destroy'], ['auth', 'force_password_change', 'role:admin', 'csrf']);

    // Ambulances API
    $r->get('/api/ambulances', [AmbulanceApiController::class, 'index'], ['auth', 'force_password_change']);
    $r->get('/api/ambulances/{id}', [AmbulanceApiController::class, 'show'], ['auth', 'force_password_change']);
    $r->post('/api/ambulances', [AmbulanceApiController::class, 'store'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->put('/api/ambulances/{id}', [AmbulanceApiController::class, 'update'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->delete('/api/ambulances/{id}', [AmbulanceApiController::class, 'destroy'], ['auth', 'force_password_change', 'role:admin', 'csrf']);

    // Assignments API
    $r->get('/api/assignments', [AssignmentApiController::class, 'index'], ['auth', 'force_password_change']);
    $r->get('/api/assignments/{id}', [AssignmentApiController::class, 'show'], ['auth', 'force_password_change']);
    $r->post('/api/assignments', [AssignmentApiController::class, 'store'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->put('/api/assignments/{id}', [AssignmentApiController::class, 'update'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
    $r->post('/api/assignments/{id}/cancel', [AssignmentApiController::class, 'cancel'], ['auth', 'force_password_change', 'role:admin,operator', 'csrf']);
};
