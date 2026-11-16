<?php

namespace App\Http\Requests\Transaksi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Batch 2 — Anggota mengajukan booking. Pemilik SELALU dari sesi; member_id/kodeAnggota dari klien diabaikan. */
class AjukanBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('booking-anggota');
    }

    protected function prepareForValidation(): void
    {
        foreach (['kodeBuku', 'kodeEksemplar'] as $k) {
            if (is_string($this->input($k))) {
                $this->merge([$k => trim($this->input($k))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kodeBuku' => ['nullable', 'string', 'max:30'],
            'kodeEksemplar' => ['required', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['kodeEksemplar.required' => 'Pilih Kode Eksemplar.'];
    }

    /** @return array{kodeEksemplar: string, kodeBuku: ?string} */
    public function isian(): array
    {
        return $this->safe()->only(['kodeBuku', 'kodeEksemplar']) + ['kodeBuku' => null]; // kunci kiri menang
    }
}
