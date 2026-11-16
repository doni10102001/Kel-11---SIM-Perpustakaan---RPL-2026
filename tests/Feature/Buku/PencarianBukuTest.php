<?php

namespace Tests\Feature\Buku;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\User;
use App\Queries\DataPerpustakaan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Database\MySqlTestCase;

/**
 * Phase 9B — pencarian buku di MySQL + pagination untuk Data Buku (Admin)
 * dan Cari Buku (Anggota). Semua di sim_perpustakaan_test (di-rollback).
 */
class PencarianBukuTest extends MySqlTestCase
{
    private User $admin;

    private User $akunAnggota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->buatAdmin();
        $this->akunAnggota = $this->buatAkunAnggota();
        $this->buatAnggota(['name' => 'Anggota Rahasia Uji', 'contact' => '0899-RAHASIA'], $this->akunAnggota);
    }

    private function buku(string $kode, string $judul, string $kategori, array $statusEksemplar = []): Book
    {
        $b = Book::factory()->create(['book_code' => $kode, 'title' => $judul, 'category' => $kategori, 'author' => 'Penulis Tidak Dicari']);
        foreach (array_values($statusEksemplar) as $i => $s) {
            BookCopy::factory()->for($b)->create(['copy_code' => "{$kode}-E".($i + 1), 'status' => $s]);
        }

        return $b;
    }

    /** @return array<string, mixed> props halaman */
    private function props(string $url, ?User $sebagai = null): array
    {
        $this->flushSession();
        $this->actingAs($sebagai ?? $this->admin);
        $props = [];
        $this->get($url)->assertOk()->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    private function kode(array $props): array
    {
        return array_column($props['data']['buku'], 'kodeBuku');
    }

    private function contohKatalog(): void
    {
        $this->buku('BK-101', 'Algoritma Dasar', 'Informatika');
        $this->buku('BK-102', 'Kalkulus Lanjut', 'Matematika');
        $this->buku('BK-103', 'Struktur Data', 'Informatika');
        $this->buku('ALGO-9', 'Sejarah Nusantara', 'Humaniora');
        $this->buku('BK-104', 'Diskon 50% Ilmu', 'Ekonomi');
        $this->buku('BK-105', 'Kata_Bergaris Bawah', 'Bahasa');
    }

    // ---------------------------------------------------------------- kode, judul, kategori

    public function test_cari_berdasarkan_kode_judul_dan_kategori_tanpa_membedakan_huruf(): void
    {
        $this->contohKatalog();

        foreach (['/admin/buku' => $this->admin, '/anggota/cari-buku' => $this->akunAnggota] as $url => $akun) {
            $this->assertSame(['BK-102'], $this->kode($this->props("{$url}?q=bk-102", $akun)), "{$url}: kode");
            $this->assertSame(['BK-101'], $this->kode($this->props("{$url}?q=ALGORITMA", $akun)), "{$url}: judul");
            $this->assertSame(['BK-101', 'BK-103'], $this->kode($this->props("{$url}?q=informatika", $akun)), "{$url}: kategori");
            // "algo" cocok dengan kode ALGO-9 dan judul Algoritma; hasil urut Kode Buku.
            $this->assertSame(['ALGO-9', 'BK-101'], $this->kode($this->props("{$url}?q=algo", $akun)), "{$url}: gabungan");
            // Penulis tidak termasuk kolom pencarian (KEPUTUSAN_DESAIN 3.10).
            $this->assertSame([], $this->kode($this->props("{$url}?q=Penulis+Tidak", $akun)), "{$url}: penulis");
            // Spasi di tepi dirapikan.
            $this->assertSame(['BK-103'], $this->kode($this->props("{$url}?q=%20%20struktur%20", $akun)), "{$url}: trim");
        }
    }

    public function test_pencarian_dijalankan_mysql_dengan_parameter_dan_wildcard_di_escape(): void
    {
        $this->contohKatalog();

        $this->flushSession();
        $this->actingAs($this->admin);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/admin/buku?q=Kalkulus')->assertOk();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $cari = collect($log)->first(fn ($q) => str_contains($q['query'], 'from `books`') && str_contains($q['query'], 'like'));
        $this->assertNotNull($cari, 'Pencarian dijalankan sebagai query SQL.');
        $this->assertStringContainsString('`book_code` like ? or `title` like ? or `category` like ?', $cari['query']);
        $this->assertStringNotContainsString('Kalkulus', $cari['query'], 'Kata kunci dikirim sebagai binding, bukan disisipkan ke SQL.');
        $this->assertContains('%Kalkulus%', $cari['bindings']);

        // % dan _ diperlakukan sebagai huruf biasa, bukan wildcard.
        $this->assertSame(['BK-104'], $this->kode($this->props('/admin/buku?q=50%25')));
        $this->assertSame(['BK-105'], $this->kode($this->props('/admin/buku?q=_')));
        // Teks berbahaya hanya menjadi kata kunci biasa.
        $props = $this->props('/admin/buku?q='.urlencode("' OR 1=1 -- "));
        $this->assertSame([], $this->kode($props));
        $this->assertSame(6, Book::count(), 'Tidak ada data yang berubah.');
    }

    public function test_filter_kategori_persis_dan_gabungan_dengan_kata_kunci(): void
    {
        $this->contohKatalog();

        $props = $this->props('/anggota/cari-buku?kategori=Informatika', $this->akunAnggota);
        $this->assertSame(['BK-101', 'BK-103'], $this->kode($props));
        $this->assertSame(['Bahasa', 'Ekonomi', 'Humaniora', 'Informatika', 'Matematika'], $props['data']['kategori'], 'Pilihan kategori = semua kategori.');
        $this->assertSame(['BK-103'], $this->kode($this->props('/anggota/cari-buku?q=data&kategori=Informatika', $this->akunAnggota)));
        $this->assertSame([], $this->kode($this->props('/anggota/cari-buku?kategori=Informat', $this->akunAnggota)), 'Kategori dicocokkan persis.');
    }

    // ---------------------------------------------------------------- pagination & meta

    public function test_pagination_20_per_halaman_dan_meta_mempertahankan_filter(): void
    {
        for ($i = 1; $i <= 45; $i++) {
            $this->buku(sprintf('BK-%03d', $i), "Judul Uji {$i}", $i % 3 === 0 ? 'Sains' : 'Umum');
        }

        $h1 = $this->props('/admin/buku');
        $this->assertCount(DataPerpustakaan::BUKU_PER_HALAMAN, $h1['data']['buku']);
        $this->assertSame('BK-001', $h1['data']['buku'][0]['kodeBuku']);
        $this->assertSame(['q' => '', 'kategori' => '', 'halaman' => 1, 'perHalaman' => 20, 'halamanTerakhir' => 3, 'total' => 45, 'totalSemua' => 45], $h1['pencarianBuku']);

        $h3 = $this->props('/admin/buku?page=3');
        $this->assertSame(['BK-041', 'BK-042', 'BK-043', 'BK-044', 'BK-045'], $this->kode($h3));
        $this->assertSame(3, $h3['pencarianBuku']['halaman']);

        // Di luar rentang → halaman terakhir; nilai tidak valid → halaman 1.
        $this->assertSame(3, $this->props('/admin/buku?page=99')['pencarianBuku']['halaman']);
        foreach (['abc', '0', '-2', '1.5'] as $tidakValid) {
            $this->assertSame(1, $this->props("/admin/buku?page={$tidakValid}")['pencarianBuku']['halaman'], $tidakValid);
        }

        // Filter tetap berlaku saat berpindah halaman; meta membawa filter untuk tautan pagination.
        $sains = $this->props('/anggota/cari-buku?q=judul&kategori=Sains&page=1', $this->akunAnggota);
        $this->assertSame(['q' => 'judul', 'kategori' => 'Sains', 'halaman' => 1, 'perHalaman' => 20, 'halamanTerakhir' => 1, 'total' => 15, 'totalSemua' => 45], $sains['pencarianBuku']);
        $umum2 = $this->props('/admin/buku?q=judul+uji&kategori=Umum&page=2');
        $this->assertSame(['q' => 'judul uji', 'kategori' => 'Umum', 'halaman' => 2, 'perHalaman' => 20, 'halamanTerakhir' => 2, 'total' => 30, 'totalSemua' => 45], $umum2['pencarianBuku']);
        $this->assertCount(10, $umum2['data']['buku']);
    }

    public function test_eksemplar_lengkap_untuk_buku_di_halaman_sehingga_ketersediaan_akurat(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            $this->buku(sprintf('BK-%03d', $i), "Judul {$i}", 'Umum', ['Tersedia']);
        }
        $this->buku('BK-022', 'Banyak Eksemplar', 'Umum', ['Tersedia', 'Tersedia', 'Dipesan', 'Dipinjam', 'Dipinjam']);

        $hitung = fn (array $props, string $kode) => ['total' => count($m = array_filter($props['data']['eksemplar'], fn ($e) => $e['kodeBuku'] === $kode))] + array_count_values(array_column($m, 'status'));

        $h1 = $this->props('/admin/buku');
        $this->assertCount(20, $h1['data']['eksemplar'], 'Hanya eksemplar milik buku di halaman ini.');
        $this->assertSame([], array_diff(array_column($h1['data']['eksemplar'], 'kodeBuku'), $this->kode($h1)));

        $h2 = $this->props('/admin/buku?page=2');
        $this->assertSame(['BK-021', 'BK-022'], $this->kode($h2));
        $this->assertSame(['total' => 5, 'Tersedia' => 2, 'Dipesan' => 1, 'Dipinjam' => 2], $hitung($h2, 'BK-022'));

        $katalog = $this->props('/anggota/cari-buku?q=banyak', $this->akunAnggota);
        $this->assertSame(['total' => 5, 'Tersedia' => 2, 'Dipesan' => 1, 'Dipinjam' => 2], $hitung($katalog, 'BK-022'));
    }

    // ---------------------------------------------------------------- integrasi tulis → cari

    public function test_buku_baru_langsung_muncul_di_hasil_pencarian(): void
    {
        $this->contohKatalog();
        $this->actingAs($this->admin)->post('/admin/buku', [
            'kodeBuku' => 'BK-777', 'judul' => 'Kriptografi Modern', 'penulis' => 'P', 'penerbit' => 'Q',
            'tahunTerbit' => '2024', 'kategori' => 'Keamanan',
            'eksemplar' => [['kodeEksemplar' => 'EK-7771'], ['kodeEksemplar' => 'EK-7772']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['BK-777'], $this->kode($this->props('/admin/buku?q=kripto')));
        $katalog = $this->props('/anggota/cari-buku?q=keamanan', $this->akunAnggota);
        $this->assertSame(['BK-777'], $this->kode($katalog));
        $this->assertSame(['EK-7771', 'EK-7772'], array_column($katalog['data']['eksemplar'], 'kodeEksemplar'));
        $this->assertContains('Keamanan', $katalog['data']['kategori']);
    }

    // ---------------------------------------------------------------- privasi & ketahanan

    public function test_katalog_anggota_tanpa_data_pribadi_dan_parameter_aneh_diabaikan(): void
    {
        $b = $this->buku('BK-201', 'Buku Dipinjam', 'Umum', ['Dipinjam']);
        $this->buatPeminjaman($this->akunAnggota->member, $b->copies()->sole(), $this->admin);

        foreach (['/anggota/cari-buku?q[]=x&kategori[]=y&page[]=2', '/anggota/cari-buku?q='.str_repeat('a', 500), '/anggota/cari-buku?kodeAnggota=AG-001&member_id=1'] as $url) {
            $props = $this->props($url, $this->akunAnggota);
            $this->assertSame(['buku', 'eksemplar', 'kategori'], array_keys($props['data']), $url);
            $this->assertArrayNotHasKey('pengguna', $props['data']);
            $json = json_encode($props['data']);
            foreach (['Anggota Rahasia Uji', '0899-RAHASIA', $this->admin->username, '"member', 'staff', 'password'] as $rahasia) {
                $this->assertStringNotContainsString($rahasia, $json, "{$url}: {$rahasia}");
            }
            foreach ($props['data']['eksemplar'] as $e) {
                $this->assertSame(['kodeEksemplar', 'kodeBuku', 'status'], array_keys($e));
            }
        }

        $panjang = $this->props('/anggota/cari-buku?q='.str_repeat('a', 500), $this->akunAnggota);
        $this->assertSame(100, mb_strlen($panjang['pencarianBuku']['q']), 'Kata kunci dibatasi 100 karakter.');
        $this->assertSame('', $this->props('/anggota/cari-buku?q[]=x', $this->akunAnggota)['pencarianBuku']['q']);
    }

    public function test_tamu_dan_peran_lain_tidak_dapat_membuka_pencarian(): void
    {
        $this->get('/admin/buku?q=x')->assertRedirect(route('login'));
        $this->get('/anggota/cari-buku?q=x')->assertRedirect(route('login'));

        $this->actingAs($this->akunAnggota)->get('/admin/buku?q=x')->assertForbidden();
        $this->flushSession();
        $this->actingAs($this->admin)->get('/anggota/cari-buku?q=x')->assertForbidden();
    }

    public function test_jumlah_query_pencarian_tidak_bertambah_seiring_data(): void
    {
        $hitung = function (string $url, User $akun): int {
            $this->flushSession();
            $this->actingAs($akun);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        // 21 buku: halaman 2 sudah ada sejak awal (tanpa jalur "halaman terakhir").
        for ($i = 1; $i <= 21; $i++) {
            $this->buku(sprintf('BK-%03d', $i), "Judul {$i}", 'Umum', ['Tersedia']);
        }
        $url = ['/admin/buku?q=judul', '/admin/buku?q=judul&page=2', '/anggota/cari-buku?q=judul&kategori=Umum'];
        $awal = [$hitung($url[0], $this->admin), $hitung($url[1], $this->admin), $hitung($url[2], $this->akunAnggota)];

        for ($i = 22; $i <= 60; $i++) {
            $this->buku(sprintf('BK-%03d', $i), "Judul {$i}", 'Umum', ['Tersedia', 'Dipesan', 'Dipinjam']);
        }
        $akhir = [$hitung($url[0], $this->admin), $hitung($url[1], $this->admin), $hitung($url[2], $this->akunAnggota)];

        $this->assertSame($awal, $akhir, 'Jumlah query tetap (tanpa N+1) walau buku & eksemplar bertambah.');
    }
}
