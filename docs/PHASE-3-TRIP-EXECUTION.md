# Phase 3 — Trip Execution & Digital Logbook

## Status and verification caveat

Phase 3 adds an executable trip layer over Phase 2 assignments. The Phase 2 implementation/runtime verdict in `PHASE-2-REPORT.md` is unchanged: its PHP/MySQL runtime verification remains **PENDING**. This sandbox has neither PHP CLI nor a MySQL/MariaDB client/daemon, so Phase 3 PHP unit, PHP syntax, database integration, HTTP, and Phase 2 regression suites could not be executed here. Static shell checks are not a substitute for runtime verification.

## Lifecycle

A trip is created from one `ASSIGNED` Phase 2 assignment. The trip stores immutable assignment, vehicle, driver, and destination-coordinate snapshots; it does not change or duplicate the unrelated vehicle/driver master data.

| Current state | Allowed next state | Event | Official server timestamp |
|---|---|---|---|
| `ASSIGNED` | `READY` | `READY` | — |
| `READY` | `STARTED` | `START` | `actual_started_at` |
| `STARTED` | `ARRIVED` | `ARRIVAL` | `actual_arrived_at` |
| `ARRIVED` | `RETURNING` | `RETURNING` | `actual_returning_at` |
| `RETURNING` | `COMPLETED` | `COMPLETED` | `actual_completed_at` |
| `COMPLETED` | `SUBMITTED` | `SUBMITTED` | `actual_submitted_at` |
| `SUBMITTED` | — | — | terminal |

There is no skip, reverse, or arbitrary status endpoint. `TripService` is the only transition authority; web and API handlers call its fixed action methods. A trip can be created only once for an active, non-cancelled `ASSIGNED` assignment whose vehicle and driver are active and whose driver license is valid for the assignment date.

## API endpoints

All routes use the existing front controller, authentication/session, and JSON response conventions. Every write route uses session CSRF middleware. API action handlers select a fixed server-side transition; a caller cannot submit a target status.

| Method | Endpoint | Role / behavior |
|---|---|---|
| `GET` | `/api/trips` | Authenticated list; driver result is scoped to own driver profile |
| `GET` | `/api/trips/{id}` | Authorized detail and event history |
| `POST` | `/api/trips` | Admin/operator: create from `assignment_id`, `action_uuid`, optional planned departure/notes |
| `POST` | `/api/trips/{id}/ready` | `ASSIGNED → READY` |
| `POST` | `/api/trips/{id}/start` | `READY → STARTED`; event GPS is optional/non-blocking |
| `POST` | `/api/trips/{id}/arrival` | `STARTED → ARRIVED`; destination GPS validation |
| `POST` | `/api/trips/{id}/returning` | `ARRIVED → RETURNING`; no GPS capture |
| `POST` | `/api/trips/{id}/complete` | `RETURNING → COMPLETED`; event GPS is optional/non-blocking |
| `POST` | `/api/trips/{id}/submit` | `COMPLETED → SUBMITTED` |

Successful responses follow `{"success":true,"message":"...","data":{...}}`; exceptions use the app's JSON error handler and HTTP status codes (401, 403/404, 409, 422, etc.). All writes require the existing session CSRF token in `_csrf` or `X-CSRF-Token`.

## Web driver workflow

- `/perjalanan` is a mobile-first board; driver rows are server-filtered to their own `drivers.user_id` relationship.
- `/perjalanan/{id}` shows current state, destination/vehicle/assignment, a lifecycle stepper, action history, and one primary next action.
- Admin/operator can create a trip from the `ASSIGNED` row on `/penugasan`.
- START, ARRIVAL, and COMPLETED ask the browser for one geolocation reading only when the action is submitted. If permission/GPS fails, the state action still records a `REVIEW_REQUIRED` GPS status instead of blocking the trip. No background or continuous location mode is registered.

## GPS event model and source of destination

GPS is event-based only:

- `START`, `ARRIVAL`, `COMPLETED`: latitude, longitude, accuracy, classification, and (for ARRIVAL only when configured) distance to destination.
- `READY`, `RETURNING`, and `SUBMITTED`: no GPS capture.

`occurred_at`, `recorded_at`, and trip `actual_*_at` fields are database/server time in `Asia/Jakarta`. `client_timestamp_untrusted` may be retained only as non-authoritative event metadata.

Assignments originally contained only textual destinations. Phase 3 adds nullable `assignments.destination_latitude` and `destination_longitude`, editable by authorized assignment staff as an explicit coordinate source. Both are required together and range-validated. There is no geocoder and no fabricated/default coordinate. The pair is snapshotted into `trip` on creation so later assignment edits cannot alter the event's validation target. If the coordinate pair is absent, ARRIVAL distance is `NULL` and classification is `WARNING`; the app does not claim destination verification.

Defaults are read from settings: destination radius 100 m, tolerance 50 m, and GPS accuracy limit 100 m. Admin settings can adjust all three. Valid/Warning/Review rules:

- `VALID`: reasonable accuracy and, for ARRIVAL with a configured destination, within radius.
- `WARNING`: poor-but-not-severe accuracy, just outside radius but within tolerance, or no configured destination coordinate.
- `REVIEW_REQUIRED`: missing GPS, impossible coordinate/accuracy, accuracy greater than 3× configured maximum, or ARRIVAL beyond radius plus tolerance.

The Haversine calculation uses mean Earth radius. GPS is supporting evidence, never absolute proof and never an automatic trip rejection.

## Authorization and IDOR

- Admin/operator: trip management and lifecycle operations, still subject to the state machine and assignment eligibility rules.
- Driver: reads/executes only trips whose snapshotted `driver_id` resolves to their own `drivers.user_id`.
- Pimpinan/monitor: read-only list/detail.
- Other-driver trip identifiers return 404 to hide object existence. All lookup/ownership checks occur on the server; no frontend visibility check is trusted.
- Vehicle, driver, and assignment IDs are never accepted from lifecycle action payloads. They are derived at creation and remain immutable in trip records.

## Idempotency, transaction, and concurrency

Every trip-creation call requires a UUID `action_uuid` stored in a unique `trip.create_uuid`; every lifecycle event requires a unique `trip_events.event_uuid`. UUID reuse only replays when actor, trip/action, and relevant payload match; otherwise the server returns 409. This supports safe mobile retry without duplicating transitions/events.

Each transition runs in one InnoDB transaction: `SELECT trip ... FOR UPDATE`, reload state, authorize actor/ownership, look up UUID, validate exactly one allowed transition, record event and server timestamp, update state, record audit log, commit. Audit writes are performed inside the same transaction and are not swallowed. Concurrent STARTs serialize on the trip row; only the first can change READY to STARTED. Unique assignment and UUID indexes provide an additional integrity barrier. Trip numbers are allocated using a per-year sequence row lock and a unique database key (`TRP-YYYY-NNNNN`).

## Audit history

Trip creation and READY/START/ARRIVAL/RETURNING/COMPLETED/SUBMITTED transitions write `audit_logs` records with actor, entity, event/status, event UUID and relevant GPS classification/distance metadata. Event detail is separately retained in `trip_events`. No credentials or secrets are included.

## Migrations and tests

- `database/migrations/007_phase3_trips.sql`: assignment coordinate fields; trip/sequence/event tables, foreign keys, checks, indexes and UUID uniqueness.
- `database/schema.sql`: current schema snapshot includes migration 007.
- `tests/unit_phase3.php`: 23 pure state/GPS assertions.
- `tests/integration_phase3.php`: real DB lifecycle, assignment eligibility, create/action idempotency, duplicate creation, invalid/terminal transition, cross-driver IDOR, GPS/audit persistence, and two-process concurrent START test. It creates uniquely prefixed fixtures and cleans them up. Run only on an isolated migrated `kendaraan_logbook` database, after explicit acknowledgement with `PHASE3_TEST_DB_OK=1`.
- `tests/http_phase3.sh`: authenticated integration check that a state-changing trip API request without CSRF returns 403 `csrf_invalid`; provide `BASE_URL`, `TEST_AUTH_USERNAME`, and `TEST_AUTH_PASSWORD` for a test account without forced password change.
- `tests/run_phase3_gate.sh`: static checks then PHP lint/unit; database integration is opt-in using `PHASE3_RUN_INTEGRATION=1 PHASE3_TEST_DB_OK=1`.
- Phase 2 regression: `tests/run_phase2_gate.sh`, unit Phase 2, and `tests/http_phase2.sh` should be run in a proper PHP 8.2 + MySQL/MariaDB test environment. They were not runnable in this sandbox, and the Phase 2 runtime verdict remains pending.

No OCR, fuel/e-Toll/expense ledger, equipment checklist, reporting dashboard, analytics, or continuous tracking was introduced.
