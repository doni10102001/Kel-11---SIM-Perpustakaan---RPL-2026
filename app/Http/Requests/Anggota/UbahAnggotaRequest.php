<?php

namespace App\Http\Requests\Anggota;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 9A — Admin mengubah profil & status Anggota. Kode Anggota (dari URL)
 * tidak dapat diubah; kata sandi dan akun tidak disentuh (reset = aksi
 * terpisah Phase 8C).
 */
class UbahAnggotaRequest extends FormRequest
{
    use AturanProfilAnggota;

    public function authorize(): bool
    {
        return Gate::allows('kelola-anggota');
    }

    protected function prepareForValidation(): void
    {
        $this->rapikanProfil();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return $this->aturanProfil();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->pesanProfil();
    }

    /** @return array{nama: string, kontak: string, status: string} */
    public function profil(): array
    {
        return $this->safe()->only(['nama', 'kontak', 'status']);
    }
}
