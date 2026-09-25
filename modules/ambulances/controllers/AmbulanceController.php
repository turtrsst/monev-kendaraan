<?php

declare(strict_types=1);

namespace Modules\Ambulances\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Ambulance;
use App\Models\Vehicle;
use App\Services\AmbulanceService;

final class AmbulanceController
{
    public function index(Request $request): never
    {
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->query('per_page') ?? 15)));
        $search = $request->query('search');
        $readiness = $request->query('readiness');

        $result = Ambulance::paginate($page, $perPage, $search, $readiness);

        View::show('modules/ambulances/views/index.php', [
            'title' => 'Profil Unit Ambulans',
            'ambulances' => $result['data'],
            'pagination' => $result,
            'search' => $search ?? '',
            'readiness' => $readiness ?? '',
        ]);
    }

    public function create(Request $request): never
    {
        $vehicleId = $request->query('vehicle_id');
        $vehicles = Vehicle::listActive();

        View::show('modules/ambulances/views/form.php', [
            'title' => 'Tambah Profil Ambulans',
            'ambulance' => $vehicleId ? ['vehicle_id' => (int)$vehicleId] : null,
            'vehicles' => $vehicles,
            'isEdit' => false,
            'errors' => [],
        ]);
    }

    public function store(Request $request): never
    {
        $user = Session::user();
        try {
            $data = $request->all();
            AmbulanceService::create($data, $user ? (int)$user['id'] : null);
            flash('success', 'Profil ambulans berhasil ditambahkan.');
            Response::redirect('/ambulans');
        } catch (HttpException $e) {
            $vehicles = Vehicle::listActive();
            View::show('modules/ambulances/views/form.php', [
                'title' => 'Tambah Profil Ambulans',
                'ambulance' => $request->all(),
                'vehicles' => $vehicles,
                'isEdit' => false,
                'errors' => $e->errors,
                'status' => $e->status,
            ], 'app/views/layouts/app', $e->status);
        }
    }

    public function edit(Request $request, string $vehicleId): never
    {
        $ambulance = Ambulance::find((int)$vehicleId);
        if ($ambulance === null) {
            throw new HttpException(404, 'Profil ambulans tidak ditemukan.');
        }

        View::show('modules/ambulances/views/form.php', [
            'title' => 'Edit Profil Ambulans: ' . $ambulance['ambulance_code'],
            'ambulance' => $ambulance,
            'vehicles' => [],
            'isEdit' => true,
            'errors' => [],
        ]);
    }

    public function update(Request $request, string $vehicleId): never
    {
        $user = Session::user();
        try {
            $data = $request->all();
            AmbulanceService::update((int)$vehicleId, $data, $user ? (int)$user['id'] : null);
            flash('success', 'Profil ambulans berhasil diperbarui.');
            Response::redirect('/ambulans');
        } catch (HttpException $e) {
            $existing = Ambulance::find((int)$vehicleId);
            $ambulance = array_merge($existing ?? ['vehicle_id' => (int)$vehicleId], $request->all());
            View::show('modules/ambulances/views/form.php', [
                'title' => 'Edit Profil Ambulans',
                'ambulance' => $ambulance,
                'vehicles' => [],
                'isEdit' => true,
                'errors' => $e->errors,
                'status' => $e->status,
            ], 'app/views/layouts/app', $e->status);
        }
    }

    public function destroy(Request $request, string $vehicleId): never
    {
        $user = Session::user();
        try {
            AmbulanceService::delete((int)$vehicleId, $user ? (int)$user['id'] : null);
            flash('success', 'Profil ambulans berhasil dihapus.');
        } catch (HttpException $e) {
            flash('danger', $e->getMessage());
        }
        Response::redirect('/ambulans');
    }
}
