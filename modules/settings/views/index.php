<?php

/** @var array<int,array<string,mixed>> $rows */
/** @var array<string,array<string,mixed>> $current */
$errors = $errors ?? [];
$cur = static fn (string $k, string $d = ''): string => (string)($current[$k]['value'] ?? $d);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Pengaturan</h1>
        <div class="text-muted small">Konfigurasi sistem tersimpan di database — tidak ada hard-code.</div>
    </div>
    <span class="badge text-bg-primary"><i class="bi bi-shield-check me-1"></i>Admin</span>
</div>

<div class="card border-0 shadow-soft mb-3">
    <div class="card-header bg-white"><strong>Ubah pengaturan umum</strong></div>
    <div class="card-body">
        <form method="post" action="/pengaturan" class="row g-3">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="col-12 col-md-6">
                <label class="form-label" for="app.hospital_name">Nama rumah sakit</label>
                <input class="form-control <?= isset($errors['app.hospital_name']) ? 'is-invalid' : '' ?>"
                       id="app.hospital_name" name="app.hospital_name" maxlength="100" required
                       value="<?= e($errors['app.hospital_name'] ?? $cur('app.hospital_name')) ?>">
                <?php if (isset($errors['app.hospital_name'])): ?>
                    <div class="invalid-feedback"><?= e($errors['app.hospital_name']) ?></div>
                <?php endif; ?>
                <div class="form-text">Ditampilkan di login, navbar, dan laporan — bukan hard-code.</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="app.logo_path">Path logo (URL relatif / aset)</label>
                <input class="form-control <?= isset($errors['app.logo_path']) ? 'is-invalid' : '' ?>"
                       id="app.logo_path" name="app.logo_path" maxlength="255"
                       value="<?= e($errors['app.logo_path'] ?? $cur('app.logo_path', '/assets/images/logo.svg')) ?>">
                <?php if (isset($errors['app.logo_path'])): ?>
                    <div class="invalid-feedback"><?= e($errors['app.logo_path']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="trip.destination_radius_default_m">Radius validasi tujuan (meter)</label>
                <input type="number" min="10" max="10000"
                       class="form-control <?= isset($errors['trip.destination_radius_default_m']) ? 'is-invalid' : '' ?>"
                       id="trip.destination_radius_default_m" name="trip.destination_radius_default_m"
                       value="<?= e($errors['trip.destination_radius_default_m'] ?? $cur('trip.destination_radius_default_m', '100')) ?>">
                <?php if (isset($errors['trip.destination_radius_default_m'])): ?>
                    <div class="invalid-feedback"><?= e($errors['trip.destination_radius_default_m']) ?></div>
                <?php endif; ?>
                <div class="form-text">≤radius=VALID · ±toleransi=WARNING · di luar=REVIEW_REQUIRED.</div>
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" value="1" role="switch"
                           id="fuel.receipt_photo_required" name="fuel.receipt_photo_required"
                        <?= $cur('fuel.receipt_photo_required', '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="fuel.receipt_photo_required">
                        Wajibkan foto struk BBM <span class="text-muted small">(default: non-blocking)</span>
                    </label>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary" data-loading-text="Menyimpan…">
                    <i class="bi bi-save me-1"></i>Simpan pengaturan
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-soft">
    <div class="card-header bg-white"><strong>Semua kunci</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 settings-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:38%">Kunci</th>
                        <th>Nilai</th>
                        <th class="d-none d-lg-table-cell">Keterangan</th>
                        <th class="d-none d-md-table-cell">Diperbarui</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><code><?= e($row['skey']) ?></code></td>
                        <td class="small"><?= e($row['value'] ?? '') ?></td>
                        <td class="small text-muted d-none d-lg-table-cell"><?= e($row['description'] ?? '') ?></td>
                        <td class="small text-muted d-none d-md-table-cell"><?= e($row['updated_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
