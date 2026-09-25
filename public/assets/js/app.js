/* ============================================================
 * Fleet Logbook — app.js (Phase 1)
 * - CSRF header untuk fetch
 * - Sesi: heartbeat HANYA saat ada aktivitas user (sesuai approval):
 *   tidak ada keep-alive otomatis tanpa aktivitas.
 * - Toast, offline indicator, toggle password, loading button, draft lokal.
 * ============================================================ */
(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    /* ---------- helpers ---------- */
    window.fleet = {
        csrf: CSRF,
        async fetch(url, opts = {}) {
            const o = Object.assign({ headers: {} }, opts);
            o.headers['X-Requested-With'] = 'XMLHttpRequest';
            if (CSRF) o.headers['X-CSRF-Token'] = CSRF;
            if (o.body && typeof o.body === 'object' && !(o.body instanceof FormData)) {
                o.headers['Content-Type'] = 'application/json';
                o.body = JSON.stringify(o.body);
            }
            const res = await fetch(url, o);
            let data = null;
            try { data = await res.clone().json(); } catch (_) { /* non-json */ }
            if (data && data.success === false && res.status === 401) {
                showSessionExpired();
            }
            return { res, data };
        },
        toast(message, type) {
            toast(message, type);
        }
    };

    function toast(message, type) {
        const region = document.getElementById('toast-region');
        if (!region) return;
        const el = document.createElement('div');
        el.className = 'toast-msg' + (type ? ' ' + type : '');
        el.textContent = message;
        region.appendChild(el);
        setTimeout(() => el.remove(), 3600);
    }
    window.toast = toast;

    /* ---------- toast dari atribut data-toast ---------- */
    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-toast]');
        if (t) { e.preventDefault(); toast(t.getAttribute('data-toast')); }
    });

    /* ---------- form submit → loading state (anti dobel klik) ---------- */
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.busy === '1') { e.preventDefault(); return; }
        const btn = form.querySelector('button[type="submit"]:not([data-toggle-password])');
        if (btn && !btn.disabled) {
            form.dataset.busy = '1';
            btn.disabled = true;
            const label = btn.textContent;
            btn.dataset.orig = label;
            btn.textContent = btn.getAttribute('data-loading-text') || 'Memproses…';
            // Backup: jangan pernah biarkan tombol macet > 15 detik
            setTimeout(() => {
                if (form.dataset.busy === '1') {
                    form.dataset.busy = '0';
                    btn.disabled = false;
                    btn.textContent = label;
                }
            }, 15000);
        }
    });

    /* ---------- toggle password ---------- */
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-toggle-password]');
        if (!b) return;
        const input = document.getElementById(b.getAttribute('data-toggle-password'));
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        const icon = b.querySelector('i');
        if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    /* ---------- offline indicator ---------- */
    const banner = document.getElementById('offline-banner');
    function updateOnline() {
        if (banner) banner.hidden = navigator.onLine;
    }
    window.addEventListener('online', () => { updateOnline(); toast('Koneksi kembali.', 'success'); });
    window.addEventListener('offline', () => { updateOnline(); toast('Koneksi terputus.', 'error'); });
    updateOnline();

    /* ============================================================
     * SESI — aktivitas user → heartbeat
     * Aturan (approval Phase 0 #7):
     *  - idle timeout server-side 30 menit
     *  - warning sebelum expired
     *  - keep-alive HANYA ketika user aktif
     *  - TANPA aktivitas → sesi berakhir sesuai aturan server
     * ============================================================ */
    let lastActivity = Date.now();
    let lastBeat = 0;
    const ACTIVITY = ['pointerdown', 'keydown', 'wheel', 'touchstart'];
    ACTIVITY.forEach(ev => document.addEventListener(ev, () => { lastActivity = Date.now(); }, { passive: true }));

    const warningEl = document.getElementById('session-warning');
    const expiredEl = document.getElementById('session-expired');
    const stayBtn = document.getElementById('session-stay');

    function showSessionExpired() {
        if (expiredEl) expiredEl.hidden = false;
        if (warningEl) warningEl.hidden = true;
    }

    async function beat(force) {
        // Hanya layout yang punya UI sesi (terotentikasi) yang perlu heartbeat
        if (!warningEl && !expiredEl) return;
        const now = Date.now();
        // hanya kirim bila ada aktivitas sejak beat terakhir (atau dipaksa)
        const hasActivity = force || now - lastActivity < 60000;
        if (!hasActivity) return;
        if (now - lastBeat < 25000 && !force) return;
        lastBeat = now;
        try {
            const { res, data } = await fleet.fetch('/api/auth/heartbeat', { method: 'POST' });
            if (res.status === 401) { showSessionExpired(); return; }
            if (data && data.success && warningEl) {
                warningEl.hidden = !data.warn;
            }
        } catch (_) { /* offline — biarkan */ }
    }

    // Poll ringan HANYA memutuskan apakah perlu heartbeat; request hanya
    // dikirim jika ada aktivitas user dalam 60 detik terakhir.
    setInterval(() => beat(false), 30000);

    if (stayBtn) {
        stayBtn.addEventListener('click', () => {
            lastActivity = Date.now();
            if (warningEl) warningEl.hidden = true;
            beat(true);
        });
    }

    /* ---------- draft lokal untuk form (data-draft="key") ----------
     * Data form tidak hilang saat session expired / reload / offline. */
    document.addEventListener('input', (e) => {
        const form = e.target.closest('form[data-draft]');
        if (!form) return;
        const key = 'fleet:draft:' + form.getAttribute('data-draft');
        const data = {};
        new FormData(form).forEach((v, k) => {
            if (k !== '_csrf' && typeof v === 'string') data[k] = v;
        });
        try { localStorage.setItem(key, JSON.stringify(data)); } catch (_) { /* storage penuh */ }
    });
    document.querySelectorAll('form[data-draft]').forEach(form => {
        const key = 'fleet:draft:' + form.getAttribute('data-draft');
        try {
            const data = JSON.parse(localStorage.getItem(key) || 'null');
            if (!data) return;
            Object.entries(data).forEach(([k, v]) => {
                const el = form.querySelector('[name="' + CSS.escape(k) + '"]');
                if (el && !el.value) el.value = v;
            });
        } catch (_) { /* corrupt */ }
        form.addEventListener('submit', () => {
            try { localStorage.removeItem(key); } catch (_) {}
        });
    });
})();
