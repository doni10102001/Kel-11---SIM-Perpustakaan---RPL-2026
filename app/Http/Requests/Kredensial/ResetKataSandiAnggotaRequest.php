<?php

namespace App\Http\Requests\Kredensial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Phase 8C — Admin mereset kata sandi akun Anggota (aksi eksplisit). */
class ResetKataSandiAnggotaRequest extends FormRequest
{
    use AturanKataSandi;

    public function authorize(): bool
    {
        return Gate::allows('kelola-kredensial');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return $this->aturanKataSandiBaru();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->pesanKataSandiBaru('Kata Sandi Baru');
    }
}
