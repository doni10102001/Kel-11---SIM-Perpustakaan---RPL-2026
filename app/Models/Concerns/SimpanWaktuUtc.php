<?php

namespace App\Models\Concerns;

/**
 * Phase 7A D-02 — timestamp kejadian SELALU disimpan dalam UTC.
 *
 * Eloquent bawaan memformat objek tanggal apa adanya (jam dinding objek),
 * sehingga Carbon berzona Asia/Jakarta akan tersimpan sebagai jam WIB di kolom
 * DATETIME. Override ini mengonversi setiap nilai ke UTC sebelum diformat.
 *
 * Tidak berlaku untuk kolom tanggal-saja (DATE) yang memakai cast TanggalSaja.
 * Binding query builder (where(..., $carbon)) TIDAK melewati method ini: kirim
 * objek UTC, mis. dari WaktuPerpustakaan::rentangHariUtc().
 */
trait SimpanWaktuUtc
{
    /**
     * @param  mixed  $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)
            ->setTimezone('UTC')
            ->format($this->getDateFormat());
    }
}
