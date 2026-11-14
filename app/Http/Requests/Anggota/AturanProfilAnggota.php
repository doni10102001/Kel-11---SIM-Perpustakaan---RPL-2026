<?php

namespace App\Http\Requests\Anggota;

/**
 * Aturan profil Anggota (nama, kontak, status) — sama dengan validator frontend
 * (resources/js/utils/validasiMaster.js) dan panjang kolom MySQL.
 */
trait AturanProfilAnggota
{
    protected function rapikanProfil(): void
    {
        $this->merge([
            'nama' => trim((string) $this->input('nama')),
            'kontak' => trim((string) $this->input('kontak')),
        ]);
    }

    /** @return array<string, list<string>> */
    protected function aturanProfil(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'kontak' => ['required', 'string', 'max:50'],
            'status' => ['required', 'in:Aktif,Nonaktif'],
        ];
    }

    /** @return array<string, string> */
    protected function pesanProfil(): array
    {
        return [
            'nama.required' => 'Nama wajib diisi.',
            'nama.max' => 'Nama maksimal 100 karakter.',
            'kontak.required' => 'Kontak wajib diisi.',
            'kontak.max' => 'Kontak maksimal 50 karakter.',
            'status.required' => 'Pilih Status Anggota.',
            'status.in' => 'Pilih Status Anggota.',
        ];
    }
}
