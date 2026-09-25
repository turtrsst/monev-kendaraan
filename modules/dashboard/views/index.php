<?php
/** @var array<int,array{label:string,value:string,icon:string,note:string}> $stats */
/** @var array<int,array<string,mixed>> $notifications */
/** @var int $unread */
$user = $user ?? auth_user();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><?= e(greeting()) ?>,<?= ' ' . e($user['name'] ?? '') ?></h1>
        <div class="text-muted small"><?= e($user['role_name'] ?? '') ?> · <?= e(date('d F Y')) ?> WIB</div>
    </div>
</div>

<?php if (!empty($user['force_password_change'])): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Anda masih menggunakan password bootstrap. <a href="/ganti-password">Ganti sekarang</a>.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $s): ?>
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100 border-0 shadow-soft">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="text-muted small"><?= e($s['label']) ?></span>
                        <i class="bi <?= e($s['icon']) ?> text-primary"></i>
                    </div>
                    <div class="fs-5 fw-semibold mt-1"><?= e($s['value']) ?></div>
                    <div class="small text-muted"><?= e($s['note']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-soft">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-bell me-2"></i>Notifikasi
                <?php if ($unread > 0): ?>
                    <span class="badge text-bg-danger"><?= (int)$unread ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p class="mb-1 fw-semibold">Belum ada notifikasi</p>
                        <p class="small text-muted mb-0">Assignment baru, pengingat STNK/KIR/SIM akan muncul di sini.</p>
                    </div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($notifications as $n): ?>
                            <li class="border-bottom py-2 last-border-none">
                                <div class="fw-semibold small"><?= e($n['title']) ?></div>
                                <div class="text-muted small"><?= e($n['body'] ?? '') ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-soft">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-signpost me-2"></i>Peta Modul</div>
            <div class="card-body small">
                <p class="text-muted mb-2">Aplikasi dibangun bertahap sesuai phase yang telah disetujui:</p>
                <ul class="mb-0 phase-list">
                    <li class="done"><i class="bi bi-check-circle-fill"></i> Phase 1 — Foundation (Kernel &amp; Auth)</li>
                    <li class="done"><i class="bi bi-check-circle-fill"></i> Phase 2 — Master Data &amp; Penugasan (Selesai)</li>
                    <li><i class="bi bi-circle"></i> Phase 3 — Trip Lifecycle &amp; State Machine (TODO)</li>
                    <li><i class="bi bi-circle"></i> Phase 4–5 — Driver Mobile, GPS &amp; Evidence (TODO)</li>
                    <li><i class="bi bi-circle"></i> Phase 6–9 — Expense, OCR, Dokumen, Verifikasi (TODO)</li>
                    <li><i class="bi bi-circle"></i> Phase 10–12 — Dashboard, Laporan, Hardening (TODO)</li>
                </ul>
            </div>
        </div>
    </div>
</div>
