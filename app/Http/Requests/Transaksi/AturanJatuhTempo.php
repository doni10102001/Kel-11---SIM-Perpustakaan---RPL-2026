<?php

namespace App\Http\Requests\Transaksi;

use App\Support\WaktuPerpustakaan;

/**
 * Tanggal Jatuh Tempo (loans.due_date DATE) — diisi manual Admin (FR-024; tanpa
 * durasi bawaan, TBD-05). Aturan sama dengan validator frontend
 * (validasiTransaksi.galatJatuhTempo): wajib, tanggal kalender valid Y-m-d,
 * dan SETELAH Tanggal Pinjam (hari ini, kalender WIB). Tanpa konversi zona.
 */
trait AturanJatuhTempo
{
    /** @return array<string, list<mixed>> */
    protected function aturanJatuhTempo(): array
    {
        return ['tanggalJatuhTempo' => ['required', 'date_format:Y-m-d', 'after:'.WaktuPerpustakaan::hariIni()]];
    }

    /** @return array<string, string> */
    protected function pesanJatuhTempo(): array
    {
        return [
            'tanggalJatuhTempo.required' => 'Tanggal Jatuh Tempo wajib diisi.',
            'tanggalJatuhTempo.date_format' => 'Tanggal Jatuh Tempo tidak valid.',
            'tanggalJatuhTempo.after' => 'Tanggal Jatuh Tempo harus setelah Tanggal Pinjam.',
        ];
    }
}
