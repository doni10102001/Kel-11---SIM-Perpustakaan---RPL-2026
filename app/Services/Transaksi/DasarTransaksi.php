<?php

namespace App\Services\Transaksi;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Batch 2 — dasar service transaksi perpustakaan.
 *
 * URUTAN KUNCI (wajib di semua service transaksi; lihat
 * docs/phase10/CONCURRENCY_AND_LOCKING.md):
 *   1. members  2. bookings  3. book_copies  4. loans  5. returns
 * Baris dikunci dengan SELECT … FOR UPDATE (lockForUpdate) di dalam
 * DB::transaction, lalu SEMUA syarat dibaca ulang setelah kunci dipegang.
 *
 * - Deadlock (1213) diulang otomatis oleh DB::transaction (maks. 3 percobaan).
 * - Lock wait timeout (1205) / deadlock yang tetap gagal → galat validasi
 *   "coba lagi" (tidak ada perubahan; transaksi di-rollback).
 * - Audit ditulis di transaksi yang sama: transaksi gagal → tanpa audit.
 */
abstract class DasarTransaksi
{
    public const PESAN_SIBUK = 'Data yang sama sedang diproses transaksi lain. Tidak ada perubahan yang disimpan; coba lagi sebentar lagi.';

    /**
     * @template T
     *
     * @param  Closure(): T  $kerja
     * @return T
     */
    protected function transaksi(Closure $kerja): mixed
    {
        try {
            return DB::transaction($kerja, 3);
        } catch (QueryException $e) {
            if (in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) {
                throw ValidationException::withMessages(['umum' => self::PESAN_SIBUK]);
            }
            throw $e;
        }
    }

    /**
     * Simpan rekaman transaksi baru dengan kode bisnis turunan ID (mis. PJ-011):
     * ID AUTO_INCREMENT unik meskipun ada permintaan bersamaan, sehingga kode
     * tidak dapat bentrok antar-transaksi. Kode lama yang kebetulan sama → sufiks acak.
     */
    protected function simpanDenganKode(Model $model, string $prefiks): void
    {
        $kolom = $model::KOLOM_KODE;
        $model->forceFill([$kolom => 'TMP-'.Str::lower(Str::random(24))])->save();

        $kode = sprintf('%s-%03d', $prefiks, $model->getKey());
        try {
            $model->forceFill([$kolom => $kode])->save();
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            $model->forceFill([$kolom => $kode.'-'.Str::upper(Str::random(4))])->save();
        }
    }

    /** Audit tanpa data pribadi/rahasia. Aktor NULL = kejadian sistem (mis. penjadwal kedaluwarsa). */
    protected function catat(?User $aktor, string $aksi, string $jenis, int $id, array $metadata): void
    {
        (new AuditLog)->forceFill([
            'actor_id' => $aktor?->id,
            'action' => $aksi,
            'subject_type' => $jenis,
            'subject_id' => $id,
            'metadata' => $metadata,
        ])->save();
    }

    protected static function tolak(string $kunci, string $pesan): never
    {
        throw ValidationException::withMessages([$kunci => $pesan]);
    }
}
