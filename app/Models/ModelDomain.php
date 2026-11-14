<?php

namespace App\Models;

use App\Models\Concerns\SimpanWaktuUtc;
use Illuminate\Database\Eloquent\Model;

/**
 * Dasar model domain Phase 7A.
 *
 * Kolom waktu memakai DATETIME(6); format ini menjaga presisi mikrodetik.
 * Nilai waktu selalu disimpan dalam UTC (D-02, trait SimpanWaktuUtc) dan
 * diserialisasi sebagai ISO-8601 UTC ("...Z"); frontend menampilkan WIB.
 * Model sengaja tipis: tidak ada logika transaksi di sini — transaksi
 * multi-tabel (booking, peminjaman, pengembalian) dibuat sebagai service
 * dengan DB::transaction + row lock pada Phase 8+.
 */
abstract class ModelDomain extends Model
{
    use SimpanWaktuUtc;

    protected $dateFormat = 'Y-m-d H:i:s.u';
}
