<?php

namespace Tests\Feature\Dashboard;

use App\Enums\StatusAnggota;
use App\Models\Booking;
use App\Models\BookReturn;
use App\Models\Loan;
use App\Services\Transaksi\LayananBooking;
use App\Services\Transaksi\LayananKedaluwarsaBooking;
use App\Services\Transaksi\LayananPeminjaman;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Transaksi\TransaksiTestCase;

/**
 * Batch 3 — dashboard (agregat SQL), laporan server, dan konsistensi angka
 * terhadap transaksi nyata. Semua di sim_perpustakaan_test (di-rollback).
 */
class DashboardLaporanTest extends TransaksiTestCase
{
    private function ringkasanAdmin(): array
    {
        return $this->sebagaiAdmin()->props('/admin/dashboard')['data']['ringkasan'];
    }

    // ---------------------------------------------------------------- Admin: agregat

    public function test_ringkasan_admin_sama_dengan_isi_mysql_untuk_semua_status(): void
    {
        $a = $this->anggotaDenganAkun('AG-D01');
        $this->anggotaDenganAkun('AG-D02', StatusAnggota::Nonaktif);
        $e = [];
        foreach (['Tersedia', 'Tersedia', 'Dipesan', 'Dipinjam', 'Dipinjam'] as $i => $st) {
            $e[] = $this->eksemplarBaru("EK-D0{$i}", $st);
        }
        $this->buatPeminjaman($a, $e[3], $this->admin);
        $this->buatPeminjaman($a, $e[4], $this->admin);
        $this->buatPeminjaman($a, $e[0], $this->admin, 'Selesai');
        $this->buatBooking($a, $e[2], 'Disetujui', ['staff_id' => $this->admin->id]);
        foreach (['Diajukan', 'Diajukan', 'Ditolak', 'Dibatalkan'] as $st) {
            $this->buatBooking($a, $e[1], $st);
        }

        $r = $this->ringkasanAdmin();
        $this->assertSame(['total' => 2, 'Aktif' => 1, 'Nonaktif' => 1], $r['anggota']);
        $this->assertSame(['total' => 5, 'kategori' => 1], $r['buku']);
        $this->assertSame(['total' => 5, 'Tersedia' => 2, 'Dipesan' => 1, 'Dipinjam' => 2], $r['eksemplar']);
        $this->assertSame(['Aktif' => 2, 'Selesai' => 1], $r['peminjaman']);
        $this->assertSame(['Diajukan' => 2, 'Disetujui' => 1, 'Ditolak' => 1, 'Dibatalkan' => 1, 'Direalisasikan' => 0, 'Kedaluwarsa' => 0], $r['booking']);

        // Dicocokkan langsung dengan COUNT MySQL (bukan angka tetap).
        $this->assertSame(DB::table('book_copies')->where('status', 'Dipinjam')->count(), $r['eksemplar']['Dipinjam']);
        $this->assertSame(DB::table('loans')->where('status', 'Aktif')->count(), $r['peminjaman']['Aktif']);
    }

    public function test_dashboard_admin_memakai_agregat_dan_jumlah_query_tetap(): void
    {
        $hitung = function (): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->sebagaiAdmin()->get('/admin/dashboard')->assertOk();
            $q = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();

            return $q;
        };

        // Data awal sudah memuat peminjaman Aktif & booking berjalan agar eager loading ikut terhitung sejak awal.
        $a = $this->anggotaDenganAkun('AG-D03');
        $this->buatPeminjaman($a, $this->eksemplarBaru('EK-D10', 'Dipinjam'), $this->admin);
        $this->buatBooking($a, $this->eksemplarBaru('EK-D11'), 'Diajukan');
        $awal = $hitung();
        for ($i = 0; $i < 25; $i++) {
            $e = $this->eksemplarBaru(sprintf('EK-D2%02d', $i), $i % 2 ? 'Dipinjam' : 'Tersedia');
            if ($i % 2) {
                $this->buatPeminjaman($a, $e, $this->admin);
            }
        }
        $akhir = $hitung();

        $this->assertCount(count($awal), $akhir, 'Jumlah query dashboard tidak bertambah seiring data.');
        // Tabel tidak pernah dimuat penuh hanya untuk menghitung: query TANPA WHERE harus agregat.
        foreach ($akhir as $sql) {
            if (preg_match('/from `(members|books|book_copies|loans|bookings|returns)`/', $sql) && ! str_contains($sql, ' where ')) {
                $this->assertMatchesRegularExpression('/count\(|sum\(/i', $sql, "Query tanpa filter harus agregat: {$sql}");
            }
        }
        $data = $this->sebagaiAdmin()->props('/admin/dashboard')['data'];
        $this->assertSame(['ringkasan', 'peminjamanAktif', 'bookingBerjalan'], array_keys($data), 'Tanpa koleksi tabel penuh.');
        $this->assertCount(10, $data['peminjamanAktif'], 'Daftar ringkas dibatasi 10 baris.');
        $this->assertSame(13, $data['ringkasan']['peminjaman']['Aktif'], 'Total tetap dari agregat.');
    }

    public function test_daftar_ringkas_dashboard_benar_dan_tanpa_kontak(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 03:00:00', 'UTC'));
        $a = $this->anggotaDenganAkun('AG-D04');
        $e = $this->eksemplarBaru('EK-D30', 'Dipinjam');
        $this->buatPeminjaman($a, $e, $this->admin, 'Aktif', ['loan_code' => 'PJ-D30', 'due_date' => '2026-11-20', 'loaned_at' => now()]);
        $this->buatBooking($a, $this->eksemplarBaru('EK-D31'), 'Diajukan', ['booking_code' => 'BP-D31']);

        $data = $this->sebagaiAdmin()->props('/admin/dashboard')['data'];
        $this->assertSame([
            'idPeminjaman' => 'PJ-D30', 'anggota' => ['kodeAnggota' => 'AG-D04', 'nama' => $a->name], 'buku' => ['judul' => 'Buku EK-D30'],
            'kodeEksemplar' => 'EK-D30', 'tanggalPinjam' => '2026-11-16', 'tanggalJatuhTempo' => '2026-11-20', 'status' => 'Aktif',
        ], $data['peminjamanAktif'][0]);
        $this->assertSame(['BP-D31', 'Diajukan', null], [$data['bookingBerjalan'][0]['idBooking'], $data['bookingBerjalan'][0]['status'], $data['bookingBerjalan'][0]['batasPengambilan']]);
        $this->assertStringNotContainsString($a->contact, json_encode($data));
        $this->assertSame(['peminjaman' => 1, 'pengembalian' => 0, 'booking' => 1], $data['ringkasan']['hariIni']);
    }

    // ---------------------------------------------------------------- Konsistensi angka (skenario A–H)

    public function test_angka_dashboard_mengikuti_setiap_transaksi(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 02:00:00', 'UTC'));
        $a = $this->anggotaDenganAkun('AG-D05');
        $b = $this->anggotaDenganAkun('AG-D06');
        foreach (['EK-D40', 'EK-D41', 'EK-D42', 'EK-D43'] as $k) {
            $this->eksemplarBaru($k);
        }
        $pinjam = $this->app->make(LayananPeminjaman::class);
        $booking = $this->app->make(LayananBooking::class);
        $r0 = $this->ringkasanAdmin();

        // A. Peminjaman langsung.
        $p = $pinjam->pinjam(['kodeAnggota' => 'AG-D05', 'kodeEksemplar' => 'EK-D40', 'kodeBuku' => null, 'tanggalJatuhTempo' => '2026-11-23'], $this->admin);
        $r1 = $this->ringkasanAdmin();
        $this->assertSame([$r0['peminjaman']['Aktif'] + 1, $r0['eksemplar']['Tersedia'] - 1], [$r1['peminjaman']['Aktif'], $r1['eksemplar']['Tersedia']]);

        // B. Pengembalian.
        $pinjam->kembalikan($p->loan_code, $this->admin);
        $r2 = $this->ringkasanAdmin();
        $this->assertSame([$r1['peminjaman']['Aktif'] - 1, $r1['peminjaman']['Selesai'] + 1, $r1['eksemplar']['Tersedia'] + 1], [$r2['peminjaman']['Aktif'], $r2['peminjaman']['Selesai'], $r2['eksemplar']['Tersedia']]);
        $this->assertSame(1, $r2['hariIni']['pengembalian']);

        // C. Pengajuan booking: Diajukan +1, ketersediaan tetap.
        $x = $booking->ajukan($a, ['kodeEksemplar' => 'EK-D41', 'kodeBuku' => null], $a->user);
        $y = $booking->ajukan($b, ['kodeEksemplar' => 'EK-D42', 'kodeBuku' => null], $b->user);
        $z = $booking->ajukan($b, ['kodeEksemplar' => 'EK-D43', 'kodeBuku' => null], $b->user);
        $r3 = $this->ringkasanAdmin();
        $this->assertSame([3, $r2['eksemplar']['Tersedia']], [$r3['booking']['Diajukan'], $r3['eksemplar']['Tersedia']]);

        // D. Persetujuan: Disetujui +1, Tersedia −1, Dipesan +1.
        $booking->setujui($x->booking_code, $this->admin);
        $booking->setujui($y->booking_code, $this->admin);
        $r4 = $this->ringkasanAdmin();
        $this->assertSame([1, 2, $r3['eksemplar']['Tersedia'] - 2, 2], [$r4['booking']['Diajukan'], $r4['booking']['Disetujui'], $r4['eksemplar']['Tersedia'], $r4['eksemplar']['Dipesan']]);

        // E. Penolakan: Diajukan −1, ketersediaan tetap.
        $booking->tolakBooking($z->booking_code, 'Alasan uji', $this->admin);
        $r5 = $this->ringkasanAdmin();
        $this->assertSame([0, 1, $r4['eksemplar']['Tersedia']], [$r5['booking']['Diajukan'], $r5['booking']['Ditolak'], $r5['eksemplar']['Tersedia']]);

        // F. Realisasi: peminjaman Aktif +1, Dipesan → Dipinjam.
        $booking->realisasikan($x->booking_code, '2026-11-24', $this->admin);
        $r6 = $this->ringkasanAdmin();
        $this->assertSame([1, 1, $r5['peminjaman']['Aktif'] + 1, 1, 1], [$r6['booking']['Disetujui'], $r6['booking']['Direalisasikan'], $r6['peminjaman']['Aktif'], $r6['eksemplar']['Dipesan'], $r6['eksemplar']['Dipinjam']]);

        // G. Pembatalan booking Disetujui melepas reservasi.
        $booking->batalkan($b, $y->booking_code, $b->user);
        $r7 = $this->ringkasanAdmin();
        $this->assertSame([0, 1, 0, $r6['eksemplar']['Tersedia'] + 1], [$r7['booking']['Disetujui'], $r7['booking']['Dibatalkan'], $r7['eksemplar']['Dipesan'], $r7['eksemplar']['Tersedia']]);

        // H. Kedaluwarsa: Kedaluwarsa +1, reservasi dilepas.
        $w = $booking->ajukan($b, ['kodeEksemplar' => 'EK-D42', 'kodeBuku' => null], $b->user);
        $booking->setujui($w->booking_code, $this->admin);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 02:00:00', 'UTC'));
        $this->app->make(LayananKedaluwarsaBooking::class)->prosesJatuhTempo();
        $r8 = $this->ringkasanAdmin();
        $this->assertSame([1, 0, 0], [$r8['booking']['Kedaluwarsa'], $r8['booking']['Disetujui'], $r8['eksemplar']['Dipesan']]);
        $this->assertSame(DB::table('book_copies')->where('status', 'Tersedia')->count(), $r8['eksemplar']['Tersedia']);
    }

    // ---------------------------------------------------------------- Dashboard Anggota (I)

    public function test_dashboard_anggota_hanya_data_miliknya(): void
    {
        $saya = $this->anggotaDenganAkun('AG-D07');
        $lain = $this->anggotaDenganAkun('AG-D08');
        $this->buatPeminjaman($saya, $this->eksemplarBaru('EK-D50', 'Dipinjam'), $this->admin, 'Aktif', ['loan_code' => 'PJ-D50']);
        $this->buatPeminjaman($saya, $this->eksemplarBaru('EK-D51'), $this->admin, 'Selesai');
        $this->buatBooking($saya, $this->eksemplarBaru('EK-D52', 'Dipesan'), 'Disetujui', ['staff_id' => $this->admin->id, 'booking_code' => 'BP-D52']);
        $this->buatBooking($saya, $this->eksemplarBaru('EK-D53'), 'Diajukan');
        $this->buatBooking($saya, $this->eksemplarBaru('EK-D54'), 'Kedaluwarsa', ['decided_at' => now()->subDays(3), 'expires_at' => now()->subDay(), 'expired_at' => now()]);
        // Data anggota lain yang TIDAK boleh terhitung/terlihat.
        $this->buatPeminjaman($lain, $this->eksemplarBaru('EK-D55', 'Dipinjam'), $this->admin, 'Aktif', ['loan_code' => 'PJ-D55']);
        $this->buatBooking($lain, $this->eksemplarBaru('EK-D56'), 'Diajukan', ['booking_code' => 'BP-D56']);

        $data = $this->sebagai($saya)->props('/anggota/dashboard?member_id='.$lain->id.'&kodeAnggota=AG-D08')['data'];
        $this->assertSame(['bookingAktif' => 2, 'bookingDiajukan' => 1, 'bookingDisetujui' => 1, 'bookingKedaluwarsa' => 1, 'peminjamanAktif' => 1, 'riwayatSelesai' => 1], $data['ringkasan']);
        $this->assertSame(['PJ-D50'], array_column($data['peminjamanAktif'], 'idPeminjaman'));
        $this->assertNotContains('BP-D56', array_column($data['bookingBerjalan'], 'idBooking'));
        $this->assertSame(['AG-D07'], array_column($data['anggota'], 'kodeAnggota'));
        $json = json_encode($data);
        foreach (['PJ-D55', 'BP-D56', $lain->name, $lain->contact, $this->admin->username] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json);
        }
        $this->assertNotNull(collect($data['bookingBerjalan'])->firstWhere('idBooking', 'BP-D52')['batasPengambilan']);
    }

    // ---------------------------------------------------------------- Laporan (J)

    public function test_laporan_periode_wib_total_dan_baris_dari_server(): void
    {
        $a = $this->anggotaDenganAkun('AG-D09');
        $buat = function (string $kode, string $wib, string $status, ?Booking $asal = null) use ($a) {
            return $this->buatPeminjaman($a, $this->eksemplarBaru('E-'.$kode, $status === 'Aktif' ? 'Dipinjam' : 'Tersedia'), $this->admin, $status, [
                'loan_code' => $kode, 'loaned_at' => CarbonImmutable::parse($wib, 'Asia/Jakarta'), 'due_date' => '2026-12-20', 'booking_id' => $asal?->id,
            ]);
        };
        $asal = $this->buatBooking($a, $this->eksemplarBaru('EK-D60'), 'Direalisasikan', ['booking_code' => 'BP-D60']);
        $buat('PJ-T1', '2026-12-01 00:00:00', 'Aktif', $asal);   // batas awal tepat tengah malam WIB → masuk
        $buat('PJ-T2', '2026-12-03 23:59:59', 'Selesai');        // batas akhir → masuk
        $buat('PJ-T3', '2026-11-30 23:59:59', 'Selesai');        // sehari sebelum → tidak
        $buat('PJ-T4', '2026-12-04 00:00:00', 'Selesai');        // = 03 Des 17:00 UTC → tidak
        $kembali = Loan::where('loan_code', 'PJ-T2')->first();
        (new BookReturn)->forceFill(['return_code' => 'KB-T2', 'loan_id' => $kembali->id, 'staff_id' => $this->admin->id, 'returned_at' => CarbonImmutable::parse('2026-12-04 06:59:59', 'Asia/Jakarta')])->save();

        $props = $this->sebagaiAdmin()->props('/admin/laporan?jenis=peminjaman&awal=2026-12-01&akhir=2026-12-03');
        $l = $props['data']['laporan'];
        $this->assertSame(['PJ-T1', 'PJ-T2'], array_column($l['baris'], 'idPeminjaman'));
        $this->assertSame([1, 2], array_column($l['baris'], 'no'));
        $this->assertSame(['BP-D60', null], array_column($l['baris'], 'idBooking'), 'Asal booking peminjaman hasil realisasi.');
        $this->assertSame(['kodeAnggota' => 'AG-D09', 'nama' => $a->name], $l['baris'][0]['anggota']);
        $this->assertSame(['2026-12-01', '2026-12-20', 'Aktif'], [$l['baris'][0]['tanggalPinjam'], $l['baris'][0]['tanggalJatuhTempo'], $l['baris'][0]['status']]);
        $this->assertSame([['label' => 'Jumlah peminjaman', 'nilai' => 2], ['label' => 'Aktif', 'nilai' => 1], ['label' => 'Selesai', 'nilai' => 1]], $l['ringkasan']);

        // Pengembalian 04 Des 06:59:59 WIB (= 03 Des 23:59:59 UTC) masuk periode 04 Des WIB, bukan 03 Des.
        $kb = $this->sebagaiAdmin()->props('/admin/laporan?jenis=pengembalian&awal=2026-12-04&akhir=2026-12-04')['data']['laporan'];
        $this->assertSame(['KB-T2'], array_column($kb['baris'], 'idPengembalian'));
        $this->assertSame(['nama' => $this->admin->name], $kb['baris'][0]['pemroses']);
        $this->assertSame('2026-12-04', $kb['baris'][0]['tanggalKembali']);
        $this->assertSame([], $this->sebagaiAdmin()->props('/admin/laporan?jenis=pengembalian&awal=2026-12-03&akhir=2026-12-03')['data']['laporan']['baris']);

        // Laporan buku: ringkasan dari agregat SQL.
        $buku = $this->sebagaiAdmin()->props('/admin/laporan?jenis=buku')['data']['laporan'];
        $this->assertSame(DB::table('book_copies')->count(), collect($buku['ringkasan'])->firstWhere('label', 'Jumlah eksemplar')['nilai']);
        $this->assertSame(DB::table('book_copies')->where('status', 'Dipinjam')->count(), collect($buku['ringkasan'])->firstWhere('label', 'Eksemplar Dipinjam')['nilai']);
        $baris = collect($buku['baris'])->firstWhere('kodeBuku', 'BK-E-PJ-T1');
        $this->assertSame([1, 0, 1], [$baris['jumlahEksemplar'], $baris['tersedia'], $baris['dipinjam']]);
    }

    public function test_jumlah_query_laporan_dan_halaman_formulir_terbatas(): void
    {
        $a = $this->anggotaDenganAkun('AG-D10');
        $hitung = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->sebagaiAdmin()->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $url = ['/admin/laporan?jenis=peminjaman', '/admin/laporan?jenis=pengembalian', '/admin/laporan?jenis=buku', '/admin/peminjaman', '/admin/booking/realisasi'];
        // Data awal memuat setiap jenis baris (agar eager loading terhitung sejak awal).
        $p0 = $this->buatPeminjaman($a, $this->eksemplarBaru('EK-D70'), $this->admin, 'Selesai');
        (new BookReturn)->forceFill(['return_code' => 'KB-D70', 'loan_id' => $p0->id, 'staff_id' => $this->admin->id, 'returned_at' => now()])->save();
        $this->buatPeminjaman($a, $this->eksemplarBaru('EK-D71', 'Dipinjam'), $this->admin);
        $this->buatBooking($a, $this->eksemplarBaru('EK-D72', 'Dipesan'), 'Disetujui', ['staff_id' => $this->admin->id]);
        $awal = array_map($hitung, $url);
        for ($i = 0; $i < 15; $i++) {
            $p = $this->buatPeminjaman($a, $this->eksemplarBaru(sprintf('EK-D8%02d', $i)), $this->admin, 'Selesai');
            (new BookReturn)->forceFill(['return_code' => "KB-D8{$i}", 'loan_id' => $p->id, 'staff_id' => $this->admin->id, 'returned_at' => now()])->save();
        }
        $this->assertSame($awal, array_map($hitung, $url), 'Tanpa N+1 pada laporan & formulir.');

        // Formulir Peminjaman/Realisasi tidak memuat riwayat (hanya transaksi berjalan).
        $data = $this->sebagaiAdmin()->props('/admin/peminjaman')['data'];
        $this->assertSame([], array_values(array_filter($data['peminjaman'], fn ($p) => $p['status'] !== 'Aktif')));
        $this->assertArrayNotHasKey('pengembalian', $data);
        foreach ($data['anggota'] as $x) {
            $this->assertNull($x['kontak'], 'Kontak tidak dikirim ke halaman yang tidak menampilkannya.');
        }
    }

    // ---------------------------------------------------------------- Keamanan

    public function test_dashboard_dan_laporan_terpisah_per_peran(): void
    {
        $a = $this->anggotaDenganAkun('AG-D11');
        $this->sebagai($a)->get('/admin/dashboard')->assertForbidden();
        $this->sebagai($a)->getJson('/admin/laporan?jenis=anggota')->assertForbidden();
        $this->sebagaiAdmin()->get('/anggota/dashboard')->assertForbidden();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->getJson('/admin/laporan?jenis=anggota')->assertUnauthorized();

        $json = json_encode($this->sebagaiAdmin()->props('/admin/dashboard'));
        foreach ([$a->user->password, 'remember_token', 'password'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json);
        }
    }
}
