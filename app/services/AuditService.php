<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Session;
use App\Models\AuditLog;

/**
 * Audit trail wajib untuk semua aktivitas penting:
 * LOGIN, LOGOUT, CREATE, UPDATE, DELETE, START_TRIP, ARRIVAL, END_TRIP,
 * UPLOAD, OCR, SUBMIT, VERIFY, REJECT, PASSWORD_CHANGE, ...
 */
final class AuditService
{
    public static function log(
        string $action,
        ?string $entity = null,
        string|int|null $entityId = null,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        try {
            $request = App::$request;
            AuditLog::record(
                Session::userId(),
                $action,
                $entity,
                $entityId !== null ? (string)$entityId : null,
                $oldData,
                $newData,
                $request?->ip(),
                $request?->userAgent()
            );
        } catch (\Throwable $e) {
            logger('error', 'Audit gagal: ' . $e->getMessage(), ['action' => $action]);
        }
    }
}
