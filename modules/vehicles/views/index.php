<?php
/** @var array<int,array<string,mixed>> $vehicles */
/** @var array<string,mixed> $pagination */
/** @var string $search */
/** @var string $status */
/** @var string $type */
$statusBadges = [
    'ACTIVE' => 'success',
    'MAINTENANCE' => 'warning',
    'INACTIVE' => 'secondary',
    'RETIRED' => 'dark',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Master Kendaraan</h1>
        <div class="text-muted small">Kelola data seluruh armada mobil dinas &amp; operasional</div>
    </div>
    <div>
        <a href="/kendaraan/tambah" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Kendaraan
        </a>
    </div>
</div>

<!-- Filter card -->
<div class="card border-0 shadow-soft mb-3">
    <div class="card-body p-3">
        <form method="get" action="/kendaraan" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nopol, kode, nama..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                    <option value="MAINTENANCE" <?= $status === 'MAINTENANCE' ? 'selected' : '' ?>>MAINTENANCE</option>
                    <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                    <option value="RETIRED" <?= $status === 'RETIRED' ? 'selected' : '' ?>>RETIRED</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">Semua Tipe</option>
                    <option value="OPERASIONAL" <?= $type === 'OPERASIONAL' ? 'selected' : '' ?>>Operasional</option>
                    <option value="AMBULANCE" <?= $type === 'AMBULANCE' ? 'selected' : '' ?>>Ambulans</option>
                    <option value="LOGISTIK" <?= $type === 'LOGISTIK' ? 'selected' : '' ?>>Logistik</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-dark btn-sm flex-fill">Filter</button>
                <a href="/kendaraan" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- List for Mobile & Desktop -->
<div class="card border-0 shadow-soft">
    <div class="card-body p-0">
        <?php if (empty($vehicles)): ?>
            <div class="text-center py-5">
                <i class="bi bi-car-front text-muted fs-1"></i>
                <p class="mt-2 mb-0 text-muted">Belum ada data kendaraan yang cocok.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>KODE</th>
                            <th>NOPOL &amp; NAMA</th>
                            <th>TIPE</th>
                            <th>STATUS</th>
                            <th>PROFIL AMBULANS</th>
                            <th class="text-end">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $v): ?>
                            <tr>
                                <td>
                                    <span class="badge text-bg-light border"><?= e($v['vehicle_code']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($v['plate_number']) ?></div>
                                    <div class="small text-muted"><?= e($v['vehicle_name']) ?> (<?= e((string)($v['year'] ?? '-')) ?>)</div>
                                </td>
                                <td>
                                    <span class="badge text-bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?= e($v['vehicle_type']) ?></span>
                                </td>
                                <td>
                                    <?php $badge = $statusBadges[$v['status']] ?? 'secondary'; ?>
                                    <span class="badge text-bg-<?= $badge ?>"><?= e($v['status']) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($v['ambulance_code'])): ?>
                                        <a href="/ambulans" class="badge text-bg-danger text-decoration-none">
                                            <i class="bi bi-hospital me-1"></i><?= e($v['ambulance_code']) ?> (<?= e($v['ambulance_readiness'] ?? 'READY') ?>)
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item small" href="/kendaraan/edit/<?= (int)$v['id'] ?>">
                                                    <i class="bi bi-pencil me-2 text-primary"></i>Edit Data
                                                </a>
                                            </li>
                                            <?php if (empty($v['ambulance_code'])): ?>
                                                <li>
                                                    <a class="dropdown-item small" href="/ambulans/tambah?vehicle_id=<?= (int)$v['id'] ?>">
                                                        <i class="bi bi-plus-circle me-2 text-danger"></i>Set Profil Ambulans
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post" action="/kendaraan/hapus/<?= (int)$v['id'] ?>" onsubmit="return confirm('Hapus kendaraan <?= e($v['plate_number']) ?>?');">
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
                        Total <?= $pagination['total'] ?> kendaraan (Hal <?= $pagination['page'] ?> dari <?= $pagination['last_page'] ?>)
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                                <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="/kendaraan?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
