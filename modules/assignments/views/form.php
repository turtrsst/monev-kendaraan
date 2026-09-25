<?php
/** @var array<string,mixed>|null $assignment */
/** @var array<int,array<string,mixed>> $vehicles */
/** @var array<int,array<string,mixed>> $drivers */
/** @var array<string,string> $errors */
$isEdit = !empty($assignment['id']);
$action = $isEdit ? '/penugasan/edit/' . (int)$assignment['id'] : '/penugasan/tambah';
$val = static fn (string $k, string $d = ''): string => (string)($assignment[$k] ?? $d);
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 mb-0"><?= $isEdit ? 'Edit Penugasan: ' . e($val('assignment_number')) : 'Buat Penugasan Baru' ?></h1>
                <div class="text-muted small">Surat tugas perjalanan dinas resmi dan kesiapan operasional</div>
            </div>
            <a href="/penugasan" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>

        <div class="card border-0 shadow-soft">
            <div class="card-body p-4">
                <form method="post" action="<?= $action ?>" class="row g-3">
                    <?= csrf_field() ?>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="assignment_date">Tanggal Penugasan <span class="text-danger">*</span></label>
                        <input type="date" class="form-control <?= isset($errors['assignment_date']) ? 'is-invalid' : '' ?>"
                               id="assignment_date" name="assignment_date" value="<?= e($val('assignment_date', date('Y-m-d'))) ?>" required>
                        <?php if (isset($errors['assignment_date'])): ?>
                            <div class="invalid-feedback"><?= e($errors['assignment_date']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="status">Status Penugasan</label>
                        <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" id="status" name="status">
                            <option value="ASSIGNED" <?= $val('status', 'ASSIGNED') === 'ASSIGNED' ? 'selected' : '' ?>>ASSIGNED (Ditugaskan)</option>
                            <option value="DRAFT" <?= $val('status') === 'DRAFT' ? 'selected' : '' ?>>DRAFT (Konsep)</option>
                            <?php if ($isEdit): ?>
                                <option value="CANCELLED" <?= $val('status') === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED (Batal)</option>
                            <?php endif; ?>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <div class="invalid-feedback"><?= e($errors['status']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="vehicle_id">Kendaraan Dinas / Ambulans <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['vehicle_id']) ? 'is-invalid' : '' ?>" id="vehicle_id" name="vehicle_id" required>
                            <option value="">-- Pilih Kendaraan Aktif --</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= (int)$val('vehicle_id') === (int)$v['id'] ? 'selected' : '' ?>>
                                    <?= e($v['plate_number']) ?> — <?= e($v['vehicle_name']) ?> (<?= e($v['vehicle_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['vehicle_id'])): ?>
                            <div class="invalid-feedback"><?= e($errors['vehicle_id']) ?></div>
                        <?php endif; ?>
                        <div class="form-text small">Satu kendaraan tidak dapat memiliki 2 penugasan aktif pada tanggal yang sama.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="driver_id">Driver Resmi <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['driver_id']) ? 'is-invalid' : '' ?>" id="driver_id" name="driver_id" required>
                            <option value="">-- Pilih Driver Aktif --</option>
                            <?php foreach ($drivers as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= (int)$val('driver_id') === (int)$d['id'] ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?> (<?= e($d['license_type']) ?> - SIM s/d <?= e($d['license_expiry']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['driver_id'])): ?>
                            <div class="invalid-feedback"><?= e($errors['driver_id']) ?></div>
                        <?php endif; ?>
                        <div class="form-text small">Driver berstatus aktif dan SIM belum kadaluarsa.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="destination">Tujuan Perjalanan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['destination']) ? 'is-invalid' : '' ?>"
                               id="destination" name="destination" value="<?= e($val('destination')) ?>"
                               placeholder="Contoh: RSUP Dr. Kariadi Semarang / Kantor Dinkes Jateng" required>
                        <?php if (isset($errors['destination'])): ?>
                            <div class="invalid-feedback"><?= e($errors['destination']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="purpose">Maksud &amp; Keperluan Penugasan <span class="text-danger">*</span></label>
                        <textarea class="form-control <?= isset($errors['purpose']) ? 'is-invalid' : '' ?>"
                                  id="purpose" name="purpose" rows="2" placeholder="Uraikan keperluan kedinasan atau rujukan pasien..." required><?= e($val('purpose')) ?></textarea>
                        <?php if (isset($errors['purpose'])): ?>
                            <div class="invalid-feedback"><?= e($errors['purpose']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="passenger_count">Jumlah Penumpang</label>
                        <input type="number" class="form-control" id="passenger_count" name="passenger_count"
                               value="<?= e($val('passenger_count', '1')) ?>" min="0" max="100">
                    </div>

                    <div class="col-12 col-md-8">
                        <label class="form-label" for="passenger_notes">Daftar / Nama Penumpang</label>
                        <input type="text" class="form-control" id="passenger_notes" name="passenger_notes"
                               value="<?= e($val('passenger_notes')) ?>" placeholder="Nama-nama staf / pasien / tim medis">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="st_reference">Nomor Surat Tugas (ST)</label>
                        <input type="text" class="form-control" id="st_reference" name="st_reference"
                               value="<?= e($val('st_reference')) ?>" placeholder="Contoh: ST/089/TU.02/RSST/2026">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="sppd_reference">Nomor SPPD</label>
                        <input type="text" class="form-control" id="sppd_reference" name="sppd_reference"
                               value="<?= e($val('sppd_reference')) ?>" placeholder="Contoh: SPPD/089/2026">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="notes">Catatan Tambahan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Instruksi rute khusus, jam kumpul, dll..."><?= e($val('notes')) ?></textarea>
                    </div>

                    <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                        <a href="/penugasan" class="btn btn-light border">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Terbitkan Penugasan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
