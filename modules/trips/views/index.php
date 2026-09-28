<?php
/** @var array<int,array<string,mixed>> $trips */
/** @var array<string,mixed> $pagination */
/** @var string $userRole */
$colors = [
    'ASSIGNED' => 'secondary', 'READY' => 'primary', 'STARTED' => 'warning',
    'ARRIVED' => 'info', 'RETURNING' => 'warning', 'COMPLETED' => 'success', 'SUBMITTED' => 'dark',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><?= $userRole === 'driver' ? 'Perjalanan Saya' : 'Trip &amp; Logbook' ?></h1>
        <div class="text-muted small">Status perjalanan dan tindakan berikutnya.</div>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/penugasan"><i class="bi bi-clipboard-check me-1"></i>Penugasan</a>
</div>

<?php if ($trips === []): ?>
    <section class="card border-0 shadow-soft">
        <div class="card-body text-center py-5">
            <div class="display-6 text-muted"><i class="bi bi-signpost-2"></i></div>
            <h2 class="h6 mt-3">Belum ada trip</h2>
            <p class="text-muted mb-0"><?= $userRole === 'driver' ? 'Trip dari penugasan yang disiapkan petugas akan muncul di sini.' : 'Trip akan muncul setelah dibuat dari penugasan yang berstatus ASSIGNED.' ?></p>
        </div>
    </section>
<?php else: ?>
    <div class="trip-card-list">
        <?php foreach ($trips as $trip): ?>
            <a class="card border-0 shadow-soft trip-list-card text-decoration-none" href="/perjalanan/<?= (int)$trip['id'] ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between gap-3 align-items-start">
                        <div class="min-w-0">
                            <div class="font-monospace fw-bold text-primary"><?= e($trip['trip_number']) ?></div>
                            <div class="fw-semibold text-dark mt-2"><?= e($trip['destination']) ?></div>
                            <div class="small text-muted mt-1"><?= e($trip['assignment_number']) ?> · <?= e($trip['assignment_date']) ?></div>
                        </div>
                        <span class="badge text-bg-<?= $colors[$trip['status']] ?? 'secondary' ?> flex-shrink-0"><?= e($trip['status']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between gap-2 mt-3 small text-muted">
                        <span><i class="bi bi-car-front me-1"></i><?= e($trip['plate_number']) ?></span>
                        <?php if ($userRole !== 'driver'): ?><span><i class="bi bi-person me-1"></i><?= e($trip['driver_name']) ?></span><?php endif; ?>
                        <span class="text-primary fw-semibold">Buka <i class="bi bi-chevron-right"></i></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.trip-card-list{display:grid;gap:.75rem}.trip-list-card{min-height:116px;border-radius:1rem;transition:transform .12s ease}.trip-list-card:active{transform:scale(.99)}
@media(min-width:768px){.trip-card-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
