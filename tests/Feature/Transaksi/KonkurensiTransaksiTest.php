<?php

namespace Tests\Feature\Transaksi;

use App\Enums\StatusAnggota;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Booking;
use App\Models\BookReturn;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use App\Services\LayananAnggota;
use App\Services\Transaksi\DasarTransaksi;
use App\Services\Transaksi\LayananBooking;
use App\Services\Transaksi\LayananKedaluwarsaBooking;
use App\Services\Transaksi\LayananPeminjaman;
use App\Support\PemeriksaInvarianData;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Database\MySqlTestCase;

/**
 * Batch 2 — balapan transaksi dengan DUA koneksi MySQL independen (butir 51–58,
 * F-01). Data di-COMMIT sungguhan (tanpa transaksi pembungkus) dan dibersihkan
 * di tearDown; test dilewati bila database uji tidak kosong.
 *
 * Pola: koneksi kedua membuka transaksi, menjalankan operasi A lewat service
 * (savepoint, belum commit → kunci baris tetap dipegang). Koneksi utama
 * menjalankan operasi B: harus TERTAHAN pada kunci yang sama (lock wait
 * timeout 1 detik → galat "coba lagi", tanpa perubahan). Setelah A di-commit,
 * B dijalankan ulang dan harus mengambil keputusan yang benar dari data terbaru.
 */
class KonkurensiTransaksiTest extends MySqlTestCase
{
    private const KEDUA = 'mysql_uji_kedua';

    private const TABEL = ['audit_logs', 'sessions', 'returns', 'loans', 'bookings', 'book_copies', 'books', 'members', 'users'];

    private User $admin;

    private ?Connection $kedua = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Tanpa transaksi pembungkus: data harus terlihat oleh koneksi kedua.
        DB::connection(self::KONEKSI)->rollBack();

        foreach (self::TABEL as $t) {
            if (DB::table($t)->count() > 0) {
                $this->markTestSkipped("Database uji tidak kosong ({$t}); test konkurensi tidak dijalankan agar tidak menghapus data.");
            }
        }

        config(['database.connections.'.self::KEDUA => config('database.connections.'.self::KONEKSI)]);
        $this->kedua = DB::connection(self::KEDUA);
        foreach ([DB::connection(self::KONEKSI), $this->kedua] as $koneksi) {
            $koneksi->statement('SET SESSION innodb_lock_wait_timeout = 1');
        }

        $this->travelTo(CarbonImmutable::parse('2026-11-16 08:00:00', 'UTC'));
        $this->admin = $this->buatAdmin();
    }

    protected function tearDown(): void
    {
        if ($this->kedua !== null) {
            while ($this->kedua->transactionLevel() > 0) {
                $this->kedua->rollBack();
            }
            DB::purge(self::KEDUA);
            DB::setDefaultConnection(self::KONEKSI);
            $db = DB::connection(self::KONEKSI);
            if (str_ends_with((string) $db->getDatabaseName(), '_test')) {
                foreach (self::TABEL as $t) {
                    $db->table($t)->delete();
                }
            }
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------- bantuan

    /** Jalankan $aksi di koneksi kedua dalam transaksi yang DIBIARKAN terbuka (kunci dipegang). */
    private function tahanDiKoneksiKedua(Closure $aksi): mixed
    {
        $this->kedua->beginTransaction();
        DB::setDefaultConnection(self::KEDUA);
        try {
            return $aksi();
        } finally {
            DB::setDefaultConnection(self::KONEKSI);
        }
    }

    private function commitKedua(): void
    {
        $this->kedua->commit();
    }

    /** Operasi di koneksi utama harus tertahan kunci → galat "coba lagi", tanpa perubahan. */
    private function assertTertahan(Closure $aksi): void
    {
        $mulai = microtime(true);
        try {
            $aksi();
            $this->fail('Operasi seharusnya tertahan kunci baris transaksi lain.');
        } catch (ValidationException $e) {
            $this->assertSame([DasarTransaksi::PESAN_SIBUK], $e->errors()['umum']);
        }
        $this->assertGreaterThanOrEqual(0.9, microtime(true) - $mulai, 'Benar-benar menunggu kunci (lock wait), bukan gagal langsung.');
    }

    private function assertDitolak(Closure $aksi, string $pesan): void
    {
        try {
            $aksi();
            $this->fail("Seharusnya ditolak: {$pesan}");
        } catch (ValidationException $e) {
            $this->assertSame($pesan, collect($e->errors())->flatten()->first());
        }
    }

    private function anggota(string $kode, StatusAnggota $status = StatusAnggota::Aktif): Member
    {
        return $this->buatAnggota(['member_code' => $kode, 'status' => $status], $this->buatAkunAnggota(['username' => strtolower($kode)]));
    }

    private function eksemplar(string $kode, string $status = 'Tersedia'): BookCopy
    {
        return BookCopy::factory()->for(Book::factory()->create(['book_code' => 'BK-'.$kode]))->create(['copy_code' => $kode, 'status' => $status]);
    }

    private function booking(): LayananBooking
    {
        return $this->app->make(LayananBooking::class);
    }

    private function pinjaman(): LayananPeminjaman
    {
        return $this->app->make(LayananPeminjaman::class);
    }

    private function kedaluwarsa(): LayananKedaluwarsaBooking
    {
        return $this->app->make(LayananKedaluwarsaBooking::class);
    }

    private function diajukan(Member $m, string $kodeEksemplar): Booking
    {
        return $this->booking()->ajukan($m, ['kodeEksemplar' => $kodeEksemplar, 'kodeBuku' => null], $m->user);
    }

    private function tempo(): string
    {
        return '2026-11-30';
    }

    // ---------------------------------------------------------------- 51 dua peminjaman, satu eksemplar

    public function test_dua_admin_meminjamkan_eksemplar_yang_sama_hanya_satu_berhasil(): void
    {
        $this->anggota('AG-C01');
        $this->anggota('AG-C02');
        $e = $this->eksemplar('EK-C01');
        $adminKedua = $this->buatAdmin();

        $this->tahanDiKoneksiKedua(fn () => $this->pinjaman()->pinjam(['kodeAnggota' => 'AG-C01', 'kodeEksemplar' => 'EK-C01', 'tanggalJatuhTempo' => $this->tempo()], $this->admin));
        $this->assertTertahan(fn () => $this->pinjaman()->pinjam(['kodeAnggota' => 'AG-C02', 'kodeEksemplar' => 'EK-C01', 'tanggalJatuhTempo' => $this->tempo()], $adminKedua));
        $this->commitKedua();

        $this->assertDitolak(fn () => $this->pinjaman()->pinjam(['kodeAnggota' => 'AG-C02', 'kodeEksemplar' => 'EK-C01', 'tanggalJatuhTempo' => $this->tempo()], $adminKedua),
            'Buku tidak tersedia: eksemplar EK-C01 masih dalam peminjaman Aktif.');

        $this->assertSame(1, Loan::where('copy_id', $e->id)->count());
        $this->assertSame('Dipinjam', $e->fresh()->status->value);
        $this->assertSame(['peminjaman.dibuat'], AuditLog::pluck('action')->all());
        $this->assertSame([], (new PemeriksaInvarianData)->periksa());
    }

    // ---------------------------------------------------------------- 52 dua persetujuan, satu eksemplar

    public function test_dua_persetujuan_untuk_eksemplar_yang_sama_hanya_satu_reservasi(): void
    {
        $a = $this->anggota('AG-C03');
        $b = $this->anggota('AG-C04');
        $e = $this->eksemplar('EK-C02');
        $b1 = $this->diajukan($a, 'EK-C02');
        $b2 = $this->diajukan($b, 'EK-C02');

        $this->tahanDiKoneksiKedua(fn () => $this->booking()->setujui($b1->booking_code, $this->admin));
        $this->assertTertahan(fn () => $this->booking()->setujui($b2->booking_code, $this->admin));
        $this->commitKedua();

        $this->assertDitolak(fn () => $this->booking()->setujui($b2->booking_code, $this->admin), 'Eksemplar EK-C02 tidak tersedia (status Dipesan); booking hanya dapat ditolak.');
        $this->assertSame(['Disetujui', 'Diajukan'], [$b1->fresh()->status->value, $b2->fresh()->status->value]);
        $this->assertSame(1, Booking::where('copy_id', $e->id)->where('status', 'Disetujui')->count());
        $this->assertSame([], (new PemeriksaInvarianData)->periksa());
    }

    // ---------------------------------------------------------------- 53 / F-01

    public function test_f01_persetujuan_lebih_dulu_membuat_penonaktifan_ditolak(): void
    {
        $a = $this->anggota('AG-C05');
        $this->eksemplar('EK-C03');
        $b = $this->diajukan($a, 'EK-C03');
        $ubah = fn () => $this->app->make(LayananAnggota::class)->ubah('AG-C05', ['nama' => $a->name, 'kontak' => $a->contact, 'status' => 'Nonaktif'], $this->admin);

        $this->tahanDiKoneksiKedua(fn () => $this->booking()->setujui($b->booking_code, $this->admin));
        // Penonaktifan mengunci member lebih dulu → tertahan oleh persetujuan yang sedang berjalan.
        try {
            $ubah();
            $this->fail('Penonaktifan seharusnya tertahan.');
        } catch (QueryException $e) {
            $this->assertSame(1205, (int) $e->errorInfo[1], 'Lock wait pada baris members.');
        }
        $this->commitKedua();

        try {
            $ubah();
            $this->fail('F-01: penonaktifan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame([LayananAnggota::PESAN_F01], $e->errors()['status']);
        }
        $this->assertSame([StatusAnggota::Aktif, 'Disetujui'], [$a->fresh()->status, $b->fresh()->status->value]);
        $this->assertSame([], (new PemeriksaInvarianData)->periksa());
    }

    public function test_f01_penonaktifan_lebih_dulu_membuat_persetujuan_ditolak(): void
    {
        $a = $this->anggota('AG-C06');
        $e = $this->eksemplar('EK-C04');
        $b = $this->diajukan($a, 'EK-C04');

        $this->tahanDiKoneksiKedua(fn () => $this->app->make(LayananAnggota::class)->ubah('AG-C06', ['nama' => $a->name, 'kontak' => $a->contact, 'status' => 'Nonaktif'], $this->admin));
        $this->assertTertahan(fn () => $this->booking()->setujui($b->booking_code, $this->admin));
        $this->commitKedua();

        $this->assertDitolak(fn () => $this->booking()->setujui($b->booking_code, $this->admin), 'Booking tidak dapat disetujui karena status anggota AG-C06 Nonaktif.');
        $this->assertSame([StatusAnggota::Nonaktif, 'Diajukan', 'Tersedia'], [$a->fresh()->status, $b->fresh()->status->value, $e->fresh()->status->value]);
        $this->assertSame([], (new PemeriksaInvarianData)->periksa());
    }

    // ---------------------------------------------------------------- 54 realisasi vs kedaluwarsa

    public function test_kedaluwarsa_lebih_dulu_membuat_realisasi_ditolak(): void
    {
        $a = $this->anggota('AG-C07');
        $e = $this->eksemplar('EK-C05');
        $b = $this->diajukan($a, 'EK-C05');
        $this->booking()->setujui($b->booking_code, $this->admin);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00', 'UTC'));

        $this->tahanDiKoneksiKedua(fn () => $this->kedaluwarsa()->kedaluwarsakan($b->id));
        $this->assertTertahan(fn () => $this->booking()->realisasikan($b->booking_code, $this->tempo(), $this->admin));
        $this->commitKedua();

        $this->assertDitolak(fn () => $this->booking()->realisasikan($b->booking_code, $this->tempo(), $this->admin),
            "Booking {$b->booking_code} berstatus Kedaluwarsa; hanya booking Disetujui yang dapat direalisasikan.");
        $this->assertSame([0, 'Kedaluwarsa', 'Tersedia'], [Loan::count(), $b->fresh()->status->value, $e->fresh()->status->value]);
        $this->assertSame(1, AuditLog::where('action', 'booking.kedaluwarsa')->count());
    }

    public function test_realisasi_lebih_dulu_membuat_kedaluwarsa_menjadi_no_op(): void
    {
        $a = $this->anggota('AG-C08');
        $e = $this->eksemplar('EK-C06');
        $b = $this->diajukan($a, 'EK-C06');
        $this->booking()->setujui($b->booking_code, $this->admin);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 07:59:59', 'UTC'));

        $this->tahanDiKoneksiKedua(fn () => $this->booking()->realisasikan($b->booking_code, $this->tempo(), $this->admin));
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00', 'UTC'));
        $this->assertTertahan(fn () => $this->kedaluwarsa()->kedaluwarsakan($b->id));
        $this->commitKedua();

        $this->assertFalse($this->kedaluwarsa()->kedaluwarsakan($b->id), 'Sudah Direalisasikan → dilewati.');
        $this->assertSame(['Direalisasikan', 'Dipinjam', 1], [$b->fresh()->status->value, $e->fresh()->status->value, Loan::count()]);
        $this->assertSame(0, AuditLog::where('action', 'booking.kedaluwarsa')->count());
        $this->assertSame([], (new PemeriksaInvarianData)->periksa());
    }

    // ---------------------------------------------------------------- 55 pembatalan vs kedaluwarsa

    public function test_kedaluwarsa_dan_pembatalan_serentak_hanya_satu_transisi(): void
    {
        $a = $this->anggota('AG-C09');
        $e = $this->eksemplar('EK-C07');
        $b = $this->diajukan($a, 'EK-C07');
        $this->booking()->setujui($b->booking_code, $this->admin);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00', 'UTC'));

        $this->tahanDiKoneksiKedua(fn () => $this->kedaluwarsa()->kedaluwarsakan($b->id));
        $this->assertTertahan(fn () => $this->booking()->batalkan($a, $b->booking_code, $a->user));
        $this->commitKedua();

        $this->assertDitolak(fn () => $this->booking()->batalkan($a, $b->booking_code, $a->user), "Booking {$b->booking_code} berstatus Kedaluwarsa dan tidak dapat dibatalkan.");
        $this->assertSame(['Kedaluwarsa', 'Tersedia'], [$b->fresh()->status->value, $e->fresh()->status->value]);
        $this->assertSame([1, 0], [AuditLog::where('action', 'booking.kedaluwarsa')->count(), AuditLog::where('action', 'booking.dibatalkan')->count()]);
    }

    // ---------------------------------------------------------------- 56 pengembalian ganda

    public function test_pengembalian_serentak_hanya_satu_tercatat(): void
    {
        $a = $this->anggota('AG-C10');
        $e = $this->eksemplar('EK-C08', 'Dipinjam');
        $p = $this->buatPeminjaman($a, $e, $this->admin, 'Aktif', ['loan_code' => 'PJ-C08']);
        $a->forceFill(['status' => StatusAnggota::Nonaktif])->save(); // pengembalian anggota Nonaktif tetap sah

        $this->tahanDiKoneksiKedua(fn () => $this->pinjaman()->kembalikan('PJ-C08', $this->admin));
        $this->assertTertahan(fn () => $this->pinjaman()->kembalikan('PJ-C08', $this->buatAdmin()));
        $this->commitKedua();

        $this->assertDitolak(fn () => $this->pinjaman()->kembalikan('PJ-C08', $this->admin), 'Pengembalian ditolak: transaksi PJ-C08 sudah dikembalikan.');
        $this->assertSame([1, 'Selesai', 'Tersedia'], [BookReturn::where('loan_id', $p->id)->count(), $p->fresh()->status->value, $e->fresh()->status->value]);
        $this->assertSame(StatusAnggota::Nonaktif, $a->fresh()->status);
    }

    // ---------------------------------------------------------------- 57 kedaluwarsa vs reservasi baru

    public function test_kedaluwarsa_dan_persetujuan_baru_untuk_eksemplar_yang_sama(): void
    {
        $a = $this->anggota('AG-C11');
        $b = $this->anggota('AG-C12');
        $e = $this->eksemplar('EK-C09');
        $lama = $this->diajukan($a, 'EK-C09');
        $this->booking()->setujui($lama->booking_code, $this->admin);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00', 'UTC'));
        $baru = $this->booking()->ajukan($b, ['kodeEksemplar' => 'EK-C09', 'kodeBuku' => null], $b->user); // rekonsiliasi melepas reservasi lama
        $this->assertSame('Kedaluwarsa', $lama->fresh()->status->value);

        // Skenario balapan murni: reservasi lama (dibuat ulang) sedang dikedaluwarsakan saat persetujuan baru masuk.
        $lama2 = $this->diajukan($a, 'EK-C09');
        $this->travelTo(CarbonImmutable::parse('2026-11-18 09:00:00', 'UTC'));
        $this->booking()->setujui($lama2->booking_code, $this->admin);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'UTC'));

        $this->tahanDiKoneksiKedua(fn () => $this->kedaluwarsa()->kedaluwarsakan($lama2->id));
        $this->assertTertahan(fn () => $this->booking()->setujui($baru->booking_code, $this->admin));
        $this->commitKedua();

        $this->booking()->setujui($baru->booking_code, $this->admin);
        $this->assertSame(['Kedaluwarsa', 'Disetujui', 'Dipesan'], [$lama2->fresh()->status->value, $baru->fresh()->status->value, $e->fresh()->status->value]);
        $this->assertSame(1, Booking::where('copy_id', $e->id)->where('status', 'Disetujui')->count());
        $this->assertSame([], (new PemeriksaInvarianData)->periksa());
    }

    // ---------------------------------------------------------------- H, I

    public function test_dua_anggota_mengajukan_eksemplar_yang_sama_keduanya_diajukan(): void
    {
        $a = $this->anggota('AG-C13');
        $b = $this->anggota('AG-C14');
        $e = $this->eksemplar('EK-C10');

        $this->tahanDiKoneksiKedua(fn () => $this->diajukan($a, 'EK-C10'));
        $this->assertTertahan(fn () => $this->diajukan($b, 'EK-C10'));
        $this->commitKedua();
        $this->diajukan($b, 'EK-C10');

        $this->assertSame(2, Booking::where('copy_id', $e->id)->where('status', 'Diajukan')->count(), 'Perilaku teruji: Diajukan tidak mereservasi (TBD-06).');
        $this->assertSame('Tersedia', $e->fresh()->status->value);
    }

    public function test_penjadwal_memproses_booking_yang_sama_dua_kali_tanpa_audit_ganda(): void
    {
        $a = $this->anggota('AG-C15');
        $b = $this->diajukan($a, $this->eksemplar('EK-C11')->copy_code);
        $this->booking()->setujui($b->booking_code, $this->admin);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00', 'UTC'));

        $this->tahanDiKoneksiKedua(fn () => $this->kedaluwarsa()->prosesJatuhTempo());
        $hasil = $this->kedaluwarsa()->prosesJatuhTempo(); // proses kedua (tumpang tindih) → item tertahan, dicatat gagal, tanpa perubahan
        $this->assertSame(['diproses' => 0, 'dilewati' => 0, 'gagal' => 1], $hasil);
        $this->commitKedua();

        $this->assertSame(['diproses' => 0, 'dilewati' => 0, 'gagal' => 0], $this->kedaluwarsa()->prosesJatuhTempo());
        $this->assertSame(1, AuditLog::where('action', 'booking.kedaluwarsa')->count());
        $this->assertSame('Kedaluwarsa', $b->fresh()->status->value);
    }
}
