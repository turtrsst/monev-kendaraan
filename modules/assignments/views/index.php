<?php
/** @var array<int,array<string,mixed>> $assignments */
/** @var array<string,mixed> $pagination */
/** @var string $search */
/** @var string $status */
/** @var string $date */
/** @var string $userRole */
$statusBadges = [
    'DRAFT' => 'secondary',
    'ASSIGNED' => 'primary',
    'CANCELLED' => 'danger',
];
$canManage = in_array($userRole, ['admin', 'operator'], true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Manajemen Penugasan</h1>
        <div class="text-muted small">Daftar surat tugas &amp; instruksi penugasan armada</div>
    </div>
    <?php if ($canManage): ?>
        <div>
            <a href="/penugasan/tambah" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Buat Penugasan
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Filter card -->
<div class="card border-0 shadow-soft mb-3">
    <div class="card-body p-3">
        <form method="get" action="/penugasan" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nomor, nopol, driver, tujuan..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="ASSIGNED" <?= $status === 'ASSIGNED' ? 'selected' : '' ?>>ASSIGNED (Ditugaskan)</option>
                    <option value="DRAFT" <?= $status === 'DRAFT' ? 'selected' : '' ?>>DRAFT</option>
                    <option value="CANCELLED" <?= $status === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED (Dibatalkan)</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-dark btn-sm flex-fill">Filter</button>
                <a href="/penugasan" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- List table -->
<div class="card border-0 shadow-soft">
    <div class="card-body p-0">
        <?php if (empty($assignments)): ?>
            <div class="text-center py-5">
                <i class="bi bi-clipboard-x text-muted fs-1"></i>
                <p class="mt-2 mb-0 text-muted">Belum ada data penugasan.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>NO. TUGAS &amp; TANGGAL</th>
                            <th>ARMADA</th>
                            <th>DRIVER</th>
                            <th>TUJUAN &amp; KEPERLUAN</th>
                            <th>STATUS</th>
                            <?php if ($canManage): ?>
                                <th class="text-end">AKSI</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $a): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-primary font-monospace"><?= e($a['assignment_number']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-calendar-event me-1"></i><?= e($a['assignment_date']) ?></div>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border font-monospace"><?= e($a['plate_number']) ?></span>
                                    <div class="small text-muted"><?= e($a['vehicle_name']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($a['driver_name']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($a['driver_phone']) ?></div>
                                </td>
                                <td>
                                    <div class="text-dark fw-medium"><?= e($a['destination']) ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 280px;"><?= e($a['purpose']) ?></div>
                                </td>
                                <td>
                                    <?php $badge = $statusBadges[$a['status']] ?? 'secondary'; ?>
                                    <span class="badge text-bg-<?= $badge ?>"><?= e($a['status']) ?></span>
                                </td>
                                <?php if ($canManage): ?>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <?php if ($a['status'] !== 'CANCELLED'): ?>
                                                    <li>
                                                        <a class="dropdown-item small" href="/penugasan/edit/<?= (int)$a['id'] ?>">
                                                            <i class="bi bi-pencil me-2 text-primary"></i>Edit Penugasan
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <form method="post" action="/penugasan/batal/<?= (int)$a['id'] ?>" onsubmit="return confirm('Batalkan penugasan <?= e($a['assignment_number']) ?>?');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="dropdown-item small text-danger">
                                                                <i class="bi bi-x-circle me-2"></i>Batalkan Penugasan
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php else: ?>
                                                    <li><span class="dropdown-item-text small text-muted">Penugasan Dibatalkan</span></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['last_page'] > 1): ?>
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        Total <?= $pagination['total'] ?> penugasan (Hal <?= $pagination['page'] ?> dari <?= $pagination['last_page'] ?>)
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                                <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="/penugasan?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&date=<?= urlencode($date) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
