<?php

declare(strict_types=1);

namespace Api\Drivers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Driver;
use App\Services\DriverService;

final class DriverApiController
{
    public function index(Request $request): never
    {
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->query('per_page') ?? 15)));
        $search = $request->query('search');
        $status = $request->query('status');

        $result = Driver::paginate($page, $perPage, $search, $status);
        Response::jsonOk('Daftar driver', $result);
    }

    public function show(Request $request, string $id): never
    {
        $driver = Driver::find((int)$id);
        if ($driver === null) {
            Response::jsonError('Driver tidak ditemukan.', [], 404);
        }

        Response::jsonOk('Detail driver', ['driver' => $driver]);
    }

    public function store(Request $request): never
    {
        $user = Session::user();
        $data = $request->all();
        $created = DriverService::create($data, $user ? (int)$user['id'] : null);
        Response::jsonOk('Driver berhasil ditambahkan.', ['driver' => $created], 201);
    }

    public function update(Request $request, string $id): never
    {
        $user = Session::user();
        $data = $request->all();
        $updated = DriverService::update((int)$id, $data, $user ? (int)$user['id'] : null);
        Response::jsonOk('Data driver berhasil diperbarui.', ['driver' => $updated]);
    }

    public function destroy(Request $request, string $id): never
    {
        $user = Session::user();
        DriverService::delete((int)$id, $user ? (int)$user['id'] : null);
        Response::jsonOk('Driver berhasil dihapus.');
    }
}
