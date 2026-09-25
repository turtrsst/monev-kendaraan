<?php
/** @var array<string,mixed>|null $driver */
/** @var array<string,string> $errors */
$isEdit = !empty($driver['id']);
$action = $isEdit ? '/driver/edit/' . (int)$driver['id'] : '/driver/tambah';
$val = static fn (string $k, string $d = ''): string => (string)($driver[$k] ?? $d);
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 mb-0"><?= $isEdit ? 'Edit Driver' : 'Tambah Driver Baru' ?></h1>
                <div class="text-muted small"><?= $isEdit ? 'Ubah informasi identitas dan lisensi pengemudi' : 'Pendaftaran pengemudi baru ke dalam master data' ?></div>
            </div>
            <a href="/driver" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>

        <div class="card border-0 shadow-soft">
            <div class="card-body p-4">
                <form method="post" action="<?= $action ?>" class="row g-3">
                    <?= csrf_field() ?>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="driver_code">Kode Driver <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['driver_code']) ? 'is-invalid' : '' ?>"
                               id="driver_code" name="driver_code" value="<?= e($val('driver_code')) ?>"
                               placeholder="Contoh: DRV-001" required>
                        <?php if (isset($errors['driver_code'])): ?>
                            <div class="invalid-feedback"><?= e($errors['driver_code']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="name">Nama Lengkap Driver <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               id="name" name="name" value="<?= e($val('name')) ?>"
                               placeholder="Contoh: Budi Santoso" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="phone">Nomor Telepon / WhatsApp <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                               id="phone" name="phone" value="<?= e($val('phone')) ?>"
                               placeholder="Contoh: 081234567890" required>
                        <?php if (isset($errors['phone'])): ?>
                            <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="status">Status Driver</label>
                        <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" id="status" name="status">
                            <option value="ACTIVE" <?= $val('status', 'ACTIVE') === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE (Aktif Siap Tugas)</option>
                            <option value="INACTIVE" <?= $val('status') === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE (Tidak Aktif / Cuti)</option>
                            <option value="SUSPENDED" <?= $val('status') === 'SUSPENDED' ? 'selected' : '' ?>>SUSPENDED (Skorsing)</option>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <div class="invalid-feedback"><?= e($errors['status']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="license_type">Golongan SIM</label>
                        <select class="form-select <?= isset($errors['license_type']) ? 'is-invalid' : '' ?>" id="license_type" name="license_type">
                            <option value="SIM A" <?= $val('license_type', 'SIM A') === 'SIM A' ? 'selected' : '' ?>>SIM A (Mobil Pribadi/Operasional)</option>
                            <option value="SIM B1" <?= $val('license_type') === 'SIM B1' ? 'selected' : '' ?>>SIM B1</option>
                            <option value="SIM B1 UMUM" <?= $val('license_type') === 'SIM B1 UMUM' ? 'selected' : '' ?>>SIM B1 Umum (Ambulans/Minibus)</option>
                            <option value="SIM B2 UMUM" <?= $val('license_type') === 'SIM B2 UMUM' ? 'selected' : '' ?>>SIM B2 Umum</option>
                        </select>
                        <?php if (isset($errors['license_type'])): ?>
                            <div class="invalid-feedback"><?= e($errors['license_type']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="license_number">Nomor SIM <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['license_number']) ? 'is-invalid' : '' ?>"
                               id="license_number" name="license_number" value="<?= e($val('license_number')) ?>"
                               placeholder="12 digit nomor SIM" required>
                        <?php if (isset($errors['license_number'])): ?>
                            <div class="invalid-feedback"><?= e($errors['license_number']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="license_expiry">Masa Berlaku SIM <span class="text-danger">*</span></label>
                        <input type="date" class="form-control <?= isset($errors['license_expiry']) ? 'is-invalid' : '' ?>"
                               id="license_expiry" name="license_expiry" value="<?= e($val('license_expiry')) ?>" required>
                        <?php if (isset($errors['license_expiry'])): ?>
                            <div class="invalid-feedback"><?= e($errors['license_expiry']) ?></div>
                        <?php endif; ?>
                        <div class="form-text small">SIM kadaluarsa akan dicekal saat penugasan.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="notes">Catatan Driver</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Sertifikasi, riwayat tugas, atau catatan khusus..."><?= e($val('notes')) ?></textarea>
                    </div>

                    <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                        <a href="/driver" class="btn btn-light border">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Simpan Driver
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
