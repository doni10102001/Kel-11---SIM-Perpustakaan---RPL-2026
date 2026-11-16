<?php

namespace App\Http\Requests\Transaksi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Batch 2 — realisasi booking: hanya Tanggal Jatuh Tempo; sisanya dari booking terkunci. */
class RealisasiBookingRequest extends FormRequest
{
    use AturanJatuhTempo;

    public function authorize(): bool
    {
        return Gate::allows('kelola-transaksi');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->aturanJatuhTempo();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->pesanJatuhTempo();
    }
}
