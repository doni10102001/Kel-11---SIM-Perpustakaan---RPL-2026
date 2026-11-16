<?php

namespace App\Http\Requests\Buku;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 9B — Admin mengubah data bibliografi Buku dan (opsional) menambah
 * eksemplar baru. Kode Buku diambil dari URL dan TIDAK dapat diubah; field
 * `kodeBuku`, ID, status eksemplar lama, dan relasi transaksi dari klien diabaikan.
 */
class UbahBukuRequest extends FormRequest
{
    use AturanBuku;

    public function authorize(): bool
    {
        return Gate::allows('kelola-buku');
    }

    protected function prepareForValidation(): void
    {
        $this->rapikanBuku();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->aturanBibliografi(),
            ...$this->aturanEksemplarBaru(wajib: false),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->pesanBuku();
    }
}
