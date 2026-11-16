<?php

namespace Tests\Feature\Database;

use App\Enums\StatusBooking;
use App\Enums\StatusEksemplar;
use App\Enums\StatusPeminjaman;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookReturn;
use App\Models\Loan;
use App\Models\Member;
use App\Support\WaktuPerpustakaan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Phase 7A — kode bisnis unik, relasi Eloquent, FK tanpa cascade, unique
 * bersyarat (generated column), satu pengembalian per peminjaman, dan DATE
 * untuk due_date. Semua pada MySQL 8.4 sungguhan.
 */
class IntegritasDataTest extends MySqlTestCase
{
    public function test_kode_anggota_buku_eksemplar_unik_tanpa_membedakan_huruf(): void
    {
        $this->buatAnggota(['member_code' => 'UJI-AG-01']);
        $this->assertDitolakDb(1062, fn () => $this->buatAnggota(['member_code' => 'UJI-AG-01']));
        $this->assertDitolakDb(1062, fn () => $this->buatAnggota(['member_code' => 'uji-ag-01']));

        $buku = Book::factory()->create(['book_code' => 'UJI-BK-01']);
        $this->assertDitolakDb(1062, fn () => Book::factory()->create(['book_code' => 'UJI-BK-01']));

        BookCopy::factory()->for($buku)->create(['copy_code' => 'UJI-EK-01']);
        $this->assertDitolakDb(1062, fn () => BookCopy::factory()->for($buku)->create(['copy_code' => 'UJI-EK-01']));
    }

    public function test_kode_booking_peminjaman_pengembalian_unik(): void
    {
        $admin = $this->buatAdmin();
        $anggota = $this->buatAnggota();
        $e1 = $this->buatEksemplar();
        $e2 = $this->buatEksemplar();

        $this->buatBooking($anggota, $e1, lain: ['booking_code' => 'UJI-BP-01']);
        $this->assertDitolakDb(1062, fn () => $this->buatBooking($anggota, $e2, lain: ['booking_code' => 'UJI-BP-01']));

        $p1 = $this->buatPeminjaman($anggota, $e1, $admin, 'Selesai', ['loan_code' => 'UJI-PJ-01']);
        $p2 = $this->buatPeminjaman($anggota, $e2, $admin, 'Selesai');
        $this->assertDitolakDb(1062, fn () => $this->buatPeminjaman($anggota, $e2, $admin, 'Selesai', ['loan_code' => 'UJI-PJ-01']));

        $this->buatPengembalian($p1, $admin, 'UJI-KB-01');
        $this->assertDitolakDb(1062, fn () => $this->buatPengembalian($p2, $admin, 'UJI-KB-01'));
    }

    public function test_pencarian_kode_bisnis_dan_route_key(): void
    {
        $anggota = $this->buatAnggota(['member_code' => 'UJI-AG-77']);

        $this->assertTrue(Member::cariKode('uji-ag-77')->is($anggota));
        $this->assertSame('member_code', $anggota->getRouteKeyName());
        $this->assertNull(Member::cariKode('TIDAK-ADA'));
    }

    public function test_relasi_eloquent_lengkap(): void
    {
        $admin = $this->buatAdmin();
        $anggota = $this->buatAnggota();
        $buku = Book::factory()->create();
        $eksemplar = $this->buatEksemplar($buku, 'Dipinjam');
        $booking = $this->buatBooking($anggota, $eksemplar, 'Direalisasikan', ['staff_id' => $admin->id, 'decided_at' => now()]);
        $pinjam = $this->buatPeminjaman($anggota, $eksemplar, $admin, 'Aktif', ['booking_id' => $booking->id]);
        $log = new AuditLog;
        $log->forceFill(['actor_id' => $admin->id, 'action' => 'loan.created', 'subject_type' => 'loan', 'subject_id' => $pinjam->id, 'metadata' => ['kode' => $pinjam->loan_code]])->save();

        $this->assertTrue($buku->copies()->first()->is($eksemplar));
        $this->assertTrue($eksemplar->book->is($buku));
        $this->assertSame(1, $eksemplar->bookings()->count());
        $this->assertSame(1, $eksemplar->loans()->count());
        $this->assertTrue($anggota->bookings()->first()->is($booking));
        $this->assertTrue($anggota->loans()->first()->is($pinjam));
        $this->assertTrue($booking->copy->is($eksemplar));
        $this->assertTrue($booking->member->is($anggota));
        $this->assertTrue($booking->staff->is($admin));
        $this->assertTrue($pinjam->staff->is($admin));
        $this->assertTrue($pinjam->copy->is($eksemplar));
        $this->assertSame(1, $admin->loansProcessed()->count());
        $this->assertSame(1, $admin->bookingsDecided()->count());
        $this->assertTrue($log->actor->is($admin));
        $this->assertSame(['kode' => $pinjam->loan_code], $admin->auditLogs()->first()->metadata);

        $this->assertSame(StatusEksemplar::Dipinjam, $eksemplar->fresh()->status);
        $this->assertSame(StatusBooking::Direalisasikan, $booking->fresh()->status);
        $this->assertSame(StatusPeminjaman::Aktif, $pinjam->fresh()->status);
    }

    public function test_relasi_booking_dan_peminjaman_satu_sumber(): void
    {
        $admin = $this->buatAdmin();
        $anggota = $this->buatAnggota();
        $eksemplar = $this->buatEksemplar(status: 'Dipinjam');
        $booking = $this->buatBooking($anggota, $eksemplar, 'Direalisasikan');
        $pinjam = $this->buatPeminjaman($anggota, $eksemplar, $admin, 'Aktif', ['booking_id' => $booking->id]);

        $this->assertTrue($booking->fresh()->loan->is($pinjam));
        $this->assertTrue($pinjam->fresh()->booking->is($booking));

        $langsung = $this->buatPeminjaman($anggota, $this->buatEksemplar(status: 'Dipinjam'), $admin);
        $this->assertNull($langsung->fresh()->booking, 'Peminjaman langsung tidak punya booking.');

        $this->assertDitolakDb(1062, fn () => $this->buatPeminjaman($anggota, $this->buatEksemplar(), $admin, 'Selesai', ['booking_id' => $booking->id]),
            'Satu booking maksimal direalisasikan menjadi satu peminjaman.');
    }

    public function test_foreign_key_menolak_rujukan_yang_tidak_ada(): void
    {
        $admin = $this->buatAdmin();
        $eksemplar = $this->buatEksemplar();
        $anggotaFiktif = new Member;
        $anggotaFiktif->id = 999999999;

        $this->assertDitolakDb(1452, fn () => $this->buatPeminjaman($anggotaFiktif, $eksemplar, $admin));
        $this->assertDitolakDb(1452, fn () => DB::table('book_copies')->insert(['book_id' => 999999999, 'copy_code' => 'UJI-EK-FIKTIF', 'status' => 'Tersedia']));
    }

    public function test_menghapus_induk_yang_punya_riwayat_ditolak_tanpa_cascade(): void
    {
        $admin = $this->buatAdmin();
        $anggota = $this->buatAnggota();
        $buku = Book::factory()->create();
        $eksemplar = $this->buatEksemplar($buku);
        $booking = $this->buatBooking($anggota, $eksemplar, 'Direalisasikan');
        $pinjam = $this->buatPeminjaman($anggota, $eksemplar, $admin, 'Selesai', ['booking_id' => $booking->id]);
        $this->buatPengembalian($pinjam, $admin);

        $this->assertDitolakDb(1451, fn () => $anggota->delete());
        $this->assertDitolakDb(1451, fn () => $buku->delete());
        $this->assertDitolakDb(1451, fn () => $eksemplar->delete());
        $this->assertDitolakDb(1451, fn () => $booking->delete());
        $this->assertDitolakDb(1451, fn () => $pinjam->delete());

        $this->assertNotNull($anggota->fresh());
        $this->assertSame(1, BookReturn::where('loan_id', $pinjam->id)->count());
    }

    public function test_satu_pengembalian_per_peminjaman(): void
    {
        $admin = $this->buatAdmin();
        $pinjam = $this->buatPeminjaman($this->buatAnggota(), $this->buatEksemplar(), $admin, 'Selesai');

        $this->buatPengembalian($pinjam, $admin);
        $this->assertDitolakDb(1062, fn () => $this->buatPengembalian($pinjam, $admin));
        $this->assertTrue($pinjam->fresh()->bookReturn->loan->is($pinjam));
    }

    public function test_maksimal_satu_peminjaman_aktif_per_eksemplar(): void
    {
        $admin = $this->buatAdmin();
        $anggota = $this->buatAnggota();
        $eksemplar = $this->buatEksemplar(status: 'Dipinjam');

        $this->buatPeminjaman($anggota, $eksemplar, $admin, 'Selesai');
        $this->buatPeminjaman($anggota, $eksemplar, $admin, 'Selesai');
        $aktif = $this->buatPeminjaman($anggota, $eksemplar, $admin, 'Aktif');

        $this->assertDitolakDb(1062, fn () => $this->buatPeminjaman($this->buatAnggota(), $eksemplar, $admin, 'Aktif'));

        $aktif->forceFill(['status' => 'Selesai'])->save();
        $this->buatPeminjaman($anggota, $eksemplar, $admin, 'Aktif');
        $this->assertSame(1, Loan::where('copy_id', $eksemplar->id)->where('status', 'Aktif')->count());
    }

    public function test_maksimal_satu_booking_disetujui_per_eksemplar(): void
    {
        $eksemplar = $this->buatEksemplar(status: 'Dipesan');
        $a = $this->buatAnggota();
        $b = $this->buatAnggota();

        foreach (['Diajukan', 'Diajukan', 'Ditolak', 'Dibatalkan', 'Direalisasikan'] as $status) {
            $this->buatBooking($b, $eksemplar, $status);
        }
        $disetujui = $this->buatBooking($a, $eksemplar, 'Disetujui');

        $this->assertDitolakDb(1062, fn () => $this->buatBooking($b, $eksemplar, 'Disetujui'));
        $this->assertDitolakDb(1062, fn () => DB::table('bookings')->where('member_id', $b->id)->where('status', 'Diajukan')->limit(1)->update(['status' => 'Disetujui', 'decided_at' => now(), 'expires_at' => now()->addHours(48)]),
            'Mengubah booking lain menjadi Disetujui juga harus ditolak.');

        $disetujui->forceFill(['status' => 'Dibatalkan'])->save();
        $this->buatBooking($b, $eksemplar, 'Disetujui');

        $this->buatBooking($a, $this->buatEksemplar(status: 'Dipesan'), 'Disetujui');
    }

    public function test_nilai_status_di_luar_kosakata_ditolak_check(): void
    {
        $admin = $this->buatAdmin();
        $anggota = $this->buatAnggota();
        $eksemplar = $this->buatEksemplar();
        $booking = $this->buatBooking($anggota, $eksemplar);
        $pinjam = $this->buatPeminjaman($anggota, $eksemplar, $admin, 'Selesai');

        $this->assertDitolakDb(3819, fn () => DB::table('members')->where('id', $anggota->id)->update(['status' => 'Diblokir']));
        $this->assertDitolakDb(3819, fn () => DB::table('book_copies')->where('id', $eksemplar->id)->update(['status' => 'Hilang']));
        $this->assertDitolakDb(3819, fn () => DB::table('bookings')->where('id', $booking->id)->update(['status' => 'Kedaluwarsa']),
            'Status kedaluwarsa booking belum disetujui.');
        $this->assertDitolakDb(3819, fn () => DB::table('loans')->where('id', $pinjam->id)->update(['status' => 'Terlambat']));
    }

    public function test_status_dan_role_peka_huruf_besar_kecil_dan_aksen(): void
    {
        $admin = $this->buatAdmin();
        $anggota = $this->buatAnggota();
        $eksemplar = $this->buatEksemplar();
        $booking = $this->buatBooking($anggota, $eksemplar);
        $pinjam = $this->buatPeminjaman($anggota, $eksemplar, $admin, 'Selesai');

        // Tanpa collation biner, CHECK akan menerima nilai ini lalu enum PHP gagal membacanya.
        $this->assertDitolakDb(3819, fn () => DB::table('members')->where('id', $anggota->id)->update(['status' => 'aktif']));
        $this->assertDitolakDb(3819, fn () => DB::table('members')->where('id', $anggota->id)->update(['status' => 'Áktif']));
        $this->assertDitolakDb(3819, fn () => DB::table('book_copies')->where('id', $eksemplar->id)->update(['status' => 'TERSEDIA']));
        $this->assertDitolakDb(3819, fn () => DB::table('bookings')->where('id', $booking->id)->update(['status' => 'disetujui']));
        $this->assertDitolakDb(3819, fn () => DB::table('loans')->where('id', $pinjam->id)->update(['status' => 'aktif']));
        $this->assertDitolakDb(3819, fn () => DB::table('users')->where('id', $admin->id)->update(['role' => 'Admin']));
    }

    public function test_due_date_tersimpan_sebagai_date_tanpa_bergeser_hari(): void
    {
        $admin = $this->buatAdmin();
        $anggota = $this->buatAnggota();

        $p1 = $this->buatPeminjaman($anggota, $this->buatEksemplar(), $admin, 'Selesai', ['due_date' => '2026-11-16']);
        $this->assertSame('2026-11-16', DB::table('loans')->where('id', $p1->id)->value('due_date'));
        $this->assertSame('2026-11-16', $p1->fresh()->due_date->format('Y-m-d'));
        $this->assertSame('2026-11-16', $p1->fresh()->toArray()['due_date']);

        // 23:30 WIB tanggal 16 = 16:30 UTC; tanggal kalender WIB = 16.
        $wib = CarbonImmutable::parse('2026-11-16 23:30:00', 'Asia/Jakarta');
        $p2 = $this->buatPeminjaman($anggota, $this->buatEksemplar(), $admin, 'Selesai', ['due_date' => $wib]);
        $this->assertSame('2026-11-16', DB::table('loans')->where('id', $p2->id)->value('due_date'));

        // 00:30 WIB tanggal 17 = 17:30 UTC tanggal 16; tanggal kalender WIB = 17.
        $dekatTengahMalam = CarbonImmutable::parse('2026-11-17 00:30:00', 'Asia/Jakarta');
        $p3 = $this->buatPeminjaman($anggota, $this->buatEksemplar(), $admin, 'Selesai', ['due_date' => $dekatTengahMalam]);
        $this->assertSame('2026-11-17', DB::table('loans')->where('id', $p3->id)->value('due_date'));

        // Instant UTC yang sama (17:30Z tanggal 16) juga tanggal 17 WIB — tidak memakai tanggal UTC.
        $p4 = $this->buatPeminjaman($anggota, $this->buatEksemplar(), $admin, 'Selesai', ['due_date' => $dekatTengahMalam->utc()]);
        $this->assertSame('2026-11-17', DB::table('loans')->where('id', $p4->id)->value('due_date'));

        // Baca → tulis ulang tidak menggeser tanggal.
        $p1->due_date = $p1->fresh()->due_date;
        $p1->save();
        $this->assertSame('2026-11-16', DB::table('loans')->where('id', $p1->id)->value('due_date'));

        $this->expectException(\InvalidArgumentException::class);
        $this->buatPeminjaman($anggota, $this->buatEksemplar(), $admin, 'Selesai', ['due_date' => '2026-02-30']);
    }

    public function test_timestamp_kejadian_disimpan_utc_dan_diserialisasi_iso_z(): void
    {
        $admin = $this->buatAdmin();
        $anggota = $this->buatAnggota();

        // 2 Nov 2026 05:15 WIB = 1 Nov 2026 22:15 UTC.
        $wib = CarbonImmutable::parse('2026-11-02 05:15:00.123456', 'Asia/Jakarta');
        $pinjam = $this->buatPeminjaman($anggota, $this->buatEksemplar(), $admin, 'Selesai', ['loaned_at' => $wib]);

        $this->assertSame('2026-11-01 22:15:00.123456', DB::table('loans')->where('id', $pinjam->id)->value('loaned_at'));
        $this->assertTrue($pinjam->fresh()->loaned_at->eq($wib), 'Instant yang sama setelah dibaca ulang.');
        $this->assertSame('UTC', $pinjam->fresh()->loaned_at->getTimezone()->getName());
        $this->assertSame('2026-11-01T22:15:00.123456Z', $pinjam->fresh()->toArray()['loaned_at']);
        $this->assertSame('2026-11-02', WaktuPerpustakaan::tanggalLokal($pinjam->fresh()->loaned_at));

        // Filter laporan periode 2 Nov (WIB) harus menemukan kejadian ini; DATE() di SQL tidak.
        [$mulai, $sebelum] = WaktuPerpustakaan::rentangHariUtc('2026-11-02', '2026-11-02');
        $this->assertSame(1, DB::table('loans')->where('id', $pinjam->id)
            ->where('loaned_at', '>=', $mulai)->where('loaned_at', '<', $sebelum)->count());
        $this->assertSame(0, DB::table('loans')->where('id', $pinjam->id)
            ->whereRaw('DATE(loaned_at) = ?', ['2026-11-02'])->count());
    }

    public function test_due_date_wajib_diisi(): void
    {
        $admin = $this->buatAdmin();
        $anggota = $this->buatAnggota();

        $this->assertDitolakDb(1048, fn () => DB::table('loans')->insert([
            'loan_code' => 'UJI-PJ-NULL', 'member_id' => $anggota->id, 'copy_id' => $this->buatEksemplar()->id,
            'staff_id' => $admin->id, 'loaned_at' => now(), 'due_date' => null, 'status' => 'Selesai',
        ]));
    }

    public function test_audit_log_tidak_dapat_diubah_atau_dihapus_lewat_eloquent(): void
    {
        $log = new AuditLog;
        $log->forceFill(['actor_id' => null, 'action' => 'system.test', 'subject_type' => 'system', 'subject_id' => 1])->save();

        $this->assertNotNull($log->fresh()->created_at);

        $this->expectException(LogicException::class);
        $log->forceFill(['action' => 'diubah'])->save();
    }

    private function buatPengembalian(Loan $pinjam, $admin, ?string $kode = null): BookReturn
    {
        $kembali = new BookReturn;
        $kembali->forceFill([
            'return_code' => $kode ?? 'UJI-KB-'.uniqid(),
            'loan_id' => $pinjam->id,
            'staff_id' => $admin->id,
            'returned_at' => now(),
        ])->save();

        return $kembali;
    }
}
