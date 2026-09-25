<?php

declare(strict_types=1);

namespace Modules\Dashboard\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\Notification;

final class DashboardController
{
    public function index(Request $request): never
    {
        $user = Session::user();
        $role = $user['role'] ?? 'driver';

        // Statistik Phase 1 = fondasi saja; data aktual menyusul Phase 2–10
        $stats = [
            ['label' => 'Total Kendaraan', 'value' => '0', 'icon' => 'bi-truck', 'note' => 'Master data (Phase 2)'],
            ['label' => 'Total Driver', 'value' => '0', 'icon' => 'bi-person-badge', 'note' => 'Master data (Phase 2)'],
            ['label' => 'Trip Hari Ini', 'value' => '0', 'icon' => 'bi-route', 'note' => 'Modul trip (Phase 3)'],
            ['label' => 'Trip Aktif', 'value' => '0', 'icon' => 'bi-broadcast-pin', 'note' => 'Monitoring (Phase 10)'],
            ['label' => 'Total KM', 'value' => '0', 'icon' => 'bi-speedometer2', 'note' => '—'],
            ['label' => 'Total BBM', 'value' => 'Rp 0', 'icon' => 'bi-fuel-pump', 'note' => '—'],
            ['label' => 'Total E-Toll', 'value' => 'Rp 0', 'icon' => 'bi-credit-card', 'note' => '—'],
            ['label' => 'Total Biaya', 'value' => 'Rp 0', 'icon' => 'bi-cash-stack', 'note' => '—'],
        ];

        $notifications = $user ? Notification::latest((int)$user['id'], 5) : [];
        $unread = $user ? Notification::unreadCount((int)$user['id']) : 0;

        View::show('modules/dashboard/views/index.php', [
            'title' => 'Beranda',
            'user' => $user,
            'stats' => $stats,
            'notifications' => $notifications,
            'unread' => $unread,
        ]);
    }
}
