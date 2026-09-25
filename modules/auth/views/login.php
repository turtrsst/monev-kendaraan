<?php
/** @var array<string,string> $errors */
/** @var array<string,string> $old */
$errors = $errors ?? [];
$old = $old ?? [];
$general = $errors['_general'] ?? '';
?>
<div class="login-card card shadow-soft border-0">
    <div class="card-body p-4 p-sm-5">
        <div class="login-brand">
            <img id="login-logo" src="<?= e(url(ltrim((string)setting('app.logo_path', 'assets/images/logo.svg'), '/'))) ?>"
                 alt="Logo" width="64" height="64"
                 onerror="document.getElementById('login-fallback').hidden=false; this.style.display='none'">
            <div id="login-fallback" class="logo-fallback" hidden>RS</div>
        </div>
        <h1 class="h5 text-center mt-3 mb-0"><?= e(setting('app.hospital_name', 'Rumah Sakit')) ?></h1>
        <p class="text-center text-muted small mb-4"><?= e(setting('app.name', 'Fleet Logbook')) ?></p>

        <?php if ($general !== ''): ?>
            <div class="alert alert-danger py-2 small" role="alert"><?= e($general) ?></div>
        <?php endif; ?>

        <form method="post" action="/login" novalidate autocomplete="on">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input class="form-control form-control-lg<?= isset($errors['username']) ? ' is-invalid' : '' ?>"
                       id="username" name="username" type="text" inputmode="text"
                       value="<?= e($old['username'] ?? '') ?>" required minlength="3" maxlength="64"
                       autocomplete="username" autofocus>
                <?php if (isset($errors['username'])): ?>
                    <div class="invalid-feedback"><?= e($errors['username']) ?></div>
                <?php endif; ?>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <input class="form-control form-control-lg<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                           id="password" name="password" type="password"
                           autocomplete="current-password" required>
                    <button class="btn btn-outline-secondary" type="button" data-toggle-password="password"
                            aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                    <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit" data-loading-text="Memeriksa…">LOGIN</button>
        </form>

        <p class="text-center small mt-4 mb-0">
            <a href="#" data-toast="Hubungi administrator untuk reset password.">Lupa password?</a>
        </p>
    </div>
</div>
<p class="text-center text-white-50 small mt-3 mb-0 d-none d-sm-block">
    Digital Journey &amp; Vehicle Operational Logbook
</p>
