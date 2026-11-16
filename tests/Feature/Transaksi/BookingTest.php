<?php

namespace Tests\Feature\Transaksi;

use App\Enums\StatusAnggota;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Loan;
use App\Models\Member;
use App\Services\LayananAnggota;
use App\Services\Transaksi\LayananBooking;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Batch 2 — siklus booking (butir uji 15–40, 59–64).
 */
class BookingTest extends TransaksiTestCase
{
    private function ajukan(Member $anggota, array $isian)
    {
        return $this->sebagai($anggota)->from('/anggota/booking/ajukan')->post('/anggota/booking', $isian);
    }

    private function bookingDiajukan(Member $anggota, string $kodeEksemplar = 'EK-B01'): Booking
    {
        $this->ajukan($anggota, ['kodeEksemplar' => $kodeEksemplar])->assertSessionHasNoErrors();

        return Booking::where('member_id', $anggota->id)->latest('id')->firstOrFail();
    }

    // ---------------------------------------------------------------- 15–20 pengajuan

    public function test_pengajuan_berhasil_tanpa_mereservasi_eksemplar(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 04:00:00', 'UTC'));
        $a = $this->anggotaDenganAkun('AG-B01');
        $e = $this->eksemplarBaru('EK-B01');
        $lain = $this->anggotaDenganAkun('AG-B02');

        $this->ajukan($a, ['kodeBuku' => 'BK-EK-B01', 'kodeEksemplar' => 'EK-B01', 'member_id' => $lain->id, 'kodeAnggota' => 'AG-B02', 'status' => 'Disetujui'])
            ->assertSessionHasNoErrors()->assertRedirect('/anggota/booking/ajukan');

        $b = Booking::query()->sole();
        $this->assertSame([$a->id, $e->id, 'Diajukan', '2026-11-16 04:00:00.000000', null, null], [$b->member_id, $b->copy_id, $b->status->value, $b->getRawOriginal('submitted_at'), $b->decided_at, $b->expires_at]);
        $this->assertMatchesRegularExpression('/^BP-\d{3,}$/', $b->booking_code);
        $this->assertSame('Tersedia', $e->fresh()->status->value, 'Diajukan tidak mereservasi eksemplar.');
        $this->assertSame(0, Loan::count(), 'Tanpa peminjaman otomatis.');
        $this->assertSame(['booking.diajukan', $a->user_id], [AuditLog::query()->sole()->action, AuditLog::query()->sole()->actor_id]);

        // Anggota lain boleh mengajukan eksemplar yang sama selama belum Dipesan (perilaku teruji, TBD-06).
        $this->ajukan($lain, ['kodeEksemplar' => 'EK-B01'])->assertSessionHasNoErrors();
        $this->assertSame(2, Booking::where('copy_id', $e->id)->where('status', 'Diajukan')->count());
    }

    public function test_pengajuan_ditolak_untuk_nonaktif_eksemplar_tidak_tersedia_dan_ganda(): void
    {
        $a = $this->anggotaDenganAkun('AG-B03');
        $this->eksemplarBaru('EK-B02');
        $dipinjam = $this->eksemplarBaru('EK-B03', 'Dipinjam');
        $this->buatPeminjaman($this->buatAnggota(), $dipinjam, $this->admin);
        $dipesan = $this->eksemplarBaru('EK-B04', 'Dipesan');
        $this->buatBooking($this->buatAnggota(), $dipesan, 'Disetujui', ['staff_id' => $this->admin->id]);

        $this->ajukan($a, ['kodeEksemplar' => 'EK-B03'])->assertSessionHasErrors(['kodeEksemplar' => 'Booking ditolak: eksemplar EK-B03 berstatus Dipinjam. Hanya eksemplar Tersedia yang dapat dibooking.']);
        $this->ajukan($a, ['kodeEksemplar' => 'EK-B04'])->assertSessionHasErrors('kodeEksemplar');
        $this->ajukan($a, ['kodeEksemplar' => 'EK-TIDAK'])->assertSessionHasErrors(['kodeEksemplar' => 'Kode Eksemplar tidak terdaftar.']);
        $this->ajukan($a, ['kodeEksemplar' => ''])->assertSessionHasErrors(['kodeEksemplar' => 'Pilih Kode Eksemplar.']);
        $this->ajukan($a, ['kodeBuku' => 'BK-LAIN', 'kodeEksemplar' => 'EK-B02'])->assertSessionHasErrors(['kodeEksemplar' => 'Kode Eksemplar tidak terdaftar untuk buku ini.']);

        // 19: pengajuan ganda (anggota & eksemplar sama, masih Diajukan) ditolak.
        $b = $this->bookingDiajukan($a, 'EK-B02');
        $this->ajukan($a, ['kodeEksemplar' => 'EK-B02'])->assertSessionHasErrors(['kodeEksemplar' => "Anda sudah memiliki booking {$b->booking_code} (Diajukan) untuk eksemplar ini."]);
        $this->assertSame(1, Booking::where('member_id', $a->id)->count());

        // 16: anggota Nonaktif (service; sesi Nonaktif sudah ditolak middleware akun.aktif).
        $a->forceFill(['status' => StatusAnggota::Nonaktif])->save();
        try {
            $this->app->make(LayananBooking::class)->ajukan($a->fresh(), ['kodeEksemplar' => 'EK-B02', 'kodeBuku' => null], $a->user);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['Booking ditolak: keanggotaan Anda berstatus Nonaktif. Hubungi Admin perpustakaan.'], $e->errors()['umum']);
        }
        $this->ajukan($a->fresh(), ['kodeEksemplar' => 'EK-B02'])->assertRedirect(route('login'));
        $this->assertSame(['booking.diajukan'], $this->aksiAudit());
    }

    // ---------------------------------------------------------------- 21, 24, 25 persetujuan

    public function test_persetujuan_mereservasi_dan_batas_tepat_48_jam(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 09:30:15.250000', 'UTC'));
        $a = $this->anggotaDenganAkun('AG-B04');
        $e = $this->eksemplarBaru('EK-B05');
        $b = $this->bookingDiajukan($a, 'EK-B05');

        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/setujui", ['staff_id' => 1, 'expires_at' => '2099-01-01'])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/booking/verifikasi');

        $b->refresh();
        $this->assertSame(['Disetujui', $this->admin->id], [$b->status->value, $b->staff_id]);
        $this->assertSame('2026-11-16 09:30:15.250000', $b->getRawOriginal('decided_at'));
        $this->assertSame('2026-11-18 09:30:15.250000', $b->getRawOriginal('expires_at'), 'expires_at = decided_at + 48 jam tepat (UTC).');
        $this->assertNull($b->expired_at);
        $this->assertSame('Dipesan', $e->fresh()->status->value);

        $audit = AuditLog::where('action', 'booking.disetujui')->sole();
        $this->assertSame([$this->admin->id, 'booking', $b->id], [$audit->actor_id, $audit->subject_type, $audit->subject_id]);
        $this->assertSame('2026-11-18T09:30:15Z', $audit->metadata['batas_pengambilan']);
    }

    // ---------------------------------------------------------------- 22, 23, 28 penolakan persetujuan

    public function test_persetujuan_ditolak_untuk_nonaktif_eksemplar_tidak_tersedia_dan_ganda(): void
    {
        $a = $this->anggotaDenganAkun('AG-B05');
        $c = $this->anggotaDenganAkun('AG-B06');
        $e = $this->eksemplarBaru('EK-B06');
        $b1 = $this->bookingDiajukan($a, 'EK-B06');
        $b2 = $this->bookingDiajukan($c, 'EK-B06');

        $this->sebagaiAdmin()->post("/admin/booking/{$b1->booking_code}/setujui")->assertSessionHasNoErrors();
        // 28: persetujuan ganda booking yang sama.
        $this->sebagaiAdmin()->post("/admin/booking/{$b1->booking_code}/setujui")
            ->assertSessionHasErrors(['umum' => "Booking {$b1->booking_code} berstatus Disetujui; hanya booking Diajukan yang dapat disetujui."]);
        // 23: eksemplar sudah Dipesan booking lain.
        $this->sebagaiAdmin()->post("/admin/booking/{$b2->booking_code}/setujui")
            ->assertSessionHasErrors(['umum' => 'Eksemplar EK-B06 tidak tersedia (status Dipesan); booking hanya dapat ditolak.']);
        $this->assertSame(['Disetujui', 'Diajukan'], [$b1->fresh()->status->value, $b2->fresh()->status->value]);

        // 22: anggota Nonaktif.
        $f = $this->anggotaDenganAkun('AG-B07');
        $this->eksemplarBaru('EK-B07');
        $b3 = $this->bookingDiajukan($f, 'EK-B07');
        $f->forceFill(['status' => StatusAnggota::Nonaktif])->save();
        $this->sebagaiAdmin()->post("/admin/booking/{$b3->booking_code}/setujui")
            ->assertSessionHasErrors(['umum' => 'Booking tidak dapat disetujui karena status anggota AG-B07 Nonaktif.']);
        $this->assertSame('Diajukan', $b3->fresh()->status->value);
        $this->assertSame(1, AuditLog::where('action', 'booking.disetujui')->count());
        $this->sebagaiAdmin()->post('/admin/booking/BP-TIDAK/setujui')->assertSessionHasErrors(['umum' => 'Booking BP-TIDAK tidak ditemukan.']);
    }

    // ---------------------------------------------------------------- 26, 27 penolakan

    public function test_penolakan_wajib_alasan_dan_eksemplar_tetap_tersedia(): void
    {
        $a = $this->anggotaDenganAkun('AG-B08');
        $e = $this->eksemplarBaru('EK-B08');
        $b = $this->bookingDiajukan($a, 'EK-B08');

        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/tolak", ['alasanPenolakan' => '   '])->assertSessionHasErrors(['alasanPenolakan' => 'Alasan Penolakan wajib diisi.']);
        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/tolak", ['alasanPenolakan' => str_repeat('x', 201)])->assertSessionHasErrors(['alasanPenolakan' => 'Alasan Penolakan maksimal 200 karakter.']);
        $this->assertSame('Diajukan', $b->fresh()->status->value);

        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/tolak", ['alasanPenolakan' => '  Eksemplar dalam perbaikan.  '])->assertSessionHasNoErrors();
        $b->refresh();
        $this->assertSame(['Ditolak', 'Eksemplar dalam perbaikan.', $this->admin->id], [$b->status->value, $b->rejection_reason, $b->staff_id]);
        $this->assertNotNull($b->decided_at);
        $this->assertNull($b->expires_at);
        $this->assertSame('Tersedia', $e->fresh()->status->value);
        $this->assertSame(0, Loan::count());
        $this->assertStringNotContainsString('perbaikan', AuditLog::where('action', 'booking.ditolak')->sole()->toJson(), 'Alasan tidak disalin ke audit.');

        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/tolak", ['alasanPenolakan' => 'Lagi'])->assertSessionHasErrors('umum');
    }

    // ---------------------------------------------------------------- 30–34 realisasi

    public function test_realisasi_membuat_tepat_satu_peminjaman(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 01:00:00', 'UTC'));
        $a = $this->anggotaDenganAkun('AG-B09');
        $e = $this->eksemplarBaru('EK-B09');
        $b = $this->bookingDiajukan($a, 'EK-B09');
        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/setujui")->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 02:00:00', 'UTC'));
        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/realisasi", ['tanggalJatuhTempo' => '2026-11-24', 'member_id' => 1])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/booking/realisasi');

        $p = Loan::query()->sole();
        $this->assertSame([$a->id, $e->id, $b->id, $this->admin->id, 'Aktif'], [$p->member_id, $p->copy_id, $p->booking_id, $p->staff_id, $p->status->value]);
        $this->assertSame('2026-11-24', $p->getRawOriginal('due_date'));
        $this->assertSame('2026-11-17 02:00:00.000000', $p->getRawOriginal('loaned_at'));
        $b->refresh();
        $this->assertSame('Direalisasikan', $b->status->value);
        $this->assertSame('2026-11-18 01:00:00.000000', $b->getRawOriginal('expires_at'), 'Riwayat batas pengambilan dipertahankan.');
        $this->assertSame('Dipinjam', $e->fresh()->status->value);
        $this->assertSame(session('transaksi'), $p->loan_code);

        // 34: realisasi ganda.
        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/realisasi", ['tanggalJatuhTempo' => '2026-11-24'])
            ->assertSessionHasErrors(['umum' => "Booking {$b->booking_code} berstatus Direalisasikan; hanya booking Disetujui yang dapat direalisasikan."]);
        $this->assertSame(1, Loan::count());
        $this->assertDitolakDb(1062, fn () => $this->buatPeminjaman($a, $this->eksemplarBaru('EK-B99'), $this->admin, 'Selesai', ['booking_id' => $b->id]), 'UNIQUE loans.booking_id.');
        $this->assertSame(['booking.diajukan', 'booking.disetujui', 'peminjaman.dibuat', 'booking.direalisasikan'], $this->aksiAudit());

        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/realisasi", ['tanggalJatuhTempo' => '2026-11-17'])->assertSessionHasErrors('tanggalJatuhTempo');
    }

    public function test_realisasi_ditolak_bila_eksemplar_tidak_dipesan_untuk_booking_ini(): void
    {
        $a = $this->anggotaDenganAkun('AG-B10');
        $e = $this->eksemplarBaru('EK-B10', 'Dipinjam'); // status tidak konsisten (bukan Dipesan)
        $b = $this->buatBooking($a, $e, 'Disetujui', ['staff_id' => $this->admin->id, 'booking_code' => 'BP-B10']);

        $this->sebagaiAdmin()->post('/admin/booking/BP-B10/realisasi', ['tanggalJatuhTempo' => $this->besok()])
            ->assertSessionHasErrors(['umum' => 'Eksemplar EK-B10 tidak dipesan untuk booking ini.']);
        $this->assertSame([0, 'Disetujui'], [Loan::count(), $b->fresh()->status->value]);
    }

    // ---------------------------------------------------------------- 36–40 pembatalan

    public function test_anggota_membatalkan_booking_sendiri(): void
    {
        $a = $this->anggotaDenganAkun('AG-B11');
        $e1 = $this->eksemplarBaru('EK-B11');
        $e2 = $this->eksemplarBaru('EK-B12');
        $diajukan = $this->bookingDiajukan($a, 'EK-B11');
        $disetujui = $this->bookingDiajukan($a, 'EK-B12');
        $this->sebagaiAdmin()->post("/admin/booking/{$disetujui->booking_code}/setujui")->assertSessionHasNoErrors();
        $this->assertSame('Dipesan', $e2->fresh()->status->value);

        $this->sebagai($a)->post("/anggota/booking/{$diajukan->booking_code}/batalkan")->assertSessionHasNoErrors()->assertRedirect('/anggota/booking/status');
        $this->assertSame(['Dibatalkan', 'Tersedia'], [$diajukan->fresh()->status->value, $e1->fresh()->status->value]);

        $this->sebagai($a)->post("/anggota/booking/{$disetujui->booking_code}/batalkan")->assertSessionHasNoErrors();
        $this->assertSame(['Dibatalkan', 'Tersedia'], [$disetujui->fresh()->status->value, $e2->fresh()->status->value], 'Reservasi dilepas.');
        $this->assertNotNull($disetujui->fresh()->expires_at, 'Riwayat batas pengambilan tetap.');

        $audit = AuditLog::where('action', 'booking.dibatalkan')->orderBy('id')->get();
        $this->assertSame([$a->user_id, $a->user_id], $audit->pluck('actor_id')->all());
        $this->assertSame([false, true], $audit->map(fn ($l) => $l->metadata['eksemplar_dilepas'])->all());

        // 40: status akhir tidak dapat dibatalkan lagi.
        $this->sebagai($a)->post("/anggota/booking/{$disetujui->booking_code}/batalkan")
            ->assertSessionHasErrors(['umum' => "Booking {$disetujui->booking_code} berstatus Dibatalkan dan tidak dapat dibatalkan."]);
        foreach (['Ditolak', 'Direalisasikan'] as $i => $status) {
            $x = $this->buatBooking($a, $this->eksemplarBaru("EK-B2{$i}"), $status, ['booking_code' => "BP-X{$i}"]);
            $this->sebagai($a)->post("/anggota/booking/BP-X{$i}/batalkan")->assertSessionHasErrors('umum');
            $this->assertSame($status, $x->fresh()->status->value);
        }
    }

    public function test_anggota_lain_tidak_dapat_membatalkan_atau_melihat_booking_orang_lain(): void
    {
        $a = $this->anggotaDenganAkun('AG-B12');
        $c = $this->anggotaDenganAkun('AG-B13');
        $this->eksemplarBaru('EK-B13');
        $b = $this->bookingDiajukan($a, 'EK-B13');

        $this->sebagai($c)->post("/anggota/booking/{$b->booking_code}/batalkan")
            ->assertSessionHasErrors(['umum' => "Booking {$b->booking_code} tidak ditemukan pada akun Anda."]);
        $this->assertSame('Diajukan', $b->fresh()->status->value);

        // 60: halaman Anggota hanya memuat booking/peminjaman miliknya.
        $this->sebagai($c);
        foreach (['/anggota/booking/status', '/anggota/history', '/anggota/dashboard'] as $url) {
            $props = $this->props($url.'?kodeAnggota=AG-B12&member_id='.$a->id);
            $this->assertNotContains($b->booking_code, array_column($props['data']['booking'] ?? [], 'idBooking'), $url);
            $this->assertStringNotContainsString($a->contact, json_encode($props['data']), $url);
        }
        // Anggota tidak dapat menyetujui/merealisasi.
        $this->sebagai($c)->post("/admin/booking/{$b->booking_code}/setujui")->assertForbidden();
        $this->assertSame(['booking.diajukan'], $this->aksiAudit());
    }

    // ---------------------------------------------------------------- 29 F-01 (berurutan; balapan dua koneksi di KonkurensiTransaksiTest)

    public function test_f01_persetujuan_dan_penonaktifan_saling_menjaga(): void
    {
        $a = $this->anggotaDenganAkun('AG-B14');
        $this->eksemplarBaru('EK-B14');
        $b = $this->bookingDiajukan($a, 'EK-B14');
        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/setujui")->assertSessionHasNoErrors();

        try {
            $this->app->make(LayananAnggota::class)->ubah('AG-B14', ['nama' => $a->name, 'kontak' => $a->contact, 'status' => 'Nonaktif'], $this->admin);
            $this->fail('F-01: penonaktifan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame([LayananAnggota::PESAN_F01], $e->errors()['status']);
        }
        $this->assertSame(StatusAnggota::Aktif, $a->fresh()->status);
    }

    // ---------------------------------------------------------------- 62–64 data sensitif & audit gagal

    public function test_respons_tanpa_data_sensitif_dan_operasi_gagal_tanpa_audit(): void
    {
        $a = $this->anggotaDenganAkun('AG-B15');
        $this->eksemplarBaru('EK-B15');
        $b = $this->bookingDiajukan($a, 'EK-B15');
        Booking::updating(fn () => throw new RuntimeException('Simulasi kegagalan.'));
        try {
            $this->app->make(LayananBooking::class)->setujui($b->booking_code, $this->admin);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
        } finally {
            Booking::flushEventListeners();
        }
        $this->assertSame(['booking.diajukan'], $this->aksiAudit());
        $this->assertSame('Diajukan', $b->fresh()->status->value);

        $this->sebagaiAdmin();
        $json = json_encode($this->props('/admin/booking/verifikasi')['data']['booking']);
        foreach ([$a->user->password, 'remember_token', '"member_id"', '"staff_id"', 'reserved_copy_id'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json);
        }
    }
}
