<?php
/** @var array<string,mixed>|null $ambulance */
/** @var array<int,array<string,mixed>> $vehicles */
/** @var bool $isEdit */
/** @var array<string,string> $errors */
$vid = (int)($ambulance['vehicle_id'] ?? 0);
$action = $isEdit ? '/ambulans/edit/' . $vid : '/ambulans/tambah';
$val = static fn (string $k, string $d = ''): string => (string)($ambulance[$k] ?? $d);
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 mb-0"><?= $isEdit ? 'Edit Profil Ambulans' : 'Tambah Profil Ambulans' ?></h1>
                <div class="text-muted small">Profil ambulans melekat 1:1 pada data master kendaraan</div>
            </div>
            <a href="/ambulans" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>

        <div class="card border-0 shadow-soft">
            <div class="card-body p-4">
                <form method="post" action="<?= $action ?>" class="row g-3">
                    <?= csrf_field() ?>

                    <div class="col-12">
                        <label class="form-label" for="vehicle_id">Kendaraan Master <span class="text-danger">*</span></label>
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="vehicle_id" value="<?= $vid ?>">
                            <input type="text" class="form-control bg-light" readonly
                                   value="<?= e($val('plate_number')) ?> — <?= e($val('vehicle_name')) ?>">
                            <div class="form-text small">Kendaraan master tidak dapat diganti setelah profil dibuat.</div>
                        <?php else: ?>
                            <select class="form-select <?= isset($errors['vehicle_id']) ? 'is-invalid' : '' ?>" id="vehicle_id" name="vehicle_id" required>
                                <option value="">-- Pilih Kendaraan Master --</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?= (int)$v['id'] ?>" <?= (int)$val('vehicle_id') === (int)$v['id'] ? 'selected' : '' ?>>
                                        <?= e($v['plate_number']) ?> — <?= e($v['vehicle_name']) ?> (<?= e($v['vehicle_type']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['vehicle_id'])): ?>
                                <div class="invalid-feedback"><?= e($errors['vehicle_id']) ?></div>
                            <?php endif; ?>
                            <div class="form-text small">Pilih kendaraan dari master kendaraan yang belum memiliki profil ambulans.</div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="ambulance_code">Kode Ambulans <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['ambulance_code']) ? 'is-invalid' : '' ?>"
                               id="ambulance_code" name="ambulance_code" value="<?= e($val('ambulance_code')) ?>"
                               placeholder="Contoh: AMB-01" required>
                        <?php if (isset($errors['ambulance_code'])): ?>
                            <div class="invalid-feedback"><?= e($errors['ambulance_code']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="ambulance_name">Nama Unit Ambulans <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['ambulance_name']) ? 'is-invalid' : '' ?>"
                               id="ambulance_name" name="ambulance_name" value="<?= e($val('ambulance_name')) ?>"
                               placeholder="Contoh: Ambulans AGD Advance ICU" required>
                        <?php if (isset($errors['ambulance_name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['ambulance_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="ambulance_type">Tipe Unit</label>
                        <select class="form-select <?= isset($errors['ambulance_type']) ? 'is-invalid' : '' ?>" id="ambulance_type" name="ambulance_type">
                            <option value="TRANSPORT" <?= $val('ambulance_type', 'TRANSPORT') === 'TRANSPORT' ? 'selected' : '' ?>>Transport Standar</option>
                            <option value="ICU_ADVANCE" <?= $val('ambulance_type') === 'ICU_ADVANCE' ? 'selected' : '' ?>>Gawat Darurat / ICU</option>
                            <option value="JENAZAH" <?= $val('ambulance_type') === 'JENAZAH' ? 'selected' : '' ?>>Mobil Jenazah</option>
                            <option value="MEDIS_KHUSUS" <?= $val('ambulance_type') === 'MEDIS_KHUSUS' ? 'selected' : '' ?>>Medis Khusus / Isolasi</option>
                        </select>
                        <?php if (isset($errors['ambulance_type'])): ?>
                            <div class="invalid-feedback"><?= e($errors['ambulance_type']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="readiness">Kesiapan Unit (Readiness)</label>
                        <select class="form-select <?= isset($errors['readiness']) ? 'is-invalid' : '' ?>" id="readiness" name="readiness">
                            <option value="READY" <?= $val('readiness', 'READY') === 'READY' ? 'selected' : '' ?>>READY (Siap Berangkat)</option>
                            <option value="STANDBY" <?= $val('readiness') === 'STANDBY' ? 'selected' : '' ?>>STANDBY (Siaga Posko)</option>
                            <option value="MAINTENANCE" <?= $val('readiness') === 'MAINTENANCE' ? 'selected' : '' ?>>MAINTENANCE (Perbaikan)</option>
                            <option value="UNAVAILABLE" <?= $val('readiness') === 'UNAVAILABLE' ? 'selected' : '' ?>>UNAVAILABLE</option>
                        </select>
                        <?php if (isset($errors['readiness'])): ?>
                            <div class="invalid-feedback"><?= e($errors['readiness']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="fuel_level">Indikator BBM</label>
                        <select class="form-select" id="fuel_level" name="fuel_level">
                            <option value="FULL" <?= $val('fuel_level', 'FULL') === 'FULL' ? 'selected' : '' ?>>FULL (Penuh)</option>
                            <option value="3/4" <?= $val('fuel_level') === '3/4' ? 'selected' : '' ?>>3/4</option>
                            <option value="1/2" <?= $val('fuel_level') === '1/2' ? 'selected' : '' ?>>1/2</option>
                            <option value="1/4" <?= $val('fuel_level') === '1/4' ? 'selected' : '' ?>>1/4</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="base_location">Pangkalan / Posko Utama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['base_location']) ? 'is-invalid' : '' ?>"
                               id="base_location" name="base_location" value="<?= e($val('base_location', 'Pool Ambulans RS')) ?>" required>
                        <?php if (isset($errors['base_location'])): ?>
                            <div class="invalid-feedback"><?= e($errors['base_location']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="insurance_expiry">Masa Berlaku Asuransi</label>
                        <input type="date" class="form-control <?= isset($errors['insurance_expiry']) ? 'is-invalid' : '' ?>"
                               id="insurance_expiry" name="insurance_expiry" value="<?= e($val('insurance_expiry')) ?>">
                        <?php if (isset($errors['insurance_expiry'])): ?>
                            <div class="invalid-feedback"><?= e($errors['insurance_expiry']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="chassis_number">Nomor Rangka</label>
                        <input type="text" class="form-control" id="chassis_number" name="chassis_number" value="<?= e($val('chassis_number')) ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="engine_number">Nomor Mesin</label>
                        <input type="text" class="form-control" id="engine_number" name="engine_number" value="<?= e($val('engine_number')) ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="equipment_notes">Peralatan Medis &amp; Fitur Unit</label>
                        <textarea class="form-control" id="equipment_notes" name="equipment_notes" rows="3"
                                  placeholder="Contoh: Ventilator transport, Defibrillator, Tabung O2 central, Suction pump..."><?= e($val('equipment_notes')) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="notes">Catatan Tambahan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Catatan kesiapan atau keterangan dinas..."><?= e($val('notes')) ?></textarea>
                    </div>

                    <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                        <a href="/ambulans" class="btn btn-light border">Batal</a>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-save me-1"></i>Simpan Profil Ambulans
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
