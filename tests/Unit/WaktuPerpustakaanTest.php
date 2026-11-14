<?php

namespace Tests\Unit;

use App\Support\WaktuPerpustakaan;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 7A D-02 — penyimpanan UTC, kalender/tampilan WIB. Tanpa database.
 */
class WaktuPerpustakaanTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function bekukanWaktu(string $iso): void
    {
        Carbon::setTestNow($iso);
        CarbonImmutable::setTestNow($iso);
    }

    public function test_konfigurasi_zona_waktu_sesuai_keputusan_d02(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
        $this->assertSame('Asia/Jakarta', config('perpustakaan.zona_waktu_tampilan'));
        $this->assertSame('+00:00', config('database.connections.mysql.timezone'));
        $this->assertSame('+00:00', config('database.connections.mysql_testing.timezone'));
    }

    public function test_hari_ini_mengikuti_kalender_wib_bukan_utc(): void
    {
        // 14 Nov 05:30 WIB = 13 Nov 22:30 UTC.
        $this->bekukanWaktu('2026-11-13T22:30:00Z');

        $this->assertSame('2026-11-14', WaktuPerpustakaan::hariIni());
        $this->assertSame('2026-11-13', today()->format('Y-m-d'), 'today() Laravel = UTC; jangan dipakai untuk kalender bisnis.');
        $this->assertSame('2026-11-13 22:30:00', WaktuPerpustakaan::sekarangUtc()->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', WaktuPerpustakaan::sekarangUtc()->getTimezone()->getName());
    }

    public function test_hari_ini_sama_dengan_utc_setelah_pukul_tujuh_wib(): void
    {
        $this->bekukanWaktu('2026-11-14T00:00:00Z'); // 07:00 WIB

        $this->assertSame('2026-11-14', WaktuPerpustakaan::hariIni());
    }

    public function test_batas_hari_wib_terakhir_sebelum_tengah_malam(): void
    {
        $this->bekukanWaktu('2026-11-14T16:59:59Z'); // 23:59:59 WIB tanggal 14
        $this->assertSame('2026-11-14', WaktuPerpustakaan::hariIni());

        $this->bekukanWaktu('2026-11-14T17:00:00Z'); // 00:00 WIB tanggal 15
        $this->assertSame('2026-11-15', WaktuPerpustakaan::hariIni());
    }

    public function test_rentang_filter_laporan_wib_dikonversi_ke_utc_setengah_terbuka(): void
    {
        [$mulai, $sebelum] = WaktuPerpustakaan::rentangHariUtc('2026-11-01', '2026-11-30');

        $this->assertSame('2026-10-31 17:00:00.000000', $mulai->format('Y-m-d H:i:s.u'));
        $this->assertSame('2026-11-30 17:00:00.000000', $sebelum->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $mulai->getTimezone()->getName());
        $this->assertSame('UTC', $sebelum->getTimezone()->getName());

        [$m1, $s1] = WaktuPerpustakaan::rentangHariUtc('2026-11-07', '2026-11-07');
        $this->assertSame(24 * 3600, $s1->getTimestamp() - $m1->getTimestamp(), 'Satu hari WIB = 24 jam (tanpa DST).');
    }

    public function test_kejadian_di_tepi_hari_masuk_ke_hari_wib_yang_benar(): void
    {
        [$mulai, $sebelum] = WaktuPerpustakaan::rentangHariUtc('2026-11-02', '2026-11-02');
        $masuk = fn (string $isoUtc) => ($t = CarbonImmutable::parse($isoUtc))->greaterThanOrEqualTo($mulai) && $t->lessThan($sebelum);

        $this->assertTrue($masuk('2026-11-01T17:00:00Z'), '00:00 WIB tanggal 2 termasuk.');
        $this->assertTrue($masuk('2026-11-01T22:15:00Z'), '05:15 WIB tanggal 2 termasuk (DATE() UTC akan memberi tanggal 1).');
        $this->assertTrue($masuk('2026-11-02T16:59:59.999999Z'), '23:59:59.999999 WIB tanggal 2 termasuk.');
        $this->assertFalse($masuk('2026-11-02T17:00:00Z'), '00:00 WIB tanggal 3 tidak termasuk.');
        $this->assertFalse($masuk('2026-11-01T16:59:59Z'), '23:59:59 WIB tanggal 1 tidak termasuk.');
    }

    public function test_rentang_ditolak_bila_terbalik_atau_tanggal_tidak_valid(): void
    {
        $this->assertGagal(fn () => WaktuPerpustakaan::rentangHariUtc('2026-11-30', '2026-11-01'));
        $this->assertGagal(fn () => WaktuPerpustakaan::rentangHariUtc('2026-02-30', '2026-03-01'));
        $this->assertGagal(fn () => WaktuPerpustakaan::rentangHariUtc('07/11/2026', '2026-11-08'));
        $this->assertGagal(fn () => WaktuPerpustakaan::instantDariTanggalLokal('2026-11-02', '24:00'));
    }

    public function test_tanggal_mock_tanpa_jam_menjadi_pukul_12_wib_dalam_utc(): void
    {
        $instant = WaktuPerpustakaan::instantDariTanggalLokal('2026-11-02');

        $this->assertSame('2026-11-02 05:00:00', $instant->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $instant->getTimezone()->getName());
        $this->assertSame('2026-11-02', WaktuPerpustakaan::tanggalLokal($instant));
    }

    public function test_tanggal_lokal_dari_instant(): void
    {
        $this->assertSame('2026-11-17', WaktuPerpustakaan::tanggalLokal(CarbonImmutable::parse('2026-11-16T17:30:00Z')));
        $this->assertSame('2026-11-16', WaktuPerpustakaan::tanggalLokal(CarbonImmutable::parse('2026-11-16T16:59:59Z')));
        $this->assertSame('2026-11-16', WaktuPerpustakaan::tanggalLokal(CarbonImmutable::parse('2026-11-16 23:30', 'Asia/Jakarta')));
    }

    private function assertGagal(callable $aksi): void
    {
        try {
            $aksi();
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Seharusnya InvalidArgumentException.');
    }
}
