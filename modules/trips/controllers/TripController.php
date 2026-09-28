<?php

declare(strict_types=1);

namespace Modules\Trips\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\TripService;

final class TripController
{
    public function index(Request $request): never
    {
        $actor = Session::user() ?? [];
        $status = $request->query('status');
        $result = TripService::listForActor(
            $actor,
            max(1, (int)$request->query('page', 1)),
            min(100, max(1, (int)$request->query('per_page', 20))),
            $status !== null && $status !== '' ? (string)$status : null
        );
        View::show('modules/trips/views/index.php', [
            'title' => 'Perjalanan Saya',
            'trips' => $result['data'],
            'pagination' => $result,
            'userRole' => $actor['role'] ?? '',
            'statusFilter' => $status ?? '',
        ]);
    }

    public function show(Request $request, string $id): never
    {
        $trip = TripService::findAuthorized((int)$id, Session::user() ?? []);
        View::show('modules/trips/views/show.php', [
            'title' => 'Logbook ' . $trip['trip_number'],
            'trip' => $trip,
            'userRole' => (Session::user() ?? [])['role'] ?? '',
        ]);
    }

    public function create(Request $request, string $assignmentId): never
    {
        try {
            $input = $request->allPost();
            $result = TripService::create(
                (int)$assignmentId,
                (string)($input['action_uuid'] ?? ''),
                Session::user() ?? [],
                $input
            );
            flash('success', $result['idempotent'] ? 'Permintaan pembuatan trip telah diproses sebelumnya.' : 'Trip berhasil dibuat.');
            Response::redirect('/perjalanan/' . (int)$result['trip']['id']);
        } catch (HttpException $e) {
            flash('danger', $e->getMessage());
            Response::redirect('/penugasan');
        }
    }

    public function ready(Request $request, string $id): never { $this->perform($request, (int)$id, 'ready'); }
    public function start(Request $request, string $id): never { $this->perform($request, (int)$id, 'start'); }
    public function arrival(Request $request, string $id): never { $this->perform($request, (int)$id, 'arrival'); }
    public function returning(Request $request, string $id): never { $this->perform($request, (int)$id, 'returning'); }
    public function complete(Request $request, string $id): never { $this->perform($request, (int)$id, 'complete'); }
    public function submit(Request $request, string $id): never { $this->perform($request, (int)$id, 'submit'); }

    private function perform(Request $request, int $tripId, string $action): never
    {
        try {
            $input = $request->allPost();
            $method = match ($action) {
                'ready' => 'ready',
                'start' => 'start',
                'arrival' => 'arrival',
                'returning' => 'returning',
                'complete' => 'complete',
                'submit' => 'submit',
            };
            $result = TripService::$method(
                $tripId,
                (string)($input['action_uuid'] ?? ''),
                Session::user() ?? [],
                $input
            );
            flash('success', $result['idempotent'] ? 'Aksi ini sudah berhasil diproses sebelumnya.' : 'Status perjalanan berhasil diperbarui.');
        } catch (HttpException $e) {
            flash('danger', $e->getMessage());
        }
        Response::redirect('/perjalanan/' . $tripId);
    }
}
