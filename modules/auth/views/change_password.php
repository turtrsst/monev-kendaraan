<?php
$errors = $errors ?? [];
$general = $errors['_general'] ?? '';
$user = auth_user();
$force = !empty($user['force_password_change']);
?>
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
        <div class="card shadow-soft border-0">
            <div class="card-body p-4">
                <h1 class="h5 mb-1">Ganti Password</h1>
                <?php if ($force): ?>
                    <div class="alert alert-warning py-2 small" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Anda wajib mengubah password default sebelum melanjutkan.
                    </div>
                <?php endif; ?>

                <?php if ($general !== ''): ?>
                    <div class="alert alert-danger py-2 small" role="alert"><?= e($general) ?></div>
                <?php endif; ?>

                <form method="post" action="/ganti-password" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Password saat ini</label>
                        <input class="form-control<?= isset($errors['current']) ? ' is-invalid' : '' ?>"
                               id="current_password" name="current_password" type="password"
                               autocomplete="current-password" required>
                        <?php if (isset($errors['current'])): ?>
                            <div class="invalid-feedback"><?= e($errors['current']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_password">Password baru</label>
                        <input class="form-control<?= isset($errors['new']) ? ' is-invalid' : '' ?>"
                               id="new_password" name="new_password" type="password"
                               autocomplete="new-password" minlength="8" required>
                        <div class="form-text">Minimal 8 karakter, mengandung huruf dan angka.</div>
                        <?php if (isset($errors['new'])): ?>
                            <div class="invalid-feedback"><?= e($errors['new']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="confirm_password">Ulangi password baru</label>
                        <input class="form-control<?= isset($errors['confirm']) ? ' is-invalid' : '' ?>"
                               id="confirm_password" name="confirm_password" type="password"
                               autocomplete="new-password" minlength="8" required>
                        <?php if (isset($errors['confirm'])): ?>
                            <div class="invalid-feedback"><?= e($errors['confirm']) ?></div>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary w-100" type="submit" data-loading-text="Menyimpan…">
                        Simpan Password Baru
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
