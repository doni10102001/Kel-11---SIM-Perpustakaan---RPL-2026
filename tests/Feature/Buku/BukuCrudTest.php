<?php

namespace Tests\Feature\Buku;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Booking;
use App\Models\Loan;
use App\Models\User;
use App\Services\LayananBuku;
use App\Support\WaktuPerpustakaan;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Feature\Database\MySqlTestCase;

/**
 * Phase 9B (Batch 1) — CRUD Buku & eksemplar nyata di MySQL. Semua di
 * sim_perpustakaan_test (transaksi di-rollback); database development tidak
 * disentuh. Test balapan memakai koneksi MySQL KEDUA yang benar-benar commit,
 * lalu membersihkan barisnya sendiri.
 */
class BukuCrudTest extends MySqlTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->buatAdmin();
    }

    private function isian(array $timpa = []): array
    {
        // $timpa di KIRI: pada operator + PHP kunci kiri yang menang.
        return $timpa + [
            'kodeBuku' => 'BK-901',
            'judul' => 'Rekayasa Perangkat Lunak Terapan',
            'penulis' => 'Penulis Uji',
            'penerbit' => 'Penerbit Uji',
            'tahunTerbit' => '2024',
            'kategori' => 'Informatika',
            'eksemplar' => [['kodeEksemplar' => 'EK-901', 'status' => 'Tersedia']],
        ];
    }

    private function eksemplar(string ...$kode): array
    {
        return array_map(fn ($k) => ['kodeEksemplar' => $k, 'status' => 'Tersedia'], $kode);
    }

    private function tambah(array $isian)
    {
        return $this->actingAs($this->admin)->from('/admin/buku/tambah')->post('/admin/buku', $isian);
    }

    private function ubah(string $kode, array $isian)
    {
        return $this->actingAs($this->admin)->from("/admin/buku/{$kode}/ubah")->put("/admin/buku/{$kode}", $isian);
    }

    private function aksiAudit(): array
    {
        return AuditLog::query()->orderBy('id')->pluck('action')->all();
    }

    private function bukuDenganEksemplar(string $kodeBuku, array $status): Book
    {
        $buku = Book::factory()->create(['book_code' => $kodeBuku, 'title' => "Judul {$kodeBuku}", 'category' => 'Sains']);
        foreach (array_values($status) as $i => $s) {
            BookCopy::factory()->for($buku)->create(['copy_code' => "{$kodeBuku}-E".($i + 1), 'status' => $s]);
        }

        return $buku;
    }

    /** @return array<string, mixed> */
    private function props(string $url): array
    {
        $props = [];
        $this->get($url)->assertOk()->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    // ---------------------------------------------------------------- 1, 8, 11 tambah

    public function test_admin_menambah_buku_beserta_eksemplar_awal_secara_atomik(): void
    {
        $this->tambah($this->isian(['eksemplar' => $this->eksemplar('EK-901', 'EK-902', 'EK-903')]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/buku')
            ->assertSessionHas('status', 'Buku BK-901 · Rekayasa Perangkat Lunak Terapan berhasil ditambahkan dengan 3 eksemplar.');

        $buku = Book::cariKode('BK-901');
        $this->assertSame(
            ['Rekayasa Perangkat Lunak Terapan', 'Penulis Uji', 'Penerbit Uji', 2024, 'Informatika'],
            [$buku->title, $buku->author, $buku->publisher, $buku->publication_year, $buku->category],
        );
        $eksemplar = $buku->copies()->orderBy('copy_code')->get();
        $this->assertSame(['EK-901', 'EK-902', 'EK-903'], $eksemplar->pluck('copy_code')->all());
        $this->assertSame(['Tersedia'], $eksemplar->pluck('status')->map->value->unique()->values()->all(), 'Eksemplar baru selalu Tersedia.');
    }

    public function test_spasi_dirapikan_dan_tahun_berjalan_diterima(): void
    {
        $tahunIni = substr(WaktuPerpustakaan::hariIni(), 0, 4);
        $this->tambah($this->isian(['kodeBuku' => '  BK-902 ', 'judul' => '  Judul Rapi  ', 'tahunTerbit' => $tahunIni, 'eksemplar' => $this->eksemplar(' EK-920 ')]))
            ->assertSessionHasNoErrors();

        $buku = Book::cariKode('BK-902');
        $this->assertSame(['BK-902', 'Judul Rapi', (int) $tahunIni], [$buku->book_code, $buku->title, $buku->publication_year]);
        $this->assertSame(['EK-920'], $buku->copies()->pluck('copy_code')->all());
    }

    // ---------------------------------------------------------------- 3, 6, 4 keunikan & validasi

    public function test_kode_buku_ganda_ditolak_tanpa_membedakan_huruf_dan_kirim_ganda_tidak_menggandakan(): void
    {
        $this->tambah($this->isian())->assertSessionHasNoErrors();
        $this->tambah($this->isian(['eksemplar' => $this->eksemplar('EK-999')]))->assertSessionHasErrors(['kodeBuku' => 'Kode Buku sudah digunakan buku lain.']);
        $this->tambah($this->isian(['kodeBuku' => 'bk-901', 'eksemplar' => $this->eksemplar('EK-998')]))->assertSessionHasErrors('kodeBuku');

        $this->assertSame(1, Book::where('book_code', 'BK-901')->count());
        $this->assertFalse(BookCopy::whereIn('copy_code', ['EK-999', 'EK-998'])->exists(), 'Eksemplar dari permintaan gagal tidak tersimpan.');
        $this->assertSame(['buku.dibuat', 'eksemplar.dibuat'], $this->aksiAudit());
    }

    public function test_kode_eksemplar_ganda_ditolak(): void
    {
        $this->bukuDenganEksemplar('BK-800', ['Tersedia']);

        // Bentrok dengan eksemplar yang sudah ada (tanpa membedakan huruf).
        $this->tambah($this->isian(['eksemplar' => $this->eksemplar('bk-800-e1')]))
            ->assertSessionHasErrors(['eksemplar.0.kodeEksemplar' => 'Kode Eksemplar bk-800-e1 sudah digunakan.']);
        // Duplikat di dalam satu permintaan.
        $this->tambah($this->isian(['eksemplar' => $this->eksemplar('EK-950', 'ek-950')]))->assertSessionHasErrors('eksemplar.1.kodeEksemplar');
        // Pada Ubah Buku.
        $this->ubah('BK-800', $this->isian(['eksemplar' => $this->eksemplar('BK-800-E1')]))->assertSessionHasErrors('eksemplar.0.kodeEksemplar');

        $this->assertNull(Book::cariKode('BK-901'));
        $this->assertSame(1, BookCopy::count());
        $this->assertSame([], $this->aksiAudit());
    }

    public function test_data_buku_tidak_valid_ditolak(): void
    {
        $tahunDepan = (string) ((int) substr(WaktuPerpustakaan::hariIni(), 0, 4) + 1);

        $this->tambah([])->assertSessionHasErrors(['kodeBuku', 'judul', 'penulis', 'penerbit', 'tahunTerbit', 'kategori', 'eksemplar']);
        $this->tambah($this->isian(['kodeBuku' => 'BK 901']))->assertSessionHasErrors('kodeBuku');
        $this->tambah($this->isian(['kodeBuku' => 'BK/901']))->assertSessionHasErrors('kodeBuku');
        $this->tambah($this->isian(['kodeBuku' => str_repeat('B', 21)]))->assertSessionHasErrors(['kodeBuku' => 'Kode Buku maksimal 20 karakter.']);
        $this->tambah($this->isian(['judul' => str_repeat('j', 201)]))->assertSessionHasErrors(['judul' => 'Judul maksimal 200 karakter.']);
        $this->tambah($this->isian(['penulis' => str_repeat('p', 101)]))->assertSessionHasErrors(['penulis' => 'Penulis maksimal 100 karakter.']);
        $this->tambah($this->isian(['penerbit' => str_repeat('p', 101)]))->assertSessionHasErrors(['penerbit' => 'Penerbit maksimal 100 karakter.']);
        $this->tambah($this->isian(['kategori' => str_repeat('k', 51)]))->assertSessionHasErrors(['kategori' => 'Kategori maksimal 50 karakter.']);
        foreach (['abcd', '20a4', '999', '20245', $tahunDepan, '2024.5'] as $tahun) {
            $this->tambah($this->isian(['tahunTerbit' => $tahun]))->assertSessionHasErrors('tahunTerbit');
        }
        $this->tambah($this->isian(['eksemplar' => []]))->assertSessionHasErrors(['eksemplar' => 'Tambahkan minimal satu eksemplar untuk buku ini.']);
        $this->tambah($this->isian(['eksemplar' => $this->eksemplar('EK 1')]))->assertSessionHasErrors('eksemplar.0.kodeEksemplar');
        $this->tambah($this->isian(['eksemplar' => $this->eksemplar(str_repeat('E', 21))]))->assertSessionHasErrors('eksemplar.0.kodeEksemplar');
        $this->tambah($this->isian(['eksemplar' => $this->eksemplar(...array_map(fn ($i) => "EK-X{$i}", range(1, 101)))]))->assertSessionHasErrors('eksemplar');

        $this->assertSame(0, Book::count());
        $this->assertSame(0, BookCopy::count());
        $this->assertSame([], $this->aksiAudit());
    }

    // ---------------------------------------------------------------- 9, 28 rollback

    public function test_kegagalan_eksemplar_membatalkan_seluruh_penambahan_buku(): void
    {
        $ke = 0;
        BookCopy::creating(function () use (&$ke) {
            if (++$ke === 2) {
                throw new RuntimeException('Simulasi kegagalan eksemplar kedua.');
            }
        });

        try {
            $this->app->make(LayananBuku::class)->tambah($this->isian(), ['EK-901', 'EK-902', 'EK-903'], $this->admin);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
        } finally {
            BookCopy::flushEventListeners();
        }

        $this->assertNull(Book::cariKode('BK-901'), 'Buku ikut di-rollback.');
        $this->assertSame(0, BookCopy::count(), 'Tidak ada eksemplar parsial.');
        $this->assertSame([], $this->aksiAudit(), 'Tidak ada audit "berhasil" untuk transaksi gagal.');
    }

    public function test_kegagalan_eksemplar_baru_saat_ubah_membatalkan_perubahan_buku(): void
    {
        $buku = $this->bukuDenganEksemplar('BK-801', ['Tersedia']);
        BookCopy::creating(fn () => throw new RuntimeException('Simulasi kegagalan.'));

        try {
            $this->app->make(LayananBuku::class)->ubah('BK-801', $this->isian(['judul' => 'Judul Baru']), ['EK-NEW'], $this->admin);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
        } finally {
            BookCopy::flushEventListeners();
        }

        $this->assertSame('Judul BK-801', $buku->fresh()->title, 'Perubahan judul ikut dibatalkan.');
        $this->assertSame(1, BookCopy::count());
        $this->assertSame([], $this->aksiAudit());
    }

    // ---------------------------------------------------------------- 2, 5, 10, 14, 15 ubah

    public function test_admin_mengubah_buku_dan_menambah_eksemplar_tanpa_mengubah_kode_status_dan_relasi(): void
    {
        $buku = $this->bukuDenganEksemplar('BK-802', ['Tersedia', 'Dipesan', 'Dipinjam']);
        [$e1, $e2, $e3] = $buku->copies()->orderBy('copy_code')->get()->all();
        $anggota = $this->buatAnggota();
        $booking = $this->buatBooking($anggota, $e2, 'Disetujui', ['decided_at' => now(), 'staff_id' => $this->admin->id]);
        $pinjam = $this->buatPeminjaman($anggota, $e3, $this->admin);

        // Penulis, penerbit, dan tahun dikirim apa adanya; hanya judul & kategori yang berubah.
        $this->ubah('BK-802', [
            'kodeBuku' => 'BK-GANTI',
            'judul' => 'Judul Diperbarui',
            'penulis' => $buku->author,
            'penerbit' => $buku->publisher,
            'tahunTerbit' => (string) $buku->publication_year,
            'kategori' => 'Teknologi',
            'eksemplar' => $this->eksemplar('BK-802-E4', 'BK-802-E5'),
        ])->assertSessionHasNoErrors()
            ->assertRedirect('/admin/buku')
            ->assertSessionHas('status', 'Perubahan data buku BK-802 · Judul Diperbarui disimpan. 2 eksemplar baru ditambahkan.');

        $buku->refresh();
        $this->assertSame(['BK-802', 'Judul Diperbarui', 'Teknologi'], [$buku->book_code, $buku->title, $buku->category], 'Kode Buku tidak berubah.');
        $this->assertNull(Book::cariKode('BK-GANTI'));
        $this->assertSame(
            ['BK-802-E1' => 'Tersedia', 'BK-802-E2' => 'Dipesan', 'BK-802-E3' => 'Dipinjam', 'BK-802-E4' => 'Tersedia', 'BK-802-E5' => 'Tersedia'],
            $buku->copies()->orderBy('copy_code')->get()->mapWithKeys(fn ($e) => [$e->copy_code => $e->status->value])->all(),
            'Status eksemplar lama tidak berubah; eksemplar baru Tersedia.',
        );
        $this->assertSame([$e1->id, $e2->id, $e3->id], [$e1->fresh()->id, $e2->fresh()->id, $e3->fresh()->id]);
        $this->assertSame($e2->id, $booking->fresh()->copy_id, 'Relasi booking tetap.');
        $this->assertSame('Disetujui', $booking->fresh()->status->value);
        $this->assertSame($e3->id, $pinjam->fresh()->copy_id, 'Relasi peminjaman tetap.');
        $this->assertSame($anggota->id, $pinjam->fresh()->member_id);
        $this->assertSame('Aktif', $pinjam->fresh()->status->value);

        $audit = AuditLog::where('action', 'buku.diubah')->sole();
        // Kolom JSON MySQL tidak menjamin urutan kunci objek.
        $this->assertEqualsCanonicalizing(['kode_buku' => 'BK-802', 'kolom' => ['title', 'category']], $audit->metadata);
    }

    public function test_ubah_tanpa_perubahan_tidak_membuat_audit_dan_eksemplar_baru_opsional(): void
    {
        $buku = $this->bukuDenganEksemplar('BK-803', ['Tersedia']);
        $sama = ['judul' => $buku->title, 'penulis' => $buku->author, 'penerbit' => $buku->publisher, 'tahunTerbit' => (string) $buku->publication_year, 'kategori' => $buku->category];

        $this->ubah('BK-803', $sama)->assertSessionHasNoErrors();
        $this->ubah('BK-803', $sama + ['eksemplar' => []])->assertSessionHasNoErrors();
        $this->assertSame([], $this->aksiAudit());

        // Hanya menambah eksemplar.
        $this->ubah('BK-803', $sama + ['eksemplar' => $this->eksemplar('BK-803-E2')])->assertSessionHasNoErrors();
        $this->assertSame(['eksemplar.dibuat'], $this->aksiAudit());
        $this->assertSame(2, $buku->copies()->count());

        $this->ubah('BK-803', ['judul' => ''] + $sama)->assertSessionHasErrors('judul');
    }

    public function test_buku_lama_tanpa_eksemplar_wajib_diberi_eksemplar_saat_diubah(): void
    {
        Book::factory()->create(['book_code' => 'BK-804']);

        $this->ubah('BK-804', $this->isian(['eksemplar' => []]))->assertSessionHasErrors(['eksemplar' => LayananBuku::PESAN_MINIMAL_EKSEMPLAR]);
        $this->ubah('BK-804', $this->isian(['eksemplar' => $this->eksemplar('BK-804-E1')]))->assertSessionHasNoErrors();
        $this->assertSame(1, Book::cariKode('BK-804')->copies()->count());
    }

    // ---------------------------------------------------------------- 7 eksemplar tanpa buku

    public function test_eksemplar_tanpa_buku_ditolak(): void
    {
        $this->ubah('TIDAK-ADA', $this->isian(['eksemplar' => $this->eksemplar('EK-YATIM')]))->assertNotFound();
        $this->assertFalse(BookCopy::where('copy_code', 'EK-YATIM')->exists());

        $this->assertDitolakDb(1452, fn () => DB::table('book_copies')->insert(['book_id' => 999999999, 'copy_code' => 'EK-YATIM', 'status' => 'Tersedia']), 'FK menolak eksemplar tanpa buku.');
        $this->assertDitolakDb(1048, fn () => DB::table('book_copies')->insert(['book_id' => null, 'copy_code' => 'EK-YATIM2', 'status' => 'Tersedia']));
    }

    // ---------------------------------------------------------------- 12, 13 status manual

    public function test_admin_tidak_dapat_menetapkan_dipinjam_atau_dipesan(): void
    {
        $buku = $this->bukuDenganEksemplar('BK-805', ['Tersedia']);

        foreach (['Dipinjam', 'Dipesan', 'dipinjam', 'Rusak'] as $status) {
            $this->tambah($this->isian(['eksemplar' => [['kodeEksemplar' => 'EK-905', 'status' => $status]]]))
                ->assertSessionHasErrors('eksemplar.0.status');
            $this->ubah('BK-805', $this->isian(['eksemplar' => [['kodeEksemplar' => 'BK-805-E2', 'status' => $status]]]))
                ->assertSessionHasErrors('eksemplar.0.status');
        }

        $this->assertNull(Book::cariKode('BK-901'));
        $this->assertFalse(BookCopy::whereIn('copy_code', ['EK-905', 'BK-805-E2'])->exists());
        $this->assertSame('Tersedia', $buku->copies()->sole()->status->value);
        $this->assertSame([], $this->aksiAudit());

        // Tanpa status: server menetapkan Tersedia.
        $this->ubah('BK-805', $this->isian(['eksemplar' => [['kodeEksemplar' => 'BK-805-E2']]]))->assertSessionHasNoErrors();
        $this->assertSame('Tersedia', BookCopy::cariKode('BK-805-E2')->status->value);
    }

    // ---------------------------------------------------------------- 16, 17 tanpa hapus fisik

    public function test_buku_dan_eksemplar_berriwayat_tidak_dapat_dihapus(): void
    {
        $buku = $this->bukuDenganEksemplar('BK-806', ['Dipesan', 'Dipinjam']);
        [$dipesan, $dipinjam] = $buku->copies()->orderBy('copy_code')->get()->all();
        $anggota = $this->buatAnggota();
        $this->buatBooking($anggota, $dipesan, 'Disetujui', ['decided_at' => now(), 'staff_id' => $this->admin->id]);
        $this->buatPeminjaman($anggota, $dipinjam, $this->admin);

        // Tidak ada rute hapus.
        $this->actingAs($this->admin)->delete('/admin/buku/BK-806')->assertStatus(405);

        // Database menolak (FK RESTRICT).
        $this->assertDitolakDb(1451, fn () => Book::whereKey($buku->id)->delete(), 'Buku dengan eksemplar tidak dapat dihapus.');
        $this->assertDitolakDb(1451, fn () => BookCopy::whereKey($dipinjam->id)->delete(), 'Eksemplar dengan peminjaman tidak dapat dihapus.');
        $this->assertDitolakDb(1451, fn () => BookCopy::whereKey($dipesan->id)->delete(), 'Eksemplar dengan booking tidak dapat dihapus.');

        $this->assertSame(2, $buku->copies()->count());
        $this->assertSame(1, Loan::count());
        $this->assertSame(1, Booking::count());
    }

    // ---------------------------------------------------------------- 18–22 katalog & ketersediaan

    public function test_data_buku_dan_ketersediaan_dari_mysql_untuk_admin_dan_katalog_anggota(): void
    {
        $this->tambah($this->isian(['eksemplar' => $this->eksemplar('EK-901', 'EK-902')]))->assertSessionHasNoErrors();
        $this->bukuDenganEksemplar('BK-807', ['Tersedia', 'Tersedia', 'Dipesan', 'Dipinjam']);

        $ketersediaan = function (array $props, string $kodeBuku): array {
            $milik = array_values(array_filter($props['data']['eksemplar'], fn ($e) => $e['kodeBuku'] === $kodeBuku));
            $hitung = array_count_values(array_column($milik, 'status'));

            return ['total' => count($milik)] + $hitung;
        };

        $admin = $this->props('/admin/buku');
        $this->assertSame('Buku BK-901 · Rekayasa Perangkat Lunak Terapan berhasil ditambahkan dengan 2 eksemplar.', $admin['flash']['status']);
        $this->assertSame(
            ['kodeBuku' => 'BK-901', 'judul' => 'Rekayasa Perangkat Lunak Terapan', 'penulis' => 'Penulis Uji', 'penerbit' => 'Penerbit Uji', 'tahunTerbit' => 2024, 'kategori' => 'Informatika'],
            collect($admin['data']['buku'])->firstWhere('kodeBuku', 'BK-901'),
        );
        $this->assertSame(['total' => 2, 'Tersedia' => 2], $ketersediaan($admin, 'BK-901'));
        $this->assertSame(['total' => 4, 'Tersedia' => 2, 'Dipesan' => 1, 'Dipinjam' => 1], $ketersediaan($admin, 'BK-807'));

        // Halaman Ubah memuat buku dari MySQL.
        $this->assertSame('BK-901', collect($this->props('/admin/buku/BK-901/ubah')['data']['buku'])->firstWhere('kodeBuku', 'BK-901')['kodeBuku']);

        // Katalog Anggota: buku & eksemplar baru, tanpa data peminjam.
        $akun = $this->buatAkunAnggota();
        $this->buatAnggota([], $akun);
        $this->flushSession();
        $this->actingAs($akun);
        $katalog = $this->props('/anggota/cari-buku');

        $this->assertSame(['buku', 'eksemplar', 'kategori'], array_keys($katalog['data']));
        $this->assertContains('BK-901', array_column($katalog['data']['buku'], 'kodeBuku'));
        $this->assertSame(['total' => 4, 'Tersedia' => 2, 'Dipesan' => 1, 'Dipinjam' => 1], $ketersediaan($katalog, 'BK-807'));
        foreach ($katalog['data']['eksemplar'] as $e) {
            $this->assertSame(['kodeEksemplar', 'kodeBuku', 'status'], array_keys($e), 'Eksemplar katalog tanpa data peminjam/pemesan.');
        }

        // Eksemplar baru lewat Ubah langsung terlihat di katalog.
        $this->flushSession();
        $this->ubah('BK-807', $this->isian(['eksemplar' => $this->eksemplar('BK-807-E5')]))->assertSessionHasNoErrors();
        $this->flushSession();
        $this->actingAs($akun);
        $this->assertSame(['total' => 5, 'Tersedia' => 3, 'Dipesan' => 1, 'Dipinjam' => 1], $ketersediaan($this->props('/anggota/cari-buku'), 'BK-807'));
    }

    // ---------------------------------------------------------------- 23–26 otorisasi & pemalsuan

    public function test_anggota_dan_tamu_tidak_dapat_menulis_buku(): void
    {
        $buku = $this->bukuDenganEksemplar('BK-808', ['Tersedia']);
        $akun = $this->buatAkunAnggota();
        $this->buatAnggota([], $akun);

        $this->post('/admin/buku', $this->isian())->assertRedirect(route('login'));
        $this->put('/admin/buku/BK-808', $this->isian(['judul' => 'Tamu']))->assertRedirect(route('login'));
        $this->postJson('/admin/buku', $this->isian())->assertUnauthorized();

        $this->actingAs($akun);
        $this->post('/admin/buku', $this->isian())->assertForbidden();
        $this->put('/admin/buku/BK-808', $this->isian(['judul' => 'Anggota']))->assertForbidden();

        $this->assertNull(Book::cariKode('BK-901'));
        $this->assertSame('Judul BK-808', $buku->fresh()->title);
        $this->assertSame(1, BookCopy::count());
        $this->assertSame([], $this->aksiAudit());
    }

    public function test_admin_nonaktif_tidak_dapat_menulis_buku(): void
    {
        $nonaktif = $this->buatAdmin(['is_active' => false]);
        $this->actingAs($nonaktif)->post('/admin/buku', $this->isian());
        $this->assertNull(Book::cariKode('BK-901'));
    }

    public function test_csrf_wajib_untuk_tulis_buku(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->actingAs($this->admin)->post('/admin/buku', $this->isian())->assertStatus(419);
        $this->actingAs($this->admin)->put('/admin/buku/BK-901', $this->isian())->assertStatus(419);
        $this->assertNull(Book::cariKode('BK-901'));
    }

    public function test_klien_tidak_dapat_memalsukan_field_istimewa(): void
    {
        $lain = $this->bukuDenganEksemplar('BK-809', ['Dipinjam']);
        $adminLain = $this->buatAdmin();

        $this->tambah($this->isian() + [
            'id' => $lain->id, 'book_code' => 'HACK', 'book_id' => $lain->id, 'status' => 'Dipinjam',
            'actor_id' => $adminLain->id, 'staff_id' => $adminLain->id, 'created_at' => '2000-01-01',
        ])->assertSessionHasNoErrors();

        $buku = Book::cariKode('BK-901');
        $this->assertNotSame($lain->id, $buku->id);
        $this->assertNull(Book::cariKode('HACK'));
        $this->assertSame('Tersedia', $buku->copies()->sole()->status->value);
        $this->assertSame([$this->admin->id], AuditLog::query()->pluck('actor_id')->unique()->values()->all(), 'Aktor = Admin dari sesi.');
        $this->assertNotSame('2000', $buku->created_at->format('Y'));

        // Kunci asing di dalam data eksemplar (book_id/id) ditolak — eksemplar tidak dapat dipindah ke buku lain.
        $this->tambah($this->isian(['kodeBuku' => 'BK-910', 'eksemplar' => [['kodeEksemplar' => 'EK-910', 'book_id' => $lain->id]]]))
            ->assertSessionHasErrors('eksemplar.0');
        $this->assertFalse(BookCopy::where('copy_code', 'EK-910')->exists());
        $this->assertSame(1, $lain->copies()->count());
        $this->assertSame('Dipinjam', $lain->copies()->sole()->status->value);
    }

    // ---------------------------------------------------------------- 27 audit

    public function test_audit_mencatat_aktor_subjek_dan_metadata_tanpa_data_sensitif(): void
    {
        $this->tambah($this->isian(['eksemplar' => $this->eksemplar('EK-901', 'EK-902')]))->assertSessionHasNoErrors();
        $buku = Book::cariKode('BK-901');
        $this->ubah('BK-901', $this->isian(['penulis' => 'Penulis Baru', 'eksemplar' => $this->eksemplar('EK-903')]))->assertSessionHasNoErrors();

        $log = AuditLog::query()->orderBy('id')->get();
        $this->assertSame(['buku.dibuat', 'eksemplar.dibuat', 'eksemplar.dibuat', 'buku.diubah', 'eksemplar.dibuat'], $log->pluck('action')->all());
        $this->assertSame([$this->admin->id], $log->pluck('actor_id')->unique()->values()->all());
        $this->assertSame(['book', 'book_copy', 'book_copy', 'book', 'book_copy'], $log->pluck('subject_type')->all());
        $this->assertSame($buku->id, $log[0]->subject_id);
        $this->assertSame(BookCopy::cariKode('EK-903')->id, $log[4]->subject_id);
        $this->assertEqualsCanonicalizing(['kode_buku' => 'BK-901', 'jumlah_eksemplar' => 2], $log[0]->metadata);
        $this->assertEqualsCanonicalizing(['kode_eksemplar' => 'EK-901', 'kode_buku' => 'BK-901'], $log[1]->metadata);
        $this->assertEqualsCanonicalizing(['kode_buku' => 'BK-901', 'kolom' => ['author']], $log[3]->metadata);
        $this->assertStringNotContainsString('Penulis Baru', $log->toJson(), 'Nilai field tidak dicatat, hanya nama kolom.');
        $this->assertTrue($log->every(fn ($l) => $l->created_at !== null));
    }

    // ---------------------------------------------------------------- 29, 30 balapan (dua koneksi MySQL)

    /**
     * Koneksi kedua ke database uji yang sama (sesi MySQL terpisah). Baris yang
     * dibuat di sini BENAR-BENAR di-commit, jadi wajib dibersihkan.
     */
    private function koneksiKedua(): Connection
    {
        config(['database.connections.mysql_uji_kedua' => config('database.connections.'.self::KONEKSI)]);
        $kedua = DB::connection('mysql_uji_kedua');
        $kedua->statement('SET SESSION innodb_lock_wait_timeout = 5');

        return $kedua;
    }

    /** Akhiri transaksi test (melepas kunci baris), lalu hapus baris ter-commit milik koneksi kedua. */
    private function bersihkanKoneksiKedua(Connection $kedua, array $kodeBuku): void
    {
        DB::connection(self::KONEKSI)->rollBack();
        $id = $kedua->table('books')->whereIn('book_code', $kodeBuku)->pluck('id');
        $kedua->table('book_copies')->whereIn('book_id', $id)->delete();
        $kedua->table('books')->whereIn('id', $id)->delete();
        DB::purge('mysql_uji_kedua');

        $this->assertSame(0, DB::connection(self::KONEKSI)->table('books')->whereIn('book_code', $kodeBuku)->count(), 'Database uji bersih kembali.');
    }

    public function test_balapan_kode_buku_ganda_antar_koneksi_ditolak_sebagai_galat_validasi(): void
    {
        $kode = 'UJI-BLP-'.Str::upper(Str::random(6));
        $kedua = $this->koneksiKedua();

        try {
            // Permintaan lain menyimpan & COMMIT lebih dulu (sesi MySQL berbeda)...
            $kedua->table('books')->insert(['book_code' => $kode, 'title' => 'Balapan', 'author' => 'A', 'publisher' => 'P', 'publication_year' => 2024, 'category' => 'Uji']);

            // ...sedangkan permintaan ini sudah lolos validasi Form Request: UNIQUE MySQL → galat validasi.
            try {
                $this->app->make(LayananBuku::class)->tambah($this->isian(['kodeBuku' => $kode]), ['EK-BLP-1'], $this->admin);
                $this->fail('Seharusnya ditolak UNIQUE.');
            } catch (ValidationException $e) {
                $this->assertSame([LayananBuku::PESAN_KODE_BUKU_DIPAKAI], $e->errors()['kodeBuku']);
            }

            $this->assertSame(1, Book::where('book_code', $kode)->count());
            $this->assertFalse(BookCopy::where('copy_code', 'EK-BLP-1')->exists(), 'Eksemplar tidak tersimpan.');
            $this->assertSame([], $this->aksiAudit());
        } finally {
            $this->bersihkanKoneksiKedua($kedua, [$kode]);
        }
    }

    public function test_balapan_kode_eksemplar_ganda_antar_koneksi_ditolak_dan_rollback_penuh(): void
    {
        $kodeBukuLain = 'UJI-BLP-'.Str::upper(Str::random(6));
        $kodeEksemplar = 'UJI-BLPE-'.Str::upper(Str::random(6));
        $kedua = $this->koneksiKedua();

        try {
            $idLain = $kedua->table('books')->insertGetId(['book_code' => $kodeBukuLain, 'title' => 'Balapan', 'author' => 'A', 'publisher' => 'P', 'publication_year' => 2024, 'category' => 'Uji']);
            $kedua->table('book_copies')->insert(['book_id' => $idLain, 'copy_code' => $kodeEksemplar, 'status' => 'Tersedia']);

            $layanan = $this->app->make(LayananBuku::class);

            // Tambah: buku baru + eksemplar yang bentrok di posisi kedua → seluruh transaksi batal.
            try {
                $layanan->tambah($this->isian(), ['EK-AMAN-1', $kodeEksemplar], $this->admin);
                $this->fail('Seharusnya ditolak UNIQUE.');
            } catch (ValidationException $e) {
                $this->assertSame(["Kode Eksemplar {$kodeEksemplar} sudah digunakan. Gunakan kode lain."], $e->errors()['eksemplar']);
            }
            $this->assertNull(Book::cariKode('BK-901'), 'Buku di-rollback.');
            $this->assertFalse(BookCopy::where('copy_code', 'EK-AMAN-1')->exists());

            // Ubah: perubahan judul ikut batal.
            $buku = $this->bukuDenganEksemplar('BK-811', ['Tersedia']);
            try {
                $layanan->ubah('BK-811', $this->isian(['judul' => 'Tidak Boleh Tersimpan']), [$kodeEksemplar], $this->admin);
                $this->fail('Seharusnya ditolak UNIQUE.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('eksemplar', $e->errors());
            }
            $this->assertSame('Judul BK-811', $buku->fresh()->title);
            $this->assertSame(1, BookCopy::where('copy_code', $kodeEksemplar)->count());
            $this->assertSame([], $this->aksiAudit());
        } finally {
            $this->bersihkanKoneksiKedua($kedua, [$kodeBukuLain]);
        }
    }

    // ---------------------------------------------------------------- 31 N+1

    public function test_jumlah_query_daftar_buku_dan_katalog_tidak_bertambah_seiring_data(): void
    {
        $akun = $this->buatAkunAnggota();
        $this->buatAnggota([], $akun);

        $hitung = function (User $u, string $url): int {
            $this->flushSession();
            $this->actingAs($u);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->bukuDenganEksemplar('BK-820', ['Tersedia']);
        $awal = [$hitung($this->admin, '/admin/buku'), $hitung($this->admin, '/admin/buku/BK-820/ubah'), $hitung($akun, '/anggota/cari-buku')];

        for ($i = 1; $i <= 8; $i++) {
            $this->bukuDenganEksemplar("BK-83{$i}", ['Tersedia', 'Dipesan', 'Dipinjam']);
        }
        $akhir = [$hitung($this->admin, '/admin/buku'), $hitung($this->admin, '/admin/buku/BK-820/ubah'), $hitung($akun, '/anggota/cari-buku')];

        $this->assertSame($awal, $akhir, 'Jumlah query tetap walau buku/eksemplar bertambah (tanpa N+1).');
    }
}
