<?php

declare(strict_types=1);

namespace Api\Assignments;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Assignment;
use App\Services\AssignmentService;

final class AssignmentApiController
{
    public function index(Request $request): never
    {
        $user = Session::user();
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->query('per_page') ?? 15)));
        $search = $request->query('search');
        $status = $request->query('status');
        $date = $request->query('date');

        // Driver role only sees own assignments
        $driverId = null;
        if (($user['role'] ?? '') === 'driver') {
            $drv = \App\Models\Driver::findByUserId((int)$user['id']);
            $driverId = $drv ? (int)$drv['id'] : -1;
        }

        $result = Assignment::paginate($page, $perPage, $search, $status, $date, $driverId);
        Response::jsonOk('Daftar penugasan', $result);
    }

    public function show(Request $request, string $id): never
    {
        $user = Session::user();
        $asg = Assignment::find((int)$id);
        if ($asg === null) {
            Response::jsonError('Penugasan tidak ditemukan.', [], 404);
        }

        // Driver role IDOR protection
        if (($user['role'] ?? '') === 'driver') {
            $drv = \App\Models\Driver::findByUserId((int)$user['id']);
            if (!$drv || (int)$asg['driver_id'] !== (int)$drv['id']) {
                Response::jsonError('Akses ditolak.', [], 403);
            }
        }

        Response::jsonOk('Detail penugasan', ['assignment' => $asg]);
    }

    public function store(Request $request): never
    {
        $user = Session::user();
        $data = $request->all();
        $created = AssignmentService::create($data, $user ? (int)$user['id'] : null);
        Response::jsonOk('Penugasan berhasil dibuat.', ['assignment' => $created], 201);
    }

    public function update(Request $request, string $id): never
    {
        $user = Session::user();
        $data = $request->all();
        $updated = AssignmentService::update((int)$id, $data, $user ? (int)$user['id'] : null);
        Response::jsonOk('Penugasan berhasil diperbarui.', ['assignment' => $updated]);
    }

    public function cancel(Request $request, string $id): never
    {
        $user = Session::user();
        $data = $request->all();
        $reason = $data['reason'] ?? $data['notes'] ?? null;
        $cancelled = AssignmentService::cancel((int)$id, $reason, $user ? (int)$user['id'] : null);
        Response::jsonOk('Penugasan berhasil dibatalkan.', ['assignment' => $cancelled]);
    }
}
