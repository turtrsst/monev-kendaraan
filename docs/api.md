# API & Endpoint — Phase 1

Dokumen ini mencerminkan **hanya endpoint yang benar-benar ada pada Phase 1**
(sumber: `app/config/routes.php` + controller terkait + `tests/http_phase1.sh`).
**Belum ada** endpoint vehicles, drivers, assignments, trips, GPS, arrival,
evidence/upload, expenses, OCR, maupun reporting — semua itu Phase 2+ dan
sengaja tidak didokumentasikan di sini.

Konvensi:

- Semua path relatif terhadap base URL aplikasi (mis. `http://localhost`).
- Autentikasi memakai **session cookie** (nama `SESSION_NAME`, default `fleet_sid`;
  `HttpOnly`, `SameSite=Lax`), bukan token Bearer.
- Form HTML memakai field `_csrf`; klien JSON memakai header `X-CSRF-Token`.
- Respons JSON selalu ber-envelope:
  - sukses: `{"success": true, "message": "...", "data": ...}`
  - gagal: `{"success": false, "message": "...", "errors": {...}}` +
    kadang field `code` (lihat endpoint masing-masing).

## Ringkasan endpoint

| Method | Path | Auth | Role | CSRF | Rate limit/menit |
|---|---|---|---|---|---|
| GET | `/login` | No (guest) | — | — | 60 |
| POST | `/login` | No (guest) | — | Required | 10 |
| GET | `/` | Yes | any | — | — |
| GET | `/beranda` | Yes | any | — | — |
| POST | `/logout` | Yes | any | Required | — |
| GET | `/ganti-password` | Yes | any | — | — |
| POST | `/ganti-password` | Yes | any | Required | 20 |
| GET | `/pengaturan` | Yes | admin | — | — |
| POST | `/pengaturan` | Yes | admin | Required | 30 |
| GET | `/api/auth/me` | Yes | any | — | — |
| POST | `/api/auth/heartbeat` | Yes | any | Required | — |
| POST | `/api/auth/logout` | Yes | any | Required | — |

Urutan middleware per route: `auth → force_password_change → role → csrf → ratelimit`
(seperti didefinisikan di `app/config/routes.php`). `GET /logout` tidak ada → **404**.
Method yang salah pada path yang ada → **404** (belum ada semantik 405).

---

## Halaman (HTML)

### GET /login

```text
Authentication: No — middleware guest (sudah login → 302 /beranda)
CSRF:           Tidak ada (GET, tidak mengubah state)
Rate limit:     60/menit (per IP + path)
Request:        —
Success:        200 — form login (berisi field _csrf)
Failure:        302 /beranda bila sesi sudah valid
Example:        GET /login
```

### POST /login

```text
Authentication: No — middleware guest
CSRF:           Required — field form "_csrf" ATAU header "X-CSRF-Token"
                (JSON tanpa token → 403 {"code":"csrf_invalid"})
Rate limit:     10/menit (per IP + path)
Request (form): application/x-www-form-urlencoded
                _csrf, username, password
Request (JSON): {"username": "...", "password": "..."}

Validasi (server-side, AuthValidator):
  username: wajib, 3–64 karakter, [A-Za-z0-9._-]
  password: wajib, maksimal 1024 karakter

Success:        302 Location: /ganti-password   (force_password_change = 1)
                302 Location: /beranda          (normal; tujuan tersimpan dari
                                                  halaman sebelumnya juga bisa)
Failure (respons dire-render ulang form login HTML dengan status berikut):
                422 "Data login tidak valid." + errors per-field
                401 "Username atau password salah." (user tidak ada / password
                    salah — pesan sama, tidak membocorkan keberadaan akun)
                403 "Akun tidak aktif." + errors.username
                429 throttle: "Terlalu banyak percobaan login. Coba lagi dalam
                    {n} menit." (login_attempts ≥ 5 per username+IP / 15 menit)
                429 "Akun terkunci sementara." (locked_until masih berlaku)
                403 CSRF (HTML: pesan umum; JSON: {"code":"csrf_invalid"})
                429 rate limiter file ({"code":"rate_limited"} untuk JSON)

Catatan Phase 1: kegagalan autentikasi selalu me-render ulang view login (HTML)
dengan status di atas — controller belum memiliki cabang envelope JSON untuk
login error. Klien JSON tetap menerima status HTTP yang benar.

Example (form):
  POST /login  _csrf=…&username=admin&password=…
  → 302 Location: /ganti-password
Example (JSON, gagal):
  POST /login  X-CSRF-Token: …
  {"username":"admin","password":"salah"}
  → 401 + view HTML berisi "Username atau password salah."
```

### GET / · GET /beranda

```text
Authentication: Required — middleware auth
Authorization:  Semua role yang login
CSRF:           Tidak ada (GET)
Force-gate:     force_password_change = 1 → 403
                (pesan "Anda wajib mengubah password terlebih dahulu…";
                 JSON: {"code":"force_password_change"})
Request:        —
Success:        200 — dashboard (layout app)
Failure:        302 /login tanpa sesi (tujuan halaman disimpan untuk
                redirect setelah login; path relatif saja)
                403 bila belum ganti password wajib
```

### POST /logout

```text
Authentication: Required
CSRF:           Required (field "_csrf" / header "X-CSRF-Token")
Request:        — (form HTML biasa; tidak ada JSON endpoint logout di halaman ini)
Success:        302 Location: /login
                Sesi dihancurkan (cookie dibersihkan) + audit LOGOUT.
Failure:        403 CSRF; 401/302 bila sesi sudah habis (middleware auth)
Catatan:        GET /logout TIDAK ada → 404 (logout sengaja POST-only).
```

### GET /ganti-password

```text
Authentication: Required
Authorization:  Semua role; route ini DIIZINKAN melewati force_password_change gate
CSRF:           Tidak ada (GET)
Success:        200 — form ganti password
Failure:        302 /login bila sesi habis
```

### POST /ganti-password

```text
Authentication: Required
CSRF:           Required ("_csrf" / "X-CSRF-Token")
Rate limit:     20/menit (per IP + path)
Request (form): _csrf, current_password, new_password, confirm_password
Request (JSON): {"current_password":"…","new_password":"…","confirm_password":"…"}

Validasi (server-side, AuthValidator):
  current: wajib + password_verify terhadap hash saat ini
  new:     wajib, 8–1024 karakter, harus mengandung HURUF dan ANGKA,
           tidak boleh sama dengan password saat ini
  confirm: harus sama dengan new

Success:        302 Location: /beranda
                - hash baru disimpan (password_hash), force_password_change = 0
                - CSRF token dirotasi
                - flash "Password berhasil diubah."
                - audit PASSWORD_CHANGE
Failure:        422 — view form dire-render (errors: current/new/confirm + _general)
                403 CSRF · 429 rate limiter
                302 /login bila sesi tidak ada
Example:        POST /ganti-password  302 → /beranda
```

### GET /pengaturan

```text
Authentication: Required
Authorization:  role:admin saja (RoleMiddleware, server-side)
Force-gate:     aktif (403 bila force_password_change = 1)
CSRF:           Tidak ada (GET)
Success:        200 — form pengaturan (hanya menampilkan/menerima kunci whitelist)
Failure:        302 /login tanpa sesi
                403 force_password_change
                403 "Anda tidak memiliki akses ke halaman ini."
                   (JSON: {"code":"forbidden"})
```

### POST /pengaturan

```text
Authentication: Required
Authorization:  role:admin saja
CSRF:           Required ("_csrf" / "X-CSRF-Token")
Rate limit:     30/menit (per IP + path)

Request (form) — HANYA kunci whitelist yang diproses (sisanya diabaikan):
  app.hospital_name             text, wajib, maks 100 karakter
  app.logo_path                 text, maks 255 karakter (wajib terisi bila dikirim)
  trip.destination_radius_default_m   integer 10–10000
  fuel.receipt_photo_required   boolean (0/1)

Success:        302 Location: /pengaturan
                - perubahan disimpan per-kunci (hanya yang berubah),
                  SettingsService di-flush, audit "UPDATE" pada entity "settings"
                  (old_data = nilai lama, new_data = nilai baru)
                - flash sukses ("Tidak ada perubahan." bila tidak ada yang berubah)
Failure:        422 — form dire-render (errors per-kunci; tidak ada yang disimpan)
                403 role/force/CSRF · 429 rate limiter
Catatan:        request JSON tanpa CSRF → 403 {"code":"csrf_invalid"}; keberhasilan
                update selalu merespons redirect HTML (tanpa cabang JSON — Phase 1).
Example:        POST /pengaturan
                _csrf=…&app.hospital_name=RSUP+dr.+Soeradji+Tirtonegoro+Klaten
                → 302 /pengaturan, flash "Pengaturan disimpan (1 kunci)."
```

---

## API JSON (session)

### GET /api/auth/me

```text
Authentication: Required (middleware auth — /api/* selalu JSON)
Authorization:  Semua role
CSRF:           Tidak ada (GET)
Rate limit:     — (tanpa middleware ratelimit eksplisit)

Success (200):
{
    "success": true,
    "message": "OK",
    "data": {
        "user": {
            "id": 1,
            "username": "admin",
            "name": "Administrator",
            "role": "admin",
            "role_name": "Administrator",
            "force_password_change": true
        },
        "idle_remaining": 1742
    }
}

Error:
    401 {"success":false,"message":"Belum masuk.","errors":[],
         "code":"unauthenticated"}          — tanpa sesi
    401 {"code":"session_expired"}          — sesi terdeteksi timeout saat request
         (kedua respons memakai envelope gagal standar di atas)

Catatan: route ini termasuk whitelist force_password_change gate → selalu 200
saat sesi valid, termasuk selama wajib ganti password (field
force_password_change di data memberi tahu kondisi tersebut).
```

### POST /api/auth/heartbeat

```text
Authentication: Required (middleware auth)
CSRF:           Required — header "X-CSRF-Token" (atau field "_csrf")
Rate limit:     — (tanpa middleware ratelimit eksplisit; dilindungi CSRF)
Request:        — (tanpa body; POST saja)

Perilaku:
  Setiap POST valid MEMPERBARUI aktivitas sesi terakhir (keep-alive).
  Sesuai desain Phase 1, client (app.js) hanya mengirim heartbeat ketika ada
  aktivitas user nyata (pointer/keyboard/scroll/touch dalam 60 detik terakhir,
  minimal 25 detik antar-beat) — tidak ada polling keep-alive otomatis.

Success (200):
{
    "success": true,
    "message": "OK",
    "data": {
        "idle_remaining": 1680,
        "warn": false,
        "warning_seconds": 120
    }
}
  warn = true ketika idle_remaining ≤ 120 detik (peringatan sesi UI).

Error:
    401 code "unauthenticated" / "session_expired"  — sesi habis
    403 {"code":"csrf_invalid"}                     — token CSRF hilang/salah
```

### POST /api/auth/logout

```text
Authentication: Required (middleware auth)
CSRF:           Required
Rate limit:     —
Request:        —

Success (200):
{
    "success": true,
    "message": "Sesi berakhir.",
    "data": []
}
  Sesi dihancurkan bila masih ada; audit LOGOUT tercatat.

Error:
    401 code "unauthenticated"  — tanpa sesi (dihitung sudah keluar)
    403 {"code":"csrf_invalid"}
```

---

## Security behavior (yang benar-benar diterapkan)

- **Authentication** — session cookie native PHP (`fleet_sid`, HttpOnly, SameSite=Lax,
  `use_strict_mode`); ID sesi di-regenerate saat login (anti-fixation); CSRF token
  dirotasi saat login dan saat ganti password. Tanpa sesi: JSON → **401**
  (`code` `unauthenticated`/`session_expired`), HTML → **302 /login** (tujuan disimpan).
- **Session lifetime** — idle **30 menit** & absolute **12 jam**, divalidasi
  **server-side** pada setiap request **tanpa** memperpanjang aktivitas; peringatan
  **120 detik** sebelum habis lewat `POST /api/auth/heartbeat` (`warn`); keep-alive
  hanya via heartbeat yang dikirim client saat ada aktivitas nyata.
- **Authorization (role)** — server-side: route `/pengaturan` memakai `role:admin`
  (daftar slug didefinisikan di route, bukan input user). Role tersimpan di session
  (di-set saat login). Non-admin → **403** (HTML pesan umum; JSON `code: forbidden`).
- **Force password change** — akun dengan flag wajib ganti password hanya boleh
  mengakses whitelist: `/ganti-password`, `/logout`, `/api/auth/me`,
  `/api/auth/heartbeat`, `/api/auth/logout` (selain aset). Selain itu → **403**
  (`code: force_password_change` untuk JSON).
- **CSRF** — semua route state-changing (POST) memakai middleware `csrf`
  (double-submit: field `_csrf` atau header `X-CSRF-Token`, verifikasi `hash_equals`).
  Gagal → **403** (JSON: `code: csrf_invalid`; HTML: pesan umum). Tidak ada
  state-change lewat GET.
- **Throttling** — dua lapis:
  1. *Login:* `login_attempts` per **username + IP**, default **5 gagal / 15 menit**
     (configurable via settings) → **429** + audit `LOGIN_THROTTLED`; akun juga
     dikunci (`locked_until`) setelah kegagalan beruntun → **429**.
  2. *Rate limiter file* per menit (key: method + IP + path) pada route yang
     diberi `ratelimit:N` (lihat tabel) → **429**, header `Retry-After: 60`,
     JSON: `code: rate_limited`. Bila penyimpanan cache gagal, limiter
     **fail-open** (request diloloskan — pilihan availability, Phase 1).
- **JSON response behavior** — semua path `/api/*` selalu JSON. Untuk path non-API,
  JSON dipilih bila `Accept: application/json` ATAU `X-Requested-With: XMLHttpRequest`
  (dipakai middleware CSRF/auth/role). Respons view controller sendiri tetap HTML
  (lihat catatan per-endpoint di atas).
- **Validation** — selalu server-side (`AuthValidator` + whitelist rule di
  `SettingsController::EDITABLE`); gagal → **422** dengan `errors` per-field
  (HTML: re-render form). Input sensitif tidak pernah dipercaya dari client.
- **Error handling** — pesan error generik ke user (tidak membocorkan detail DB /
  keberadaan akun). Detail exception hanya dirender bila `APP_DEBUG=true`
  (default **false**; aktifkan eksplisit hanya untuk development lokal).
- **IDOR protection** — **tidak berlaku di Phase 1**: belum ada endpoint dengan
  parameter object-id (trip/dokumen/evidence, dsb.). Jaminan kepemilikan per-object
  baru bisa didokumentasikan bersama endpointnya di Phase 2+.
- **Audit** — login (sukses/gagal/throttled/inactive/locked), logout, ganti password,
  dan perubahan settings tercatat di `audit_logs` (server-side, IP + user-agent).
