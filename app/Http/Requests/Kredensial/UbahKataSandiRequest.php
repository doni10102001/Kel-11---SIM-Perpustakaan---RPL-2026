<?php

namespace App\Http\Requests\Kredensial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 8C — Admin mengganti kata sandinya sendiri. Kata sandi saat ini
 * diverifikasi terhadap hash di MySQL (aturan `current_password:web`).
 */
class UbahKataSandiRequest extends FormRequest
{
    use AturanKataSandi;

    public function authorize(): bool
    {
        return Gate::allows('kelola-kredensial');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            ...$this->aturanKataSandiBaru(),
            'password' => ['required', 'string', 'min:6', 'max:64', 'confirmed', 'different:current_password'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Kata Sandi Saat Ini wajib diisi.',
            'current_password.current_password' => 'Kata Sandi Saat Ini tidak sesuai.',
            'password.different' => 'Kata Sandi Baru harus berbeda dari Kata Sandi Saat Ini.',
            ...$this->pesanKataSandiBaru('Kata Sandi Baru'),
        ];
    }
}
