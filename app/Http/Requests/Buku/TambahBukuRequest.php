<?php

namespace App\Http\Requests\Buku;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 9B — Admin menambah Buku beserta eksemplar awal (minimal satu).
 * Dipakai hanya: kodeBuku, judul, penulis, penerbit, tahunTerbit, kategori,
 * eksemplar[].kodeEksemplar. ID, status eksemplar, dan aktor TIDAK diambil dari
 * klien (aktor = Admin dari sesi; status eksemplar baru = Tersedia oleh server).
 */
class TambahBukuRequest extends FormRequest
{
    use AturanBuku;

    public function authorize(): bool
    {
        return Gate::allows('kelola-buku');
    }

    protected function prepareForValidation(): void
    {
        $this->rapikanBuku();
        $this->merge(['kodeBuku' => trim((string) $this->input('kodeBuku'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Pola sama dengan parameter rute (NavigasiDemo::POLA_KODE) agar halaman Ubah selalu dapat dibuka.
            'kodeBuku' => ['required', 'string', 'max:20', 'regex:'.self::POLA_KODE, 'unique:books,book_code'],
            ...$this->aturanBibliografi(),
            ...$this->aturanEksemplarBaru(wajib: true),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kodeBuku.required' => 'Kode Buku wajib diisi.',
            'kodeBuku.max' => 'Kode Buku maksimal 20 karakter.',
            'kodeBuku.regex' => 'Kode Buku hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung (tanpa spasi).',
            'kodeBuku.unique' => 'Kode Buku sudah digunakan buku lain.',
            ...$this->pesanBuku(),
        ];
    }

    /** @return array{kodeBuku: string, judul: string, penulis: string, penerbit: string, tahunTerbit: string, kategori: string} */
    public function dataBuku(): array
    {
        return ['kodeBuku' => $this->validated('kodeBuku')] + $this->bibliografi();
    }
}
