<?php

namespace Tests\Feature\Transaksi;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Loan;
use App\Services\Transaksi\LayananKedaluwarsaBooking;
use App\Support\PemeriksaInvarianData;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Batch 2 — kedaluwarsa booking 48 jam (butir uji 41–50). Jam dibekukan
 * (travelTo), tanpa sleep.
 */
class KedaluwarsaBookingTest extends TransaksiTestCase
{
    private const DISETUJUI = '2026-11-16 08:00:00';

    /** Booking Disetujui pada 16 Nov 08.00 UTC → batas 18 Nov 08.00 UTC. */
    private function bookingDisetujui(string $kodeEksemplar = 'EK-K01', ?string $kodeBooking = null): Booking
    {
        $this->travelTo(CarbonImmutable::parse(self::DISETUJUI, 'UTC'));
        $anggota = $this->anggotaDenganAkun('AG-'.substr($kodeEksemplar, 3));
        $e = $this->eksemplarBaru($kodeEksemplar);
        $this->sebagai($anggota)->post('/anggota/booking', ['kodeEksemplar' => $kodeEksemplar])->assertSessionHasNoErrors();
        $b = Booking::where('copy_id', $e->id)->latest('id')->firstOrFail();
        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/setujui")->assertSessionHasNoErrors();

        return $b->fresh();
    }

    private function jalankan(string $pada): array
    {
        $this->travelTo(CarbonImmutable::parse($pada, 'UTC'));

        return $this->app->make(LayananKedaluwarsaBooking::class)->prosesJatuhTempo();
    }

    // ---------------------------------------------------------------- 41, 42, 43, 49

    public function test_tepat_pada_batas_kedaluwarsa_dan_sesaat_sebelumnya_tetap_disetujui(): void
    {
        $b = $this->bookingDisetujui();
        $this->assertSame('2026-11-18 08:00:00.000000', $b->getRawOriginal('expires_at'));

        $this->assertSame(['diproses' => 0, 'dilewati' => 0, 'gagal' => 0], $this->jalankan('2026-11-18 07:59:59.999999'));
        $this->assertSame(['Disetujui', 'Dipesan'], [$b->fresh()->status->value, $b->copy->fresh()->status->value]);

        $this->assertSame(['diproses' => 1, 'dilewati' => 0, 'gagal' => 0], $this->jalankan('2026-11-18 08:00:00'));
        $b->refresh();
        $this->assertSame('Kedaluwarsa', $b->status->value);
        $this->assertSame('2026-11-18 08:00:00.000000', $b->getRawOriginal('expired_at'), 'expired_at = waktu pemrosesan, UTC.');
        $this->assertSame('2026-11-16 08:00:00.000000', $b->getRawOriginal('decided_at'), 'Riwayat persetujuan dipertahankan.');
        $this->assertSame('Tersedia', $b->copy->fresh()->status->value, 'Reservasi dilepas.');
        $this->assertNull(Booking::whereKey($b->id)->value('reserved_copy_id'));

        $audit = AuditLog::where('action', 'booking.kedaluwarsa')->sole();
        $this->assertNull($audit->actor_id, 'Kejadian sistem: tanpa aktor (tidak menyamar sebagai Admin).');
        $this->assertEqualsCanonicalizing(['kode_booking' => $b->booking_code, 'kode_eksemplar' => 'EK-K01', 'batas_pengambilan' => '2026-11-18T08:00:00Z', 'eksemplar_dilepas' => true, 'pemicu' => 'penjadwal'], $audit->metadata);
        $this->assertSame([], (new PemeriksaInvarianData)->periksa());
    }

    public function test_penjadwal_tertunda_menyimpan_waktu_pemrosesan_aktual(): void
    {
        $b = $this->bookingDisetujui();
        $this->jalankan('2026-11-18 08:04:37.120000');
        $this->assertSame('2026-11-18 08:04:37.120000', $b->fresh()->getRawOriginal('expired_at'));
        $this->assertSame('2026-11-18 08:00:00.000000', $b->fresh()->getRawOriginal('expires_at'), 'Batas bisnis tetap 48 jam (bukan + jeda penjadwal).');
    }

    public function test_expired_at_memakai_waktu_pemrosesan_tiap_item_bukan_awal_eksekusi(): void
    {
        $b1 = $this->bookingDisetujui('EK-K31');
        $b2 = $this->bookingDisetujui('EK-K32');
        // Jam maju 7 detik setiap kali satu booking selesai diproses (meniru batch yang berjalan lama).
        Booking::updated(fn () => $this->travel(7)->seconds());
        try {
            $this->jalankan('2026-11-18 08:00:00');
        } finally {
            Booking::flushEventListeners();
        }

        $this->assertSame('2026-11-18 08:00:00.000000', $b1->fresh()->getRawOriginal('expired_at'));
        $this->assertSame('2026-11-18 08:00:07.000000', $b2->fresh()->getRawOriginal('expired_at'), 'Item kedua memakai waktunya sendiri.');
    }

    public function test_eksemplar_milik_transaksi_lain_tidak_dilepas(): void
    {
        $b = $this->bookingDisetujui();
        // Keadaan tidak konsisten yang disengaja: eksemplar kini dipinjam transaksi lain.
        $b->copy->forceFill(['status' => 'Dipinjam'])->save();

        $this->jalankan('2026-11-18 09:00:00');
        $this->assertSame(['Kedaluwarsa', 'Dipinjam'], [$b->fresh()->status->value, $b->copy->fresh()->status->value]);
        $this->assertFalse(AuditLog::where('action', 'booking.kedaluwarsa')->sole()->metadata['eksemplar_dilepas']);
    }

    // ---------------------------------------------------------------- 44, 45 idempoten

    public function test_kedaluwarsa_idempoten_tanpa_audit_ganda(): void
    {
        $b = $this->bookingDisetujui();
        $this->assertSame(1, $this->jalankan('2026-11-18 08:05:00')['diproses']);
        $this->assertSame(['diproses' => 0, 'dilewati' => 0, 'gagal' => 0], $this->jalankan('2026-11-18 08:10:00'));
        $this->assertFalse($this->app->make(LayananKedaluwarsaBooking::class)->kedaluwarsakan($b->id), 'Pemrosesan langsung kedua = no-op.');

        $this->assertSame(1, AuditLog::where('action', 'booking.kedaluwarsa')->count());
        $this->assertSame('2026-11-18 08:05:00.000000', $b->fresh()->getRawOriginal('expired_at'), 'Waktu pertama tidak ditimpa.');
    }

    // ---------------------------------------------------------------- 46, 47 batch & kegagalan

    public function test_batch_memproses_semua_dan_kegagalan_satu_tidak_menghentikan_lainnya(): void
    {
        $booking = [];
        foreach (range(1, 7) as $i) {
            $booking[] = $this->bookingDisetujui(sprintf('EK-K%02d', $i + 10));
        }
        $gagal = $booking[2]->id;
        Booking::updating(function (Booking $b) use ($gagal) {
            if ($b->id === $gagal) {
                throw new RuntimeException('Simulasi kegagalan satu booking.');
            }
        });
        Log::spy();

        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00', 'UTC'));
        try {
            $hasil = $this->app->make(LayananKedaluwarsaBooking::class)->prosesJatuhTempo(3);
        } finally {
            Booking::flushEventListeners();
        }

        $this->assertSame(['diproses' => 6, 'dilewati' => 0, 'gagal' => 1], $hasil);
        $this->assertSame('Disetujui', Booking::find($gagal)->status->value, 'Booking gagal tidak berubah (rollback per item).');
        $this->assertSame('Dipesan', Booking::find($gagal)->copy->status->value);
        $this->assertSame(6, Booking::where('status', 'Kedaluwarsa')->count());
        $this->assertSame(6, AuditLog::where('action', 'booking.kedaluwarsa')->count());
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($pesan, $konteks) => $konteks === ['booking_id' => $gagal, 'galat' => RuntimeException::class, 'kode' => null]);

        // Dijalankan ulang (mis. setelah perbaikan): sisa booking diproses, yang sudah selesai tidak diulang.
        $this->assertSame(['diproses' => 1, 'dilewati' => 0, 'gagal' => 0], $this->jalankan('2026-11-18 08:05:00'));
        $this->assertSame(7, AuditLog::where('action', 'booking.kedaluwarsa')->count());
    }

    public function test_perintah_artisan_dan_jadwal_tiap_lima_menit(): void
    {
        $b = $this->bookingDisetujui();
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00', 'UTC'));
        $this->assertSame(0, Artisan::call('bookings:expire', ['--batch' => 50]));
        $this->assertStringContainsString('diproses 1, dilewati 0, gagal 0', Artisan::output());
        $this->assertSame('Kedaluwarsa', $b->fresh()->status->value);

        $event = collect($this->app->make(Schedule::class)->events())->filter(fn ($e) => str_contains($e->command ?? '', 'bookings:expire'));
        $this->assertCount(1, $event, 'Tepat satu jadwal (tanpa duplikasi).');
        $this->assertSame('*/5 * * * *', $event->first()->expression);
        $this->assertTrue($event->first()->withoutOverlapping, 'Dilindungi dari tumpang tindih.');
    }

    // ---------------------------------------------------------------- 48 penjadwal tertunda

    public function test_realisasi_setelah_batas_ditolak_dan_kedaluwarsa_tetap_tercommit(): void
    {
        $b = $this->bookingDisetujui();
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00', 'UTC')); // penjadwal belum berjalan

        $this->sebagaiAdmin()->post("/admin/booking/{$b->booking_code}/realisasi", ['tanggalJatuhTempo' => '2026-11-25'])
            ->assertSessionHasErrors(['umum' => LayananKedaluwarsaBooking::PESAN_KEDALUWARSA.' Booking tidak dapat direalisasikan.']);

        $b->refresh();
        $this->assertSame(['Kedaluwarsa', '2026-11-18 08:00:00.000000'], [$b->status->value, $b->getRawOriginal('expired_at')], 'Transisi kedaluwarsa di-commit walau realisasi ditolak.');
        $this->assertSame('Tersedia', $b->copy->fresh()->status->value);
        $this->assertSame(0, Loan::count());
        $this->assertSame('realisasi', AuditLog::where('action', 'booking.kedaluwarsa')->sole()->metadata['pemicu']);
        $this->assertSame(0, AuditLog::where('action', 'booking.direalisasikan')->count());

        // Sesaat sebelum batas: realisasi masih boleh.
        $c = $this->bookingDisetujui('EK-K02');
        $this->travelTo(CarbonImmutable::parse('2026-11-18 07:59:59', 'UTC'));
        $this->sebagaiAdmin()->post("/admin/booking/{$c->booking_code}/realisasi", ['tanggalJatuhTempo' => '2026-11-25'])->assertSessionHasNoErrors();
        $this->assertSame('Direalisasikan', $c->fresh()->status->value);
    }

    public function test_pembatalan_setelah_batas_menjadi_kedaluwarsa_bukan_dibatalkan(): void
    {
        $b = $this->bookingDisetujui();
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:01:00', 'UTC'));

        $this->sebagai($b->member)->post("/anggota/booking/{$b->booking_code}/batalkan")
            ->assertSessionHasErrors(['umum' => LayananKedaluwarsaBooking::PESAN_KEDALUWARSA]);
        $this->assertSame(['Kedaluwarsa', 'Tersedia'], [$b->fresh()->status->value, $b->copy->fresh()->status->value]);
        $this->assertSame(0, AuditLog::where('action', 'booking.dibatalkan')->count());
        $this->assertSame('pembatalan', AuditLog::where('action', 'booking.kedaluwarsa')->sole()->metadata['pemicu']);
        // Kedaluwarsa tidak dapat dibatalkan lagi.
        $this->sebagai($b->member)->post("/anggota/booking/{$b->booking_code}/batalkan")
            ->assertSessionHasErrors(['umum' => "Booking {$b->booking_code} berstatus Kedaluwarsa dan tidak dapat dibatalkan."]);
    }

    public function test_reservasi_lewat_batas_direkonsiliasi_sebelum_persetujuan_baru_dan_penonaktifan(): void
    {
        $b = $this->bookingDisetujui();
        $anggotaLain = $this->anggotaDenganAkun('AG-K99');
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00', 'UTC'));
        // Booking baru untuk eksemplar yang sama: rekonsiliasi melepas reservasi lama yang lewat batas.
        $this->sebagai($anggotaLain)->post('/anggota/booking', ['kodeEksemplar' => 'EK-K01'])->assertSessionHasNoErrors();
        $baru = Booking::where('member_id', $anggotaLain->id)->sole();
        $this->assertSame('Kedaluwarsa', $b->fresh()->status->value);
        $this->sebagaiAdmin()->post("/admin/booking/{$baru->booking_code}/setujui")->assertSessionHasNoErrors();
        $this->assertSame(['Disetujui', 'Dipesan'], [$baru->fresh()->status->value, $b->copy->fresh()->status->value]);
        $this->assertSame('2026-11-20 08:00:00.000000', $baru->fresh()->getRawOriginal('expires_at'));

        // F-01: booking lewat batas (belum diproses) tidak lagi menghalangi penonaktifan.
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC'));
        $this->sebagaiAdmin()->put('/admin/anggota/AG-K99', ['nama' => $anggotaLain->name, 'kontak' => $anggotaLain->contact, 'status' => 'Nonaktif'])->assertSessionHasNoErrors();
        $this->assertSame('Kedaluwarsa', $baru->fresh()->status->value);
        $this->assertSame('penonaktifan', AuditLog::where('action', 'booking.kedaluwarsa')->orderByDesc('id')->first()->metadata['pemicu']);
    }

    // ---------------------------------------------------------------- 50 constraint & backfill

    public function test_constraint_konsistensi_kedaluwarsa_ditegakkan_mysql(): void
    {
        $b = $this->bookingDisetujui();
        $id = $b->id;
        $tabel = fn () => DB::table('bookings')->where('id', $id);

        $this->assertDitolakDb(3819, fn () => $tabel()->update(['expires_at' => null]), 'Disetujui wajib punya expires_at.');
        $this->assertDitolakDb(3819, fn () => $tabel()->update(['status' => 'Kedaluwarsa']), 'Kedaluwarsa wajib punya expired_at.');
        $this->assertDitolakDb(3819, fn () => $tabel()->update(['expired_at' => '2026-11-19 00:00:00']), 'expired_at hanya untuk Kedaluwarsa.');
        $this->assertDitolakDb(3819, fn () => $tabel()->update(['status' => 'Kedaluwarsa', 'expired_at' => '2026-11-17 00:00:00']), 'expired_at tidak boleh sebelum batas.');
        $this->assertDitolakDb(3819, fn () => $tabel()->update(['expires_at' => '2026-11-15 00:00:00']), 'Batas harus setelah keputusan.');
        $this->assertDitolakDb(3819, fn () => $tabel()->update(['status' => 'Kadaluarsa']), 'Kosakata status dijaga CHECK.');
        $this->assertSame('Disetujui', $b->fresh()->status->value);
    }
}
