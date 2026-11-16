<?php

namespace App\Http\Requests\Transaksi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Batch 2 — peminjaman langsung. Admin pemroses dari sesi; ID/status/staff dari klien diabaikan. */
class CatatPeminjamanRequest extends FormRequest
{
    use AturanJatuhTempo;

    public function authorize(): bool
    {
        return Gate::allows('kelola-transaksi');
    }

    protected function prepareForValidation(): void
    {
        foreach (['kodeAnggota', 'kodeBuku', 'kodeEksemplar', 'tanggalJatuhTempo'] as $k) {
            if (is_string($this->input($k))) {
                $this->merge([$k => trim($this->input($k))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kodeAnggota' => ['required', 'string', 'max:30'],
            'kodeBuku' => ['nullable', 'string', 'max:30'],
            'kodeEksemplar' => ['required', 'string', 'max:30'],
            ...$this->aturanJatuhTempo(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kodeAnggota.required' => 'Pilih anggota.',
            'kodeEksemplar.required' => 'Pilih Kode Eksemplar.',
            ...$this->pesanJatuhTempo(),
        ];
    }

    /** @return array{kodeAnggota: string, kodeBuku: ?string, kodeEksemplar: string, tanggalJatuhTempo: string} */
    public function isian(): array
    {
        return $this->safe()->only(['kodeAnggota', 'kodeBuku', 'kodeEksemplar', 'tanggalJatuhTempo']) + ['kodeBuku' => null]; // kunci kiri menang
    }
}
