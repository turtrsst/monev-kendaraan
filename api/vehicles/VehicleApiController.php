<?php

declare(strict_types=1);

namespace Api\Vehicles;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Vehicle;
use App\Services\VehicleService;

final class VehicleApiController
{
    public function index(Request $request): never
    {
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->query('per_page') ?? 15)));
        $search = $request->query('search');
        $status = $request->query('status');
        $type = $request->query('type');

        $result = Vehicle::paginate($page, $perPage, $search, $status, $type);
        Response::jsonOk('Daftar kendaraan', $result);
    }

    public function show(Request $request, string $id): never
    {
        $vehicle = Vehicle::find((int)$id);
        if ($vehicle === null) {
            Response::jsonError('Kendaraan tidak ditemukan.', [], 404);
        }

        Response::jsonOk('Detail kendaraan', ['vehicle' => $vehicle]);
    }

    public function store(Request $request): never
    {
        $user = Session::user();
        $data = $request->all();
        $created = VehicleService::create($data, $user ? (int)$user['id'] : null);
        Response::jsonOk('Kendaraan berhasil ditambahkan.', ['vehicle' => $created], 201);
    }

    public function update(Request $request, string $id): never
    {
        $user = Session::user();
        $data = $request->all();
        $updated = VehicleService::update((int)$id, $data, $user ? (int)$user['id'] : null);
        Response::jsonOk('Data kendaraan berhasil diperbarui.', ['vehicle' => $updated]);
    }

    public function destroy(Request $request, string $id): never
    {
        $user = Session::user();
        VehicleService::delete((int)$id, $user ? (int)$user['id'] : null);
        Response::jsonOk('Kendaraan berhasil dihapus.');
    }
}
