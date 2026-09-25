<?php

declare(strict_types=1);

namespace Modules\Assignments\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\AssignmentService;

final class AssignmentController
{
    public function index(Request $request): never
    {
        $user = Session::user();
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->query('per_page') ?? 15)));
        $search = $request->query('search');
        $status = $request->query('status');
        $date = $request->query('date');

        // IDOR protection: if user is driver, only show their own assignments
        $driverId = null;
        if (($user['role'] ?? '') === 'driver') {
            $drv = Driver::findByUserId((int)$user['id']);
            $driverId = $drv ? (int)$drv['id'] : -1;
        }

        $result = Assignment::paginate($page, $perPage, $search, $status, $date, $driverId);

        View::show('modules/assignments/views/index.php', [
            'title' => 'Penugasan Perjalanan',
            'assignments' => $result['data'],
            'pagination' => $result,
            'search' => $search ?? '',
            'status' => $status ?? '',
            'date' => $date ?? '',
            'userRole' => $user['role'] ?? '',
        ]);
    }

    public function create(Request $request): never
    {
        $vehicles = Vehicle::listActive();
        $drivers = Driver::listActive();

        View::show('modules/assignments/views/form.php', [
            'title' => 'Buat Penugasan Baru',
            'assignment' => [
                'assignment_date' => date('Y-m-d'),
                'passenger_count' => 1,
            ],
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'errors' => [],
        ]);
    }

    public function store(Request $request): never
    {
        $user = Session::user();
        try {
            $data = $request->all();
            $created = AssignmentService::create($data, $user ? (int)$user['id'] : null);
            flash('success', "Penugasan berhasil dibuat dengan nomor {$created['assignment_number']}.");
            Response::redirect('/penugasan');
        } catch (HttpException $e) {
            $vehicles = Vehicle::listActive();
            $drivers = Driver::listActive();
            View::show('modules/assignments/views/form.php', [
                'title' => 'Buat Penugasan Baru',
                'assignment' => $request->all(),
                'vehicles' => $vehicles,
                'drivers' => $drivers,
                'errors' => $e->errors,
                'status' => $e->status,
            ], 'app/views/layouts/app', $e->status);
        }
    }

    public function edit(Request $request, string $id): never
    {
        $assignment = Assignment::find((int)$id);
        if ($assignment === null) {
            throw new HttpException(404, 'Penugasan tidak ditemukan.');
        }

        $vehicles = Vehicle::listActive();
        $drivers = Driver::listActive();

        View::show('modules/assignments/views/form.php', [
            'title' => 'Edit Penugasan: ' . $assignment['assignment_number'],
            'assignment' => $assignment,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'errors' => [],
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $user = Session::user();
        try {
            $data = $request->all();
            AssignmentService::update((int)$id, $data, $user ? (int)$user['id'] : null);
            flash('success', 'Data penugasan berhasil diperbarui.');
            Response::redirect('/penugasan');
        } catch (HttpException $e) {
            $assignment = array_merge(['id' => (int)$id], $request->all());
            $vehicles = Vehicle::listActive();
            $drivers = Driver::listActive();
            View::show('modules/assignments/views/form.php', [
                'title' => 'Edit Penugasan',
                'assignment' => $assignment,
                'vehicles' => $vehicles,
                'drivers' => $drivers,
                'errors' => $e->errors,
                'status' => $e->status,
            ], 'app/views/layouts/app', $e->status);
        }
    }

    public function cancel(Request $request, string $id): never
    {
        $user = Session::user();
        try {
            $reason = $request->input('reason', '');
            AssignmentService::cancel((int)$id, $reason, $user ? (int)$user['id'] : null);
            flash('success', 'Penugasan berhasil dibatalkan.');
        } catch (HttpException $e) {
            flash('danger', $e->getMessage());
        }
        Response::redirect('/penugasan');
    }
}
