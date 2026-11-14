<?php

namespace App\Http\Requests\Kredensial;

/**
 * Aturan kata sandi baru (L.3: 6–64 karakter) dan pesan Bahasa Indonesia yang
 * sama dengan validasi frontend (resources/js/utils/validasiMaster.js).
 * Pesan galat tidak pernah memuat nilai kata sandi.
 */
trait AturanKataSandi
{
    /** @return array<string, list<string>> */
    protected function aturanKataSandiBaru(): array
    {
        return [
            'password' => ['required', 'string', 'min:6', 'max:64', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    protected function pesanKataSandiBaru(string $label = 'Kata Sandi'): array
    {
        return [
            'password.required' => "{$label} wajib diisi.",
            'password.min' => "{$label} minimal 6 karakter.",
            'password.max' => "{$label} maksimal 64 karakter.",
            'password.confirmed' => "Konfirmasi {$label} tidak sama dengan {$label}.",
            'password_confirmation.required' => "Konfirmasi {$label} wajib diisi.",
        ];
    }
}
