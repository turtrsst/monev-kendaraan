<?php

declare(strict_types=1);

namespace Modules\Drivers\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Driver;
use App\Models\User;
use App\Services\DriverService;

final class DriverController
{
    public function index(Request $request): never
    {
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->query('per_page') ?? 15)));
        $search = $request->query('search');
        $status = $request->query('status');

        $result = Driver::paginate($page, $perPage, $search, $status);

        View::show('modules/drivers/views/index.php', [
            'title' => 'Master Driver',
            'drivers' => $result['data'],
            'pagination' => $result,
            'search' => $search ?? '',
            'status' => $status ?? '',
        ]);
    }

    public function create(Request $request): never
    {
        View::show('modules/drivers/views/form.php', [
            'title' => 'Tambah Driver',
            'driver' => null,
            'errors' => [],
        ]);
    }

    public function store(Request $request): never
    {
        $user = Session::user();
        try {
            $data = $request->all();
            DriverService::create($data, $user ? (int)$user['id'] : null);
            flash('success', 'Driver berhasil didaftarkan.');
            Response::redirect('/driver');
        } catch (HttpException $e) {
            View::show('modules/drivers/views/form.php', [
                'title' => 'Tambah Driver',
                'driver' => $request->all(),
                'errors' => $e->errors,
                'status' => $e->status,
            ], 'app/views/layouts/app', $e->status);
        }
    }

    public function edit(Request $request, string $id): never
    {
        $driver = Driver::find((int)$id);
        if ($driver === null) {
            throw new HttpException(404, 'Driver tidak ditemukan.');
        }

        View::show('modules/drivers/views/form.php', [
            'title' => 'Edit Driver: ' . $driver['name'],
            'driver' => $driver,
            'errors' => [],
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $user = Session::user();
        try {
            $data = $request->all();
            DriverService::update((int)$id, $data, $user ? (int)$user['id'] : null);
            flash('success', 'Data driver berhasil diperbarui.');
            Response::redirect('/driver');
        } catch (HttpException $e) {
            $driver = array_merge(['id' => (int)$id], $request->all());
            View::show('modules/drivers/views/form.php', [
                'title' => 'Edit Driver',
                'driver' => $driver,
                'errors' => $e->errors,
                'status' => $e->status,
            ], 'app/views/layouts/app', $e->status);
        }
    }

    public function destroy(Request $request, string $id): never
    {
        $user = Session::user();
        try {
            DriverService::delete((int)$id, $user ? (int)$user['id'] : null);
            flash('success', 'Driver berhasil dihapus.');
        } catch (HttpException $e) {
            flash('danger', $e->getMessage());
        }
        Response::redirect('/driver');
    }
}
