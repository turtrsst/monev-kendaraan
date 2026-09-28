<?php

declare(strict_types=1);

namespace Api\Trips;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\TripService;

final class TripApiController
{
    public function index(Request $request): never
    {
        $status = $request->query('status');
        $result = TripService::listForActor(
            Session::user() ?? [],
            max(1, (int)$request->query('page', 1)),
            min(100, max(1, (int)$request->query('per_page', 20))),
            $status !== null && $status !== '' ? (string)$status : null
        );
        Response::jsonOk('Daftar trip', $result);
    }

    public function show(Request $request, string $id): never
    {
        $trip = TripService::findAuthorized((int)$id, Session::user() ?? []);
        Response::jsonOk('Detail trip', ['trip' => $trip]);
    }

    public function store(Request $request): never
    {
        $input = $request->allPost();
        $assignmentId = filter_var($input['assignment_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$assignmentId || $assignmentId < 1) {
            throw new HttpException(422, 'assignment_id wajib berupa ID positif.', ['assignment_id' => 'ID tidak valid.']);
        }
        $result = TripService::create(
            (int)$assignmentId,
            (string)($input['action_uuid'] ?? ''),
            Session::user() ?? [],
            $input
        );
        Response::jsonOk('Trip berhasil dibuat.', $result, $result['idempotent'] ? 200 : 201);
    }

    public function ready(Request $request, string $id): never
    {
        $this->act($request, (int)$id, 'ready');
    }

    public function start(Request $request, string $id): never
    {
        $this->act($request, (int)$id, 'start');
    }

    public function arrival(Request $request, string $id): never
    {
        $this->act($request, (int)$id, 'arrival');
    }

    public function returning(Request $request, string $id): never
    {
        $this->act($request, (int)$id, 'returning');
    }

    public function complete(Request $request, string $id): never
    {
        $this->act($request, (int)$id, 'complete');
    }

    public function submit(Request $request, string $id): never
    {
        $this->act($request, (int)$id, 'submit');
    }

    private function act(Request $request, int $tripId, string $action): never
    {
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
        Response::jsonOk('Aksi trip berhasil diproses.', $result);
    }
}
