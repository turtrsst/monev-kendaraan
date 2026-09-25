<?php

declare(strict_types=1);

namespace Modules\Dashboard\Controllers;

use App\Core\DB;
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

        $totalVehicles = 0;
        $totalDrivers = 0;
        $totalAssignmentsToday = 0;
        $totalAmbulances = 0;

        try {
            $totalVehicles = (int)DB::scalar('SELECT COUNT(*) FROM vehicles');
            $totalDrivers = (int)DB::scalar('SELECT COUNT(*) FROM drivers');
            $totalAmbulances = (int)DB::scalar('SELECT COUNT(*) FROM ambulance_details');
            $totalAssignmentsToday = (int)DB::scalar('SELECT COUNT(*) FROM assignments WHERE assignment_date = CURRENT_DATE');
        } catch (\Throwable) {
            // DB fallback if tables not yet migrated
        }

        $stats = [
            ['label' => 'Total Kendaraan', 'value' => (string)$totalVehicles, 'icon' => 'bi-car-front', 'note' => 'Armada terdaftar'],
            ['label' => 'Unit Ambulans', 'value' => (string)$totalAmbulances, 'icon' => 'bi-hospital', 'note' => 'Profil ambulans aktif'],
            ['label' => 'Total Driver', 'value' => (string)$totalDrivers, 'icon' => 'bi-person-badge', 'note' => 'Pengemudi resmi'],
            ['label' => 'Penugasan Hari Ini', 'value' => (string)$totalAssignmentsToday, 'icon' => 'bi-clipboard-check', 'note' => date('d/m/Y')],
            ['label' => 'Trip Hari Ini', 'value' => '0', 'icon' => 'bi-route', 'note' => 'Menyusul Phase 3'],
            ['label' => 'Trip Aktif', 'value' => '0', 'icon' => 'bi-broadcast-pin', 'note' => 'Monitoring (Phase 10)'],
            ['label' => 'Total BBM', 'value' => 'Rp 0', 'icon' => 'bi-fuel-pump', 'note' => 'Menyusul Phase 6'],
            ['label' => 'Total E-Toll', 'value' => 'Rp 0', 'icon' => 'bi-credit-card', 'note' => 'Menyusul Phase 7'],
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
