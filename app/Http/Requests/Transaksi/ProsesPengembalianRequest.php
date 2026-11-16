<?php

namespace App\Http\Requests\Transaksi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Batch 2 — pengembalian. Hanya kode peminjaman; Admin penerima dari sesi. */
class ProsesPengembalianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('kelola-transaksi');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['idPeminjaman' => ['required', 'string', 'max:30']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['idPeminjaman.required' => 'Pilih transaksi peminjaman.'];
    }
}
