<?php
/** @var array<string,mixed> $trip */
/** @var string $userRole */
$badgeColors = [
    'ASSIGNED' => 'secondary', 'READY' => 'primary', 'STARTED' => 'warning',
    'ARRIVED' => 'info', 'RETURNING' => 'warning', 'COMPLETED' => 'success', 'SUBMITTED' => 'dark',
];
$steps = ['ASSIGNED', 'READY', 'STARTED', 'ARRIVED', 'RETURNING', 'COMPLETED', 'SUBMITTED'];
$currentIndex = array_search($trip['status'], $steps, true);
$actions = [
    'ASSIGNED' => ['ready', 'Tandai Siap', 'bi-check2-circle', false],
    'READY' => ['start', 'Mulai Perjalanan', 'bi-play-fill', true],
    'STARTED' => ['arrival', 'Catat Kedatangan', 'bi-geo-alt-fill', true],
    'ARRIVED' => ['returning', 'Mulai Perjalanan Kembali', 'bi-arrow-return-left', false],
    'RETURNING' => ['complete', 'Selesaikan Perjalanan', 'bi-flag-fill', true],
    'COMPLETED' => ['submit', 'Kirim Logbook', 'bi-send-check-fill', false],
];
$action = ($userRole === 'pimpinan') ? null : ($actions[$trip['status']] ?? null);
$makeUuid = static function (): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
};
?>
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="/perjalanan" class="btn btn-light border btn-sm" aria-label="Kembali ke daftar trip"><i class="bi bi-arrow-left"></i></a>
    <div class="min-w-0">
        <div class="text-muted small">Digital Logbook</div>
        <h1 class="h5 mb-0 font-monospace"><?= e($trip['trip_number']) ?></h1>
    </div>
    <span class="badge text-bg-<?= $badgeColors[$trip['status']] ?? 'secondary' ?> ms-auto"><?= e($trip['status']) ?></span>
</div>

<div class="card border-0 shadow-soft mb-3 trip-summary">
    <div class="card-body">
        <div class="text-muted small">TUJUAN</div>
        <div class="h5 mt-1 mb-3"><?= e($trip['destination']) ?></div>
        <div class="row g-3">
            <div class="col-6">
                <div class="text-muted small">KENDARAAN</div>
                <div class="fw-semibold"><i class="bi bi-car-front me-1"></i><?= e($trip['plate_number']) ?></div>
                <div class="small text-muted"><?= e($trip['vehicle_name']) ?></div>
            </div>
            <div class="col-6">
                <div class="text-muted small">DRIVER</div>
                <div class="fw-semibold"><?= e($trip['driver_name']) ?></div>
            </div>
            <div class="col-6">
                <div class="text-muted small">PENUGASAN</div>
                <div class="fw-semibold"><?= e($trip['assignment_number']) ?></div>
                <div class="small text-muted"><?= e($trip['assignment_date']) ?></div>
            </div>
            <div class="col-6">
                <div class="text-muted small">RENCANA BERANGKAT</div>
                <div class="fw-semibold"><?= e($trip['planned_departure_at'] ?: 'Belum ditentukan') ?></div>
            </div>
        </div>
        <?php if (!empty($trip['purpose'])): ?><hr><div class="small text-muted">KEPERLUAN</div><div><?= nl2br(e($trip['purpose'])) ?></div><?php endif; ?>
        <?php if (!empty($trip['notes'])): ?><div class="small mt-3"><span class="text-muted">CATATAN</span><br><?= nl2br(e($trip['notes'])) ?></div><?php endif; ?>
        <div class="small mt-3 text-muted"><i class="bi bi-geo me-1"></i>
            <?= $trip['destination_latitude'] !== null && $trip['destination_longitude'] !== null
                ? 'Koordinat tujuan ditetapkan dari sumber yang dimasukkan petugas.'
                : 'Koordinat tujuan belum ditetapkan; jarak GPS tidak dapat diverifikasi.' ?>
        </div>
    </div>
</div>

<div class="card border-0 shadow-soft mb-3">
    <div class="card-body py-3">
        <div class="small fw-semibold text-muted mb-3">TAHAP PERJALANAN</div>
        <ol class="trip-stepper list-unstyled mb-0">
            <?php foreach ($steps as $i => $step): ?>
                <?php $done = $currentIndex !== false && $i < $currentIndex; $active = $step === $trip['status']; ?>
                <li class="trip-step <?= $done ? 'is-done' : '' ?> <?= $active ? 'is-current' : '' ?>">
                    <span class="trip-step-dot"><?= $done ? '<i class="bi bi-check-lg"></i>' : ($i + 1) ?></span>
                    <span><?= e($step) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</div>

<?php if ($action !== null): ?>
    <div class="trip-action-sticky">
        <form method="post" action="/perjalanan/<?= (int)$trip['id'] ?>/<?= e($action[0]) ?>" class="trip-action-form" data-gps="<?= $action[3] ? '1' : '0' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action_uuid" value="<?= e($makeUuid()) ?>">
            <?php if ($action[3]): ?>
                <input type="hidden" name="latitude" value="">
                <input type="hidden" name="longitude" value="">
                <input type="hidden" name="accuracy_m" value="">
                <input type="hidden" name="client_timestamp" value="">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-lg w-100 trip-action-button">
                <i class="bi <?= e($action[2]) ?> me-2"></i><span><?= e($action[1]) ?></span>
            </button>
            <?php if ($action[3]): ?><div class="small text-muted text-center mt-2">GPS dicatat satu kali untuk aksi ini. Jika GPS tidak tersedia, aksi tetap dapat disimpan dengan status pemeriksaan.</div><?php endif; ?>
        </form>
    </div>
<?php elseif ($trip['status'] === 'SUBMITTED'): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Logbook telah dikirim. Riwayat ini bersifat baca-saja.</div>
<?php elseif ($userRole === 'pimpinan'): ?>
    <div class="alert alert-light border"><i class="bi bi-eye me-2"></i>Mode pemantauan — tidak ada aksi yang dapat dijalankan.</div>
<?php endif; ?>

<section class="mt-4">
    <h2 class="h6 mb-3">Riwayat Aksi</h2>
    <?php if (empty($trip['events'])): ?>
        <div class="text-muted small">Belum ada aksi perjalanan.</div>
    <?php else: ?>
        <div class="trip-event-list">
            <?php foreach (array_reverse($trip['events']) as $event): ?>
                <?php $metadata = json_decode((string)($event['metadata_json'] ?? ''), true) ?: []; ?>
                <article class="trip-event card border-0 shadow-soft mb-2">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between gap-2">
                            <strong><?= e($event['event_type']) ?></strong>
                            <time class="small text-muted"><?= e($event['occurred_at']) ?></time>
                        </div>
                        <?php if (!empty($event['location_status'])): ?>
                            <div class="small mt-2">
                                <span class="badge text-bg-<?= $event['location_status'] === 'VALID' ? 'success' : ($event['location_status'] === 'WARNING' ? 'warning' : 'danger') ?>"><?= e($event['location_status']) ?></span>
                                <?php if ($event['distance_to_destination_m'] !== null): ?>
                                    <span class="text-muted ms-1"><?= e(number_format((float)$event['distance_to_destination_m'], 0)) ?> m dari titik tujuan</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($metadata['gps_reason'])): ?><div class="small text-muted mt-1"><?= e($metadata['gps_reason']) ?></div><?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($event['notes'])): ?><div class="small mt-2"><?= nl2br(e($event['notes'])) ?></div><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<style>
.trip-action-sticky{position:sticky;bottom:calc(4.25rem + env(safe-area-inset-bottom));z-index:5;background:rgba(248,249,250,.96);padding:.75rem .25rem;border-top:1px solid #dee2e6;margin:0 -.25rem}.trip-action-button{min-height:58px;border-radius:.9rem;font-weight:700;box-shadow:0 .35rem 1rem rgba(13,110,253,.2)}
.trip-stepper{display:flex;overflow-x:auto;gap:.45rem;padding-bottom:.25rem}.trip-step{flex:1 0 76px;display:flex;flex-direction:column;align-items:center;gap:.35rem;text-align:center;font-size:.65rem;color:#6c757d}.trip-step-dot{width:30px;height:30px;border:2px solid #ced4da;border-radius:50%;display:grid;place-items:center;background:#fff;font-size:.75rem}.trip-step.is-done{color:#198754}.trip-step.is-done .trip-step-dot{background:#198754;border-color:#198754;color:white}.trip-step.is-current{font-weight:700;color:#0d6efd}.trip-step.is-current .trip-step-dot{border-color:#0d6efd;color:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.12)}
@media(min-width:768px){.trip-action-sticky{bottom:0}.trip-summary{max-width:900px}}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.trip-action-form[data-gps="1"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.gpsReady === '1') return;
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            button.querySelector('span').textContent = 'Mengambil lokasi…';
            const submit = (position) => {
                if (form.dataset.gpsReady === '1') return;
                if (position) {
                    form.elements.latitude.value = position.coords.latitude;
                    form.elements.longitude.value = position.coords.longitude;
                    form.elements.accuracy_m.value = position.coords.accuracy;
                }
                form.elements.client_timestamp.value = new Date().toISOString();
                form.dataset.gpsReady = '1';
                form.requestSubmit();
            };
            if (!navigator.geolocation) {
                submit(null);
                return;
            }
            navigator.geolocation.getCurrentPosition(submit, () => submit(null), {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: 12000
            });
            window.setTimeout(() => {
                if (form.dataset.gpsReady !== '1') submit(null);
            }, 13000);
        });
    });
});
</script>
