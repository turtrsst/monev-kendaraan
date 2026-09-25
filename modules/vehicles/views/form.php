<?php
/** @var array<string,mixed>|null $vehicle */
/** @var array<string,string> $errors */
$isEdit = !empty($vehicle['id']);
$action = $isEdit ? '/kendaraan/edit/' . (int)$vehicle['id'] : '/kendaraan/tambah';
$val = static fn (string $k, string $d = ''): string => (string)($vehicle[$k] ?? $d);
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 mb-0"><?= $isEdit ? 'Edit Kendaraan' : 'Tambah Kendaraan Baru' ?></h1>
                <div class="text-muted small"><?= $isEdit ? 'Ubah data rincian kendaraan dinas' : 'Pendaftaran armada baru dalam master data' ?></div>
            </div>
            <a href="/kendaraan" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>

        <div class="card border-0 shadow-soft">
            <div class="card-body p-4">
                <form method="post" action="<?= $action ?>" class="row g-3">
                    <?= csrf_field() ?>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="vehicle_code">Kode Kendaraan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['vehicle_code']) ? 'is-invalid' : '' ?>"
                               id="vehicle_code" name="vehicle_code" value="<?= e($val('vehicle_code')) ?>"
                               placeholder="Contoh: VEH-001" required>
                        <?php if (isset($errors['vehicle_code'])): ?>
                            <div class="invalid-feedback"><?= e($errors['vehicle_code']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="plate_number">Nomor Polisi (Plat) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase <?= isset($errors['plate_number']) ? 'is-invalid' : '' ?>"
                               id="plate_number" name="plate_number" value="<?= e($val('plate_number')) ?>"
                               placeholder="Contoh: AD 1234 AB" required>
                        <?php if (isset($errors['plate_number'])): ?>
                            <div class="invalid-feedback"><?= e($errors['plate_number']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-8">
                        <label class="form-label" for="vehicle_name">Nama / Merk / Model <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['vehicle_name']) ? 'is-invalid' : '' ?>"
                               id="vehicle_name" name="vehicle_name" value="<?= e($val('vehicle_name')) ?>"
                               placeholder="Contoh: Toyota Avanza 1.3 G" required>
                        <?php if (isset($errors['vehicle_name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['vehicle_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="year">Tahun Pembuatan</label>
                        <input type="number" class="form-control <?= isset($errors['year']) ? 'is-invalid' : '' ?>"
                               id="year" name="year" value="<?= e($val('year')) ?>"
                               placeholder="2021" min="1980" max="2030">
                        <?php if (isset($errors['year'])): ?>
                            <div class="invalid-feedback"><?= e($errors['year']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="vehicle_type">Tipe Kendaraan</label>
                        <select class="form-select <?= isset($errors['vehicle_type']) ? 'is-invalid' : '' ?>" id="vehicle_type" name="vehicle_type">
                            <option value="OPERASIONAL" <?= $val('vehicle_type', 'OPERASIONAL') === 'OPERASIONAL' ? 'selected' : '' ?>>Operasional</option>
                            <option value="AMBULANCE" <?= $val('vehicle_type') === 'AMBULANCE' ? 'selected' : '' ?>>Ambulans</option>
                            <option value="LOGISTIK" <?= $val('vehicle_type') === 'LOGISTIK' ? 'selected' : '' ?>>Logistik</option>
                        </select>
                        <?php if (isset($errors['vehicle_type'])): ?>
                            <div class="invalid-feedback"><?= e($errors['vehicle_type']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="ownership">Kepemilikan</label>
                        <select class="form-select <?= isset($errors['ownership']) ? 'is-invalid' : '' ?>" id="ownership" name="ownership">
                            <option value="DINAS" <?= $val('ownership', 'DINAS') === 'DINAS' ? 'selected' : '' ?>>Dinas (RSUP)</option>
                            <option value="SEWA" <?= $val('ownership') === 'SEWA' ? 'selected' : '' ?>>Sewa / Vendor</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="status">Status Kendaraan</label>
                        <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" id="status" name="status">
                            <option value="ACTIVE" <?= $val('status', 'ACTIVE') === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE (Aktif Siap Pakai)</option>
                            <option value="MAINTENANCE" <?= $val('status') === 'MAINTENANCE' ? 'selected' : '' ?>>MAINTENANCE (Servis / Bengkel)</option>
                            <option value="INACTIVE" <?= $val('status') === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE (Tidak Aktif)</option>
                            <option value="RETIRED" <?= $val('status') === 'RETIRED' ? 'selected' : '' ?>>RETIRED (Purna Tugas)</option>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <div class="invalid-feedback"><?= e($errors['status']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="stnk_expiry">Masa Berlaku STNK</label>
                        <input type="date" class="form-control <?= isset($errors['stnk_expiry']) ? 'is-invalid' : '' ?>"
                               id="stnk_expiry" name="stnk_expiry" value="<?= e($val('stnk_expiry')) ?>">
                        <?php if (isset($errors['stnk_expiry'])): ?>
                            <div class="invalid-feedback"><?= e($errors['stnk_expiry']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="kir_expiry">Masa Berlaku KIR (Bila ada)</label>
                        <input type="date" class="form-control <?= isset($errors['kir_expiry']) ? 'is-invalid' : '' ?>"
                               id="kir_expiry" name="kir_expiry" value="<?= e($val('kir_expiry')) ?>">
                        <?php if (isset($errors['kir_expiry'])): ?>
                            <div class="invalid-feedback"><?= e($errors['kir_expiry']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="current_odometer">Odometer Terkini (km)</label>
                        <input type="number" class="form-control" id="current_odometer" name="current_odometer"
                               value="<?= e($val('current_odometer', '0')) ?>" min="0">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="notes">Catatan Tambahan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Informasi tambahan kendaraan..."><?= e($val('notes')) ?></textarea>
                    </div>

                    <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                        <a href="/kendaraan" class="btn btn-light border">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Simpan Kendaraan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
