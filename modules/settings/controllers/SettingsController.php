<?php

declare(strict_types=1);

namespace Modules\Settings\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\SettingsService;

/**
 * Pengaturan — akses admin saja (route ber-role:admin).
 * Kunci yang bisa diedit: whitelist (bukan sembarang skey).
 */
final class SettingsController
{
    /** Kunci yang boleh diedit admin + aturan validasinya. */
    private const EDITABLE = [
        'app.hospital_name' => 'text:100',
        'app.logo_path' => 'text:255',
        'trip.destination_radius_default_m' => 'int:10:10000',
        'fuel.receipt_photo_required' => 'bool',
    ];

    public function index(Request $request): never
    {
        $map = Setting::allMap();
        View::show('modules/settings/views/index.php', [
            'title' => 'Pengaturan',
            'rows' => array_values($map),
            'current' => $map,
        ]);
    }

    public function update(Request $request): never
    {
        $all = Setting::allMap();
        $errors = [];
        $old = [];
        $changed = [];

        foreach (self::EDITABLE as $key => $rule) {
            $value = $request->input($key, null);
            if ($value === null) {
                continue; // field tidak dikirim → lewati
            }
            $value = is_scalar($value) ? trim((string)$value) : '';
            $old[$key] = $all[$key]['value'] ?? '';
            $ok = true;
            if ($rule === 'bool') {
                $value = in_array($value, ['1', 'on', 'true'], true) ? '1' : '0';
            } elseif (str_starts_with($rule, 'int:')) {
                [, $min, $max] = explode(':', $rule);
                if (!preg_match('/^\d+$/', $value) || (int)$value < (int)$min || (int)$value > (int)$max) {
                    $ok = false;
                    $errors[$key] = "Harus angka {$min}–{$max}.";
                }
                $value = (string)(int)$value;
            } elseif (str_starts_with($rule, 'text:')) {
                $max = (int)substr($rule, 5);
                if ($value === '' || mb_strlen($value) > $max) {
                    $ok = false;
                    $errors[$key] = "Wajib, maksimal {$max} karakter.";
                }
            }
            if ($ok && $value !== (string)($all[$key]['value'] ?? '')) {
                $changed[$key] = $value;
            }
        }

        if ($errors !== []) {
            View::show('modules/settings/views/index.php', [
                'title' => 'Pengaturan',
                'rows' => array_values($all),
                'current' => $all,
                'errors' => $errors,
                'status' => 422,
            ], 'app/views/layouts/app', 422);
        }

        foreach ($changed as $key => $value) {
            Setting::put($key, $value, Session::userId());
        }
        if ($changed !== []) {
            SettingsService::flush();
            SettingsService::load();
            AuditService::log('UPDATE', 'settings', null, $old, $changed);
        }
        flash('success', $changed === []
            ? 'Tidak ada perubahan.'
            : 'Pengaturan disimpan (' . count($changed) . ' kunci).');
        Response::redirect('/pengaturan');
    }
}
