<?php

declare(strict_types=1);

namespace Api\Ambulances;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Ambulance;
use App\Services\AmbulanceService;

final class AmbulanceApiController
{
    public function index(Request $request): never
    {
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->query('per_page') ?? 15)));
        $search = $request->query('search');
        $readiness = $request->query('readiness');

        $result = Ambulance::paginate($page, $perPage, $search, $readiness);
        Response::jsonOk('Daftar profil ambulans', $result);
    }

    public function show(Request $request, string $vehicleId): never
    {
        $amb = Ambulance::find((int)$vehicleId);
        if ($amb === null) {
            Response::jsonError('Profil ambulans tidak ditemukan.', [], 404);
        }

        Response::jsonOk('Detail profil ambulans', ['ambulance' => $amb]);
    }

    public function store(Request $request): never
    {
        $user = Session::user();
        $data = $request->all();
        $created = AmbulanceService::create($data, $user ? (int)$user['id'] : null);
        Response::jsonOk('Profil ambulans berhasil ditambahkan.', ['ambulance' => $created], 201);
    }

    public function update(Request $request, string $vehicleId): never
    {
        $user = Session::user();
        $data = $request->all();
        $updated = AmbulanceService::update((int)$vehicleId, $data, $user ? (int)$user['id'] : null);
        Response::jsonOk('Profil ambulans berhasil diperbarui.', ['ambulance' => $updated]);
    }

    public function destroy(Request $request, string $vehicleId): never
    {
        $user = Session::user();
        AmbulanceService::delete((int)$vehicleId, $user ? (int)$user['id'] : null);
        Response::jsonOk('Profil ambulans berhasil dihapus.');
    }
}
