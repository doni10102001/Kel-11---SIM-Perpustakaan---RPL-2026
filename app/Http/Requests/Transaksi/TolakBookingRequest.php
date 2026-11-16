<?php

namespace App\Http\Requests\Transaksi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Batch 2 — tolak booking: Alasan Penolakan wajib, maks. 200 (FR-033; kolom rejection_reason). */
class TolakBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('kelola-transaksi');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['alasanPenolakan' => trim((string) $this->input('alasanPenolakan'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['alasanPenolakan' => ['required', 'string', 'max:200']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alasanPenolakan.required' => 'Alasan Penolakan wajib diisi.',
            'alasanPenolakan.max' => 'Alasan Penolakan maksimal 200 karakter.',
        ];
    }
}
