<?php

declare(strict_types=1);

use App\Core\Router;
use Api\Auth\AuthApiController;
use Modules\Auth\Controllers\AuthController;
use Modules\Dashboard\Controllers\DashboardController;
use Modules\Settings\Controllers\SettingsController;

/**
 * Definisi route Phase 1.
 * urutan middleware: auth → force_password_change → role → csrf → ratelimit
 */
return function (Router $r): void {
    // --- Publik ---
    $r->get('/login', [AuthController::class, 'showLogin'], ['guest', 'ratelimit:60']);
    $r->post('/login', [AuthController::class, 'login'], ['guest', 'csrf', 'ratelimit:10']);

    // --- Terotentikasi ---
    $r->get('/', [DashboardController::class, 'index'], ['auth', 'force_password_change']);
    $r->get('/beranda', [DashboardController::class, 'index'], ['auth', 'force_password_change']);
    $r->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);

    $r->get('/ganti-password', [AuthController::class, 'showChangePassword'], ['auth']);
    $r->post('/ganti-password', [AuthController::class, 'changePassword'], ['auth', 'csrf', 'ratelimit:20']);

    // --- Admin only (ujian otorisasi role) ---
    $r->get('/pengaturan', [SettingsController::class, 'index'], ['auth', 'force_password_change', 'role:admin']);
    $r->post('/pengaturan', [SettingsController::class, 'update'], ['auth', 'force_password_change', 'role:admin', 'csrf', 'ratelimit:30']);

    // --- API (JSON) ---
    $r->get('/api/auth/me', [AuthApiController::class, 'me'], ['auth', 'force_password_change']);
    $r->post('/api/auth/heartbeat', [AuthApiController::class, 'heartbeat'], ['auth', 'csrf']);
    $r->post('/api/auth/logout', [AuthApiController::class, 'logout'], ['auth', 'csrf']);
};
