<?php

namespace App\Http\Requests\Kredensial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 8C — Admin membuat akses login untuk Anggota yang sudah tersimpan.
 * Hanya `username`, `password`, `password_confirmation` yang dipakai; field
 * istimewa (role, user_id, is_active, name) dari klien diabaikan.
 */
class BuatAksesLoginRequest extends FormRequest
{
    use AturanKataSandi;

    public function authorize(): bool
    {
        return Gate::allows('kelola-kredensial');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => trim((string) $this->input('username'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Unik tanpa membedakan huruf besar/kecil (collation utf8mb4_0900_ai_ci), terhadap SEMUA akun.
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            ...$this->aturanKataSandiBaru(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.required' => 'Username wajib diisi.',
            'username.min' => 'Username harus 3–30 karakter.',
            'username.max' => 'Username harus 3–30 karakter.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.',
            'username.unique' => 'Username sudah digunakan. Gunakan username lain.',
            ...$this->pesanKataSandiBaru(),
        ];
    }
}
