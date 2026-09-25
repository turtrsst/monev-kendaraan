<?php
/** @var array<int,array<string,mixed>> $drivers */
/** @var array<string,mixed> $pagination */
/** @var string $search */
/** @var string $status */
$statusBadges = [
    'ACTIVE' => 'success',
    'INACTIVE' => 'secondary',
    'SUSPENDED' => 'danger',
];
$today = date('Y-m-d');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Master Driver</h1>
        <div class="text-muted small">Kelola data seluruh pengemudi resmi &amp; ambulans</div>
    </div>
    <div>
        <a href="/driver/tambah" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus me-1"></i>Tambah Driver
        </a>
    </div>
</div>

<!-- Filter card -->
<div class="card border-0 shadow-soft mb-3">
    <div class="card-body p-3">
        <form method="get" action="/driver" class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama, kode, nomor HP, SIM..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                    <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                    <option value="SUSPENDED" <?= $status === 'SUSPENDED' ? 'selected' : '' ?>>SUSPENDED</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-dark btn-sm flex-fill">Filter</button>
                <a href="/driver" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- List table -->
<div class="card border-0 shadow-soft">
    <div class="card-body p-0">
        <?php if (empty($drivers)): ?>
            <div class="text-center py-5">
                <i class="bi bi-person-badge text-muted fs-1"></i>
                <p class="mt-2 mb-0 text-muted">Belum ada data driver yang cocok.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>KODE</th>
                            <th>NAMA &amp; HP</th>
                            <th>LISENSI / SIM</th>
                            <th>MASA BERLAKU</th>
                            <th>STATUS</th>
                            <th class="text-end">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drivers as $d): ?>
                            <?php $isSimExpired = ($d['license_expiry'] < $today); ?>
                            <tr>
                                <td>
                                    <span class="badge text-bg-light border"><?= e($d['driver_code']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($d['name']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($d['phone']) ?></div>
                                </td>
                                <td>
                                    <span class="badge text-bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?= e($d['license_type']) ?></span>
                                    <span class="small font-monospace ms-1 text-muted"><?= e($d['license_number']) ?></span>
                                </td>
                                <td>
                                    <?php if ($isSimExpired): ?>
                                        <span class="badge text-bg-danger" title="SIM Kadaluarsa">
                                            <i class="bi bi-exclamation-triangle me-1"></i><?= e($d['license_expiry']) ?> (EXPIRED)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-dark small"><?= e($d['license_expiry']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $badge = $statusBadges[$d['status']] ?? 'secondary'; ?>
                                    <span class="badge text-bg-<?= $badge ?>"><?= e($d['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item small" href="/driver/edit/<?= (int)$d['id'] ?>">
                                                    <i class="bi bi-pencil me-2 text-primary"></i>Edit Data
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post" action="/driver/hapus/<?= (int)$d['id'] ?>" onsubmit="return confirm('Hapus driver <?= e($d['name']) ?>?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="dropdown-item small text-danger">
                                                        <i class="bi bi-trash me-2"></i>Hapus
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
                        Total <?= $pagination['total'] ?> driver (Hal <?= $pagination['page'] ?> dari <?= $pagination['last_page'] ?>)
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                                <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="/driver?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
