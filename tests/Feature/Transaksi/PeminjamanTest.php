<?php

namespace Tests\Feature\Transaksi;

use App\Enums\StatusAnggota;
use App\Models\AuditLog;
use App\Models\BookCopy;
use App\Models\BookReturn;
use App\Models\Loan;
use App\Models\User;
use App\Services\Transaksi\LayananPeminjaman;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Batch 2 — peminjaman langsung & pengembalian (butir uji 1–14).
 */
class PeminjamanTest extends TransaksiTestCase
{
    private function pinjam(array $isian, ?User $admin = null)
    {
        return $this->sebagaiAdmin($admin)->from('/admin/peminjaman')->post('/admin/peminjaman', $isian);
    }

    private function kembalikan(string $kode)
    {
        return $this->sebagaiAdmin()->from('/admin/pengembalian')->post('/admin/pengembalian', ['idPeminjaman' => $kode]);
    }

    // ---------------------------------------------------------------- 1, 6 peminjaman langsung

    public function test_peminjaman_langsung_berhasil_dengan_admin_dari_sesi(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 03:15:00', 'UTC'));
        $anggota = $this->anggotaDenganAkun('AG-T01');
        $e = $this->eksemplarBaru('EK-T01');
        $adminLain = $this->buatAdmin();

        $this->pinjam([
            'kodeAnggota' => 'AG-T01', 'kodeBuku' => 'BK-EK-T01', 'kodeEksemplar' => 'EK-T01', 'tanggalJatuhTempo' => '2026-11-23',
            'staff_id' => $adminLain->id, 'member_id' => 999, 'status' => 'Selesai', 'loaned_at' => '2000-01-01',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/peminjaman');

        $p = Loan::query()->sole();
        $this->assertSame([$anggota->id, $e->id, $this->admin->id, null, 'Aktif'], [$p->member_id, $p->copy_id, $p->staff_id, $p->booking_id, $p->status->value]);
        $this->assertSame('2026-11-16 03:15:00.000000', $p->getRawOriginal('loaned_at'), 'loaned_at = instant UTC.');
        $this->assertSame('2026-11-23', $p->getRawOriginal('due_date'), 'due_date DATE tanpa konversi zona.');
        $this->assertMatchesRegularExpression('/^PJ-\d{3,}$/', $p->loan_code);
        $this->assertSame('Dipinjam', $e->fresh()->status->value);
        $this->assertSame("Peminjaman {$p->loan_code} berhasil dicatat.", session('status'));
        $this->assertSame($p->loan_code, session('transaksi'));

        $audit = AuditLog::query()->sole();
        $this->assertSame(['peminjaman.dibuat', $this->admin->id, 'loan', $p->id], [$audit->action, $audit->actor_id, $audit->subject_type, $audit->subject_id]);
        $this->assertEqualsCanonicalizing(['kode_peminjaman' => $p->loan_code, 'kode_anggota' => 'AG-T01', 'kode_eksemplar' => 'EK-T01', 'jatuh_tempo' => '2026-11-23', 'sumber' => 'langsung'], $audit->metadata);
    }

    // ---------------------------------------------------------------- 2–5 penolakan

    public function test_anggota_nonaktif_dan_eksemplar_tidak_tersedia_ditolak(): void
    {
        $this->anggotaDenganAkun('AG-T02', StatusAnggota::Nonaktif);
        $this->anggotaDenganAkun('AG-T03');
        $tersedia = $this->eksemplarBaru('EK-T02');
        $dipesan = $this->eksemplarBaru('EK-T03', 'Dipesan');
        $this->buatBooking($this->buatAnggota(), $dipesan, 'Disetujui', ['staff_id' => $this->admin->id]);
        $dipinjam = $this->eksemplarBaru('EK-T04', 'Dipinjam');
        $this->buatPeminjaman($this->buatAnggota(), $dipinjam, $this->admin);

        $tempo = $this->besok();
        $this->pinjam(['kodeAnggota' => 'AG-T02', 'kodeEksemplar' => 'EK-T02', 'tanggalJatuhTempo' => $tempo])
            ->assertSessionHasErrors(['kodeAnggota' => 'Anggota tidak valid: hanya anggota berstatus Aktif yang dapat meminjam.']);
        $this->pinjam(['kodeAnggota' => 'AG-TIDAK-ADA', 'kodeEksemplar' => 'EK-T02', 'tanggalJatuhTempo' => $tempo])->assertSessionHasErrors('kodeAnggota');
        $this->pinjam(['kodeAnggota' => 'AG-T03', 'kodeEksemplar' => 'EK-T03', 'tanggalJatuhTempo' => $tempo])
            ->assertSessionHasErrors(['kodeEksemplar' => 'Buku tidak tersedia: eksemplar EK-T03 sedang dipesan dan hanya dapat dipinjam melalui Realisasi Booking.']);
        $this->pinjam(['kodeAnggota' => 'AG-T03', 'kodeEksemplar' => 'EK-T04', 'tanggalJatuhTempo' => $tempo])
            ->assertSessionHasErrors(['kodeEksemplar' => 'Buku tidak tersedia: eksemplar EK-T04 masih dalam peminjaman Aktif.']);
        $this->pinjam(['kodeAnggota' => 'AG-T03', 'kodeEksemplar' => 'EK-TIDAK-ADA', 'tanggalJatuhTempo' => $tempo])->assertSessionHasErrors('kodeEksemplar');
        $this->pinjam(['kodeAnggota' => 'AG-T03', 'kodeBuku' => 'BK-LAIN', 'kodeEksemplar' => 'EK-T02', 'tanggalJatuhTempo' => $tempo])
            ->assertSessionHasErrors(['kodeEksemplar' => 'Kode Eksemplar tidak terdaftar untuk buku ini.']);

        // 4: peminjaman Aktif ganda untuk eksemplar yang sama.
        $this->pinjam(['kodeAnggota' => 'AG-T03', 'kodeEksemplar' => 'EK-T02', 'tanggalJatuhTempo' => $tempo])->assertSessionHasNoErrors();
        $this->pinjam(['kodeAnggota' => 'AG-T03', 'kodeEksemplar' => 'EK-T02', 'tanggalJatuhTempo' => $tempo])->assertSessionHasErrors('kodeEksemplar');

        $this->assertSame(1, Loan::where('copy_id', $tersedia->id)->count());
        $this->assertSame(['Dipesan', 'Dipinjam'], [$dipesan->fresh()->status->value, $dipinjam->fresh()->status->value]);
        $this->assertSame(['peminjaman.dibuat'], $this->aksiAudit());
    }

    public function test_tanggal_jatuh_tempo_wajib_valid_dan_setelah_hari_ini(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 18:30:00', 'UTC')); // = 17 Nov 01.30 WIB
        $this->anggotaDenganAkun('AG-T04');
        $this->eksemplarBaru('EK-T05');
        $isi = ['kodeAnggota' => 'AG-T04', 'kodeEksemplar' => 'EK-T05'];

        foreach (['' => 'Tanggal Jatuh Tempo wajib diisi.', '2026-02-30' => 'Tanggal Jatuh Tempo tidak valid.', '17-11-2026' => 'Tanggal Jatuh Tempo tidak valid.',
            '2026-11-17' => 'Tanggal Jatuh Tempo harus setelah Tanggal Pinjam.', '2026-11-16' => 'Tanggal Jatuh Tempo harus setelah Tanggal Pinjam.'] as $tanggal => $pesan) {
            $this->pinjam($isi + ['tanggalJatuhTempo' => $tanggal])->assertSessionHasErrors(['tanggalJatuhTempo' => $pesan]);
        }
        $this->assertSame(0, Loan::count());

        // Hari ini (WIB) = 17 Nov → 18 Nov sudah sah walau UTC masih 16 Nov.
        $this->pinjam($isi + ['tanggalJatuhTempo' => '2026-11-18'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-18', Loan::query()->sole()->getRawOriginal('due_date'));
    }

    // ---------------------------------------------------------------- 7 rollback

    public function test_kegagalan_di_tengah_transaksi_tidak_meninggalkan_rekaman(): void
    {
        $this->anggotaDenganAkun('AG-T05');
        $e = $this->eksemplarBaru('EK-T06');
        AuditLog::creating(fn () => throw new RuntimeException('Simulasi kegagalan audit.'));

        try {
            $this->app->make(LayananPeminjaman::class)->pinjam(['kodeAnggota' => 'AG-T05', 'kodeEksemplar' => 'EK-T06', 'tanggalJatuhTempo' => $this->besok()], $this->admin);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
        } finally {
            AuditLog::flushEventListeners();
        }

        $this->assertSame(0, Loan::count());
        $this->assertSame('Tersedia', $e->fresh()->status->value);
        $this->assertSame([], $this->aksiAudit());
    }

    // ---------------------------------------------------------------- 8, 11–13 pengembalian

    public function test_pengembalian_berhasil(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 02:00:00', 'UTC'));
        $anggota = $this->anggotaDenganAkun('AG-T06');
        $e = $this->eksemplarBaru('EK-T07', 'Dipinjam');
        $p = $this->buatPeminjaman($anggota, $e, $this->admin, 'Aktif', ['loan_code' => 'PJ-T07']);
        $adminLain = $this->buatAdmin();

        $this->sebagaiAdmin($adminLain)->post('/admin/pengembalian', ['idPeminjaman' => 'PJ-T07', 'staff_id' => $this->admin->id])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/pengembalian');

        $k = BookReturn::query()->sole();
        $this->assertSame([$p->id, $adminLain->id, '2026-11-20 02:00:00.000000'], [$k->loan_id, $k->staff_id, $k->getRawOriginal('returned_at')]);
        $this->assertMatchesRegularExpression('/^KB-\d{3,}$/', $k->return_code);
        $this->assertSame('Selesai', $p->fresh()->status->value);
        $this->assertSame('Tersedia', $e->fresh()->status->value);
        $this->assertSame($anggota->id, $p->fresh()->member_id, 'Riwayat peminjaman tetap.');

        $audit = AuditLog::query()->sole();
        $this->assertSame(['pengembalian.dibuat', $adminLain->id, 'return', $k->id], [$audit->action, $audit->actor_id, $audit->subject_type, $audit->subject_id]);
        $this->assertEqualsCanonicalizing(['kode_pengembalian' => $k->return_code, 'kode_peminjaman' => 'PJ-T07', 'kode_eksemplar' => 'EK-T07'], $audit->metadata);
    }

    // ---------------------------------------------------------------- 9 anggota Nonaktif

    public function test_pinjaman_anggota_nonaktif_tetap_dapat_dikembalikan_tanpa_mengaktifkan(): void
    {
        $anggota = $this->anggotaDenganAkun('AG-T07');
        $e = $this->eksemplarBaru('EK-T08', 'Dipinjam');
        $p = $this->buatPeminjaman($anggota, $e, $this->admin, 'Aktif', ['loan_code' => 'PJ-T08']);
        $anggota->forceFill(['status' => StatusAnggota::Nonaktif])->save();

        $this->kembalikan('PJ-T08')->assertSessionHasNoErrors();

        $this->assertSame('Selesai', $p->fresh()->status->value);
        $this->assertSame('Tersedia', $e->fresh()->status->value);
        $this->assertSame(StatusAnggota::Nonaktif, $anggota->fresh()->status, 'Pengembalian tidak mengaktifkan anggota.');
        $this->assertNotNull($anggota->fresh()->user_id, 'Akun tetap ada.');
        $this->assertSame(1, BookReturn::where('loan_id', $p->id)->count());
    }

    // ---------------------------------------------------------------- 10 ganda

    public function test_pengembalian_ganda_dan_tidak_valid_ditolak(): void
    {
        $anggota = $this->anggotaDenganAkun('AG-T08');
        $e = $this->eksemplarBaru('EK-T09', 'Dipinjam');
        $this->buatPeminjaman($anggota, $e, $this->admin, 'Aktif', ['loan_code' => 'PJ-T09']);

        $this->kembalikan('PJ-T09')->assertSessionHasNoErrors();
        $this->kembalikan('PJ-T09')->assertSessionHasErrors(['umum' => 'Pengembalian ditolak: transaksi PJ-T09 sudah dikembalikan.']);
        $this->kembalikan('PJ-TIDAK-ADA')->assertSessionHasErrors(['umum' => 'Transaksi PJ-TIDAK-ADA tidak ditemukan.']);
        $this->kembalikan('')->assertSessionHasErrors('idPeminjaman');

        $this->assertSame(1, BookReturn::count());
        $this->assertSame(['pengembalian.dibuat'], $this->aksiAudit());
        // UNIQUE returns.loan_id tetap ditegakkan MySQL.
        $this->assertDitolakDb(1062, fn () => (new BookReturn)->forceFill(['return_code' => 'KB-X', 'loan_id' => Loan::where('loan_code', 'PJ-T09')->value('id'), 'staff_id' => $this->admin->id, 'returned_at' => now()])->save());
    }

    // ---------------------------------------------------------------- 14 rollback pengembalian

    public function test_rollback_pengembalian_mempertahankan_keadaan_awal(): void
    {
        $anggota = $this->anggotaDenganAkun('AG-T09');
        $e = $this->eksemplarBaru('EK-T10', 'Dipinjam');
        $p = $this->buatPeminjaman($anggota, $e, $this->admin, 'Aktif', ['loan_code' => 'PJ-T10']);
        BookCopy::updating(fn () => throw new RuntimeException('Simulasi kegagalan.'));

        try {
            $this->app->make(LayananPeminjaman::class)->kembalikan('PJ-T10', $this->admin);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
        } finally {
            BookCopy::flushEventListeners();
        }

        $this->assertSame(['Aktif', 'Dipinjam', 0], [$p->fresh()->status->value, $e->fresh()->status->value, BookReturn::count()]);
        $this->assertSame([], $this->aksiAudit());
    }

    // ---------------------------------------------------------------- 59, 61 otorisasi

    public function test_rute_transaksi_hanya_untuk_admin(): void
    {
        $anggota = $this->anggotaDenganAkun('AG-T10');
        $this->eksemplarBaru('EK-T11');
        $isi = ['kodeAnggota' => 'AG-T10', 'kodeEksemplar' => 'EK-T11', 'tanggalJatuhTempo' => $this->besok()];

        $this->post('/admin/peminjaman', $isi)->assertRedirect(route('login'));
        $this->sebagai($anggota)->post('/admin/peminjaman', $isi)->assertForbidden();
        $this->sebagai($anggota)->post('/admin/pengembalian', ['idPeminjaman' => 'PJ-001'])->assertForbidden();
        $this->sebagai($anggota)->post('/admin/booking/BP-001/setujui')->assertForbidden();
        $this->sebagai($anggota)->post('/admin/booking/BP-001/tolak', ['alasanPenolakan' => 'x'])->assertForbidden();
        $this->sebagai($anggota)->post('/admin/booking/BP-001/realisasi', ['tanggalJatuhTempo' => $this->besok()])->assertForbidden();

        $nonaktif = $this->buatAdmin(['is_active' => false]);
        $this->sebagaiAdmin($nonaktif)->post('/admin/peminjaman', $isi);

        $this->assertSame(0, Loan::count());
        $this->assertSame([], $this->aksiAudit());
    }
}
