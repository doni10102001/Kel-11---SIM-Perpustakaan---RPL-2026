<?php

namespace Tests\Feature;

use App\Support\NavigasiDemo;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 1: rute, layout, navigasi, dan pratinjau dua peran (Admin, Anggota)
 * tanpa database.
 */
class NavigasiDemoTest extends TestCase
{
    private const MENU_ADMIN = [
        'Dashboard',
        'Data Anggota',
        'Data Buku & Eksemplar',
        'Peminjaman Buku',
        'Pengembalian Buku',
        'Verifikasi Booking',
        'Laporan',
    ];

    private const MENU_ANGGOTA = ['Dashboard', 'Cari Buku', 'Booking Pinjam Buku', 'History Peminjaman'];

    protected function setUp(): void
    {
        parent::setUp();
        DB::enableQueryLog();
    }

    protected function tearDown(): void
    {
        $this->assertSame([], DB::getQueryLog(), 'Halaman pratinjau tidak boleh menjalankan query database.');
        parent::tearDown();
    }

    public function test_prototype_uses_exactly_two_roles(): void
    {
        $this->assertSame(['admin', 'anggota'], NavigasiDemo::PERAN);
        $this->assertSame(['admin' => 'Admin', 'anggota' => 'Anggota'], NavigasiDemo::LABEL_PERAN);
    }

    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_renders_without_role(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('demo.peran', null)
                ->where('demo.menu', [])
                // Kunci rute memakai titik, jadi dibandingkan sebagai array utuh.
                ->where('demo.rute', [
                    'login' => route('login'),
                    'beranda.admin' => url('/demo/admin/dashboard'),
                    'beranda.anggota' => url('/demo/anggota/dashboard'),
                ]));
    }

    public function test_every_admin_page_renders(): void
    {
        foreach (NavigasiDemo::halamanAdmin() as $kunci => $halaman) {
            $this->get("/demo/admin/{$halaman['uri']}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($halaman['komponen'])
                    ->where('demo.peran', 'admin')
                    ->where('demo.labelPeran', 'Admin')
                    ->where('demo.halaman.judul', $halaman['judul'])
                    ->where('demo.rute', fn ($rute) => $rute[$kunci] === url("/demo/admin/{$halaman['uri']}")));
        }
    }

    public function test_every_anggota_page_renders(): void
    {
        foreach (NavigasiDemo::halamanAnggota() as $kunci => $halaman) {
            $this->get("/demo/anggota/{$halaman['uri']}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($halaman['komponen'])
                    ->where('demo.peran', 'anggota')
                    ->where('demo.labelPeran', 'Anggota')
                    ->where('demo.halaman.judul', $halaman['judul'])
                    ->where('demo.rute', fn ($rute) => $rute[$kunci] === url("/demo/anggota/{$halaman['uri']}")));
        }
    }

    public function test_petugas_role_routes_do_not_exist(): void
    {
        // Prototipe hanya memakai Admin dan Anggota; tidak ada rute peran Petugas.
        $this->get('/demo/petugas/dashboard')->assertNotFound();

        foreach (NavigasiDemo::halamanAdmin() as $halaman) {
            $this->get("/demo/petugas/{$halaman['uri']}")->assertNotFound();
        }
    }

    public function test_admin_menu_matches_approved_navigation(): void
    {
        $menu = $this->menuDari('/demo/admin/dashboard');

        $this->assertSame(self::MENU_ADMIN, $this->labelMenu($menu));
        $this->assertNotContains('Kelola Pengguna', $this->labelMenu($menu));
        $this->assertNotContains('Kelola Role', $this->labelMenu($menu));
    }

    public function test_anggota_menu_matches_approved_navigation(): void
    {
        $menu = $this->menuDari('/demo/anggota/dashboard');
        $item = collect($menu)->flatMap(fn ($grup) => $grup['item']);

        $this->assertSame(self::MENU_ANGGOTA, $this->labelMenu($menu));
        $this->assertSame(
            ['Ajukan Booking', 'Status Booking'],
            collect($item->firstWhere('label', 'Booking Pinjam Buku')['anak'])->pluck('label')->all(),
        );
    }

    public function test_exactly_one_menu_item_is_active_per_page(): void
    {
        $harapan = [
            '/demo/admin/dashboard' => 'Dashboard',
            '/demo/admin/anggota/tambah' => 'Data Anggota',
            '/demo/admin/buku/tambah' => 'Data Buku & Eksemplar',
            '/demo/admin/booking/realisasi' => 'Verifikasi Booking',
            '/demo/admin/laporan' => 'Laporan',
            '/demo/anggota/booking/status' => 'Status Booking',
            '/demo/anggota/history' => 'History Peminjaman',
        ];

        foreach ($harapan as $url => $label) {
            $aktif = collect($this->menuDari($url))
                ->flatMap(fn ($grup) => $grup['item'])
                ->flatMap(fn ($item) => [$item, ...$item['anak']])
                ->filter(fn ($item) => $item['aktif'] && $item['anak'] === [])
                ->pluck('label')
                ->all();

            $this->assertSame([$label], $aktif, "Item aktif untuk {$url}");
        }
    }

    public function test_breadcrumbs_follow_page_hierarchy(): void
    {
        $this->get('/demo/admin/anggota/tambah')->assertInertia(fn (Assert $page) => $page
            ->where('demo.halaman.remah.0.label', 'Dashboard')
            ->where('demo.halaman.remah.1.label', 'Data Anggota')
            ->where('demo.halaman.remah.1.url', url('/demo/admin/anggota'))
            ->where('demo.halaman.remah.2.label', 'Tambah Anggota')
            ->where('demo.halaman.remah.2.url', null));
    }

    public function test_every_menu_link_resolves(): void
    {
        foreach (['/demo/admin/dashboard', '/demo/anggota/dashboard'] as $awal) {
            $tautan = collect($this->menuDari($awal))
                ->flatMap(fn ($grup) => $grup['item'])
                ->flatMap(fn ($item) => [$item, ...$item['anak']])
                ->pluck('url')
                ->filter();

            $this->assertNotEmpty($tautan);

            foreach ($tautan as $url) {
                $this->get($url)->assertOk();
            }
        }
    }

    public function test_role_boundaries_in_urls(): void
    {
        $this->get('/demo/tamu/dashboard')->assertNotFound();
        $this->get('/demo/anggota/peminjaman')->assertNotFound();
        $this->get('/demo/anggota/laporan')->assertNotFound();
        $this->get('/demo/admin/cari-buku')->assertNotFound();
        $this->get('/demo/anggota/anggota')->assertNotFound();
    }

    public function test_component_catalogue_page_renders(): void
    {
        $this->get('/demo/komponen')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Sistem/PratinjauKomponen'));
    }

    /** @return list<array<string, mixed>> */
    private function menuDari(string $url): array
    {
        $menu = [];
        $this->get($url)->assertOk()->assertInertia(function (Assert $page) use (&$menu) {
            $menu = $page->toArray()['props']['demo']['menu'];
        });

        return $menu;
    }

    /** @return list<string> */
    private function labelMenu(array $menu): array
    {
        return collect($menu)->flatMap(fn ($grup) => $grup['item'])->pluck('label')->all();
    }
}
