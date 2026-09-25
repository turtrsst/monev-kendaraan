<?php

declare(strict_types=1);

namespace Modules\Vehicles\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Vehicle;
use App\Services\VehicleService;

final class VehicleController
{
    public function index(Request $request): never
    {
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->query('per_page') ?? 15)));
        $search = $request->query('search');
        $status = $request->query('status');
        $type = $request->query('type');

        $result = Vehicle::paginate($page, $perPage, $search, $status, $type);

        View::show('modules/vehicles/views/index.php', [
            'title' => 'Master Kendaraan',
            'vehicles' => $result['data'],
            'pagination' => $result,
            'search' => $search ?? '',
            'status' => $status ?? '',
            'type' => $type ?? '',
        ]);
    }

    public function create(Request $request): never
    {
        View::show('modules/vehicles/views/form.php', [
            'title' => 'Tambah Kendaraan',
            'vehicle' => null,
            'errors' => [],
        ]);
    }

    public function store(Request $request): never
    {
        $user = Session::user();
        try {
            $data = $request->all();
            VehicleService::create($data, $user ? (int)$user['id'] : null);
            flash('success', 'Kendaraan berhasil ditambahkan.');
            Response::redirect('/kendaraan');
        } catch (HttpException $e) {
            View::show('modules/vehicles/views/form.php', [
                'title' => 'Tambah Kendaraan',
                'vehicle' => $request->all(),
                'errors' => $e->errors,
                'status' => $e->status,
            ], 'app/views/layouts/app', $e->status);
        }
    }

    public function edit(Request $request, string $id): never
    {
        $vehicle = Vehicle::find((int)$id);
        if ($vehicle === null) {
            throw new HttpException(404, 'Kendaraan tidak ditemukan.');
        }

        View::show('modules/vehicles/views/form.php', [
            'title' => 'Edit Kendaraan: ' . $vehicle['plate_number'],
            'vehicle' => $vehicle,
            'errors' => [],
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $user = Session::user();
        try {
            $data = $request->all();
            VehicleService::update((int)$id, $data, $user ? (int)$user['id'] : null);
            flash('success', 'Data kendaraan berhasil diperbarui.');
            Response::redirect('/kendaraan');
        } catch (HttpException $e) {
            $vehicle = array_merge(['id' => (int)$id], $request->all());
            View::show('modules/vehicles/views/form.php', [
                'title' => 'Edit Kendaraan',
                'vehicle' => $vehicle,
                'errors' => $e->errors,
                'status' => $e->status,
            ], 'app/views/layouts/app', $e->status);
        }
    }

    public function destroy(Request $request, string $id): never
    {
        $user = Session::user();
        try {
            VehicleService::delete((int)$id, $user ? (int)$user['id'] : null);
            flash('success', 'Kendaraan berhasil dihapus.');
        } catch (HttpException $e) {
            flash('danger', $e->getMessage());
        }
        Response::redirect('/kendaraan');
    }
}
