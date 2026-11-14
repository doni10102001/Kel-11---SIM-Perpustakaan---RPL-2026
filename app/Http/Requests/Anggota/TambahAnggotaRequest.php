<?php

namespace App\Http\Requests\Anggota;

use App\Http\Requests\Kredensial\AturanKataSandi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 9A — Admin menambah Anggota (opsional sekaligus akses login).
 * Dipakai hanya: kodeAnggota, nama, kontak, status, buatAkses, username,
 * password, password_confirmation. Field istimewa (role, user_id, is_active,
 * must_change_password) dari klien diabaikan.
 */
class TambahAnggotaRequest extends FormRequest
{
    use AturanKataSandi, AturanProfilAnggota;

    public function authorize(): bool
    {
        return Gate::allows('kelola-anggota');
    }

    protected function prepareForValidation(): void
    {
        $this->rapikanProfil();
        $this->merge([
            'kodeAnggota' => trim((string) $this->input('kodeAnggota')),
            'buatAkses' => $this->boolean('buatAkses'),
            'username' => trim((string) $this->input('username')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $aturan = [
            // Pola sama dengan parameter rute (NavigasiDemo::POLA_KODE) agar halaman Ubah dapat dibuka.
            'kodeAnggota' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:members,member_code'],
            ...$this->aturanProfil(),
            'buatAkses' => ['boolean'],
        ];

        if ($this->boolean('buatAkses')) {
            $aturan['username'] = ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'];
            $aturan += $this->aturanKataSandiBaru();
        }

        return $aturan;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kodeAnggota.required' => 'Kode Anggota wajib diisi.',
            'kodeAnggota.max' => 'Kode Anggota maksimal 20 karakter.',
            'kodeAnggota.regex' => 'Kode Anggota hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung (tanpa spasi).',
            'kodeAnggota.unique' => 'Kode Anggota sudah digunakan anggota lain.',
            ...$this->pesanProfil(),
            'username.required' => 'Username wajib diisi.',
            'username.min' => 'Username harus 3–30 karakter.',
            'username.max' => 'Username harus 3–30 karakter.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.',
            'username.unique' => 'Username sudah digunakan. Gunakan username lain.',
            ...$this->pesanKataSandiBaru(),
        ];
    }

    /** @return array{kodeAnggota: string, nama: string, kontak: string, status: string} */
    public function profil(): array
    {
        return $this->safe()->only(['kodeAnggota', 'nama', 'kontak', 'status']);
    }

    /** @return array{username: string, password: string}|null */
    public function akses(): ?array
    {
        return $this->boolean('buatAkses') ? $this->safe()->only(['username', 'password']) : null;
    }
}
