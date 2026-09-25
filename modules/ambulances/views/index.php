<?php
/** @var array<int,array<string,mixed>> $ambulances */
/** @var array<string,mixed> $pagination */
/** @var string $search */
/** @var string $readiness */
$readinessBadges = [
    'READY' => 'success',
    'STANDBY' => 'info',
    'MAINTENANCE' => 'warning',
    'UNAVAILABLE' => 'danger',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Profil Unit Ambulans</h1>
        <div class="text-muted small">Status kesiapan, medis, dan profil 1:1 armada ambulans rumah sakit</div>
    </div>
    <div>
        <a href="/ambulans/tambah" class="btn btn-danger btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Profil Ambulans
        </a>
    </div>
</div>

<!-- Filter card -->
<div class="card border-0 shadow-soft mb-3">
    <div class="card-body p-3">
        <form method="get" action="/ambulans" class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari kode unit, nopol, pangkalan..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="readiness" class="form-select form-select-sm">
                    <option value="">Semua Kesiapan</option>
                    <option value="READY" <?= $readiness === 'READY' ? 'selected' : '' ?>>READY (Siap Berangkat)</option>
                    <option value="STANDBY" <?= $readiness === 'STANDBY' ? 'selected' : '' ?>>STANDBY (Siaga di Posko)</option>
                    <option value="MAINTENANCE" <?= $readiness === 'MAINTENANCE' ? 'selected' : '' ?>>MAINTENANCE (Servis Alat/Mobil)</option>
                    <option value="UNAVAILABLE" <?= $readiness === 'UNAVAILABLE' ? 'selected' : '' ?>>UNAVAILABLE</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-dark btn-sm flex-fill">Filter</button>
                <a href="/ambulans" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- List table -->
<div class="card border-0 shadow-soft">
    <div class="card-body p-0">
        <?php if (empty($ambulances)): ?>
            <div class="text-center py-5">
                <i class="bi bi-hospital text-muted fs-1"></i>
                <p class="mt-2 mb-0 text-muted">Belum ada unit ambulans yang terdaftar.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>KODE &amp; UNIT</th>
                            <th>NOPOL MASTER</th>
                            <th>TIPE AMBULANS</th>
                            <th>PANGKALAN</th>
                            <th>KESIAPAN</th>
                            <th>BBM</th>
                            <th class="text-end">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ambulances as $a): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-danger"><i class="bi bi-hospital me-1"></i><?= e($a['ambulance_code']) ?></div>
                                    <div class="small text-dark"><?= e($a['ambulance_name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border font-monospace"><?= e($a['plate_number']) ?></span>
                                    <div class="small text-muted"><?= e($a['vehicle_name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge text-bg-secondary bg-opacity-10 text-dark border"><?= e($a['ambulance_type']) ?></span>
                                </td>
                                <td class="small">
                                    <i class="bi bi-geo-alt text-muted me-1"></i><?= e($a['base_location']) ?>
                                </td>
                                <td>
                                    <?php $badge = $readinessBadges[$a['readiness']] ?? 'secondary'; ?>
                                    <span class="badge text-bg-<?= $badge ?>"><?= e($a['readiness']) ?></span>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border font-monospace"><?= e($a['fuel_level']) ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item small" href="/ambulans/edit/<?= (int)$a['vehicle_id'] ?>">
                                                    <i class="bi bi-pencil me-2 text-primary"></i>Edit Profil
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item small" href="/kendaraan/edit/<?= (int)$a['vehicle_id'] ?>">
                                                    <i class="bi bi-car-front me-2 text-secondary"></i>Lihat Kendaraan Master
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post" action="/ambulans/hapus/<?= (int)$a['vehicle_id'] ?>" onsubmit="return confirm('Hapus profil ambulans <?= e($a['ambulance_code']) ?>? Data kendaraan master tetap tersimpan.');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="dropdown-item small text-danger">
                                                        <i class="bi bi-trash me-2"></i>Hapus Profil
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['last_page'] > 1): ?>
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        Total <?= $pagination['total'] ?> ambulans (Hal <?= $pagination['page'] ?> dari <?= $pagination['last_page'] ?>)
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                                <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="/ambulans?page=<?= $i ?>&search=<?= urlencode($search) ?>&readiness=<?= urlencode($readiness) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
