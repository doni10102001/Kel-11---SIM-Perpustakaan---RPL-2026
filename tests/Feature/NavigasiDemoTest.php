<?php

namespace Tests\Feature;

use App\Enums\StatusAnggota;
use App\Models\User;
use App\Support\NavigasiDemo;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Database\MySqlTestCase;

/**
 * Rute, layout, dan navigasi dua peran (Admin, Anggota).
 *
 * Phase 8A: halaman aplikasi pindah dari /demo/{peran}/* ke /{peran}/* dan
 * WAJIB masuk dengan peran yang sesuai. Test ini memakai akun nyata di
 * database uji MySQL (sim_perpustakaan_test, transaksi di-rollback); cakupan
 * navigasi Phase 1–5 dipertahankan.
 */
class NavigasiDemoTest extends MySqlTestCase
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

    /** @var array<string, User> */
    private array $akun = [];

    /** Akun nyata sesuai prefiks URL: /anggota → Anggota Aktif, selain itu Admin. */
    private function akunUntuk(string $url): User
    {
        $peran = str_starts_with(parse_url($url, PHP_URL_PATH) ?? $url, '/anggota') ? 'anggota' : 'admin';

        return $this->akun[$peran] ??= $peran === 'admin'
            ? $this->buatAdmin()
            : tap($this->buatAkunAnggota(), fn (User $u) => $this->buatAnggota(['status' => StatusAnggota::Aktif], $u));
    }

    /** GET sebagai akun yang berhak atas URL tersebut. */
    private function kunjungi(string $url): TestResponse
    {
        return $this->actingAs($this->akunUntuk($url))->get($url);
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
                ->where('auth.akun', null)
                ->where('demo.rute', [
                    'login' => route('login'),
                    'logout' => route('logout'),
                    'beranda.admin' => url('/admin/dashboard'),
                    'beranda.anggota' => url('/anggota/dashboard'),
                ]));
    }

    /** Contoh nilai parameter untuk rute berparameter. */
    private const CONTOH_PARAMETER = ['{kodeAnggota}' => 'AG-001', '{kodeBuku}' => 'BK-001'];

    public function test_every_admin_page_renders(): void
    {
        foreach (NavigasiDemo::halamanAdmin() as $kunci => $halaman) {
            $uri = strtr($halaman['uri'], self::CONTOH_PARAMETER);

            $this->kunjungi("/admin/{$uri}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($halaman['komponen'])
                    ->where('demo.peran', 'admin')
                    ->where('demo.labelPeran', 'Admin')
                    ->where('demo.halaman.judul', $halaman['judul'])
                    // Rute berparameter dikirim sebagai templat, mis. .../anggota/{kodeAnggota}/ubah
                    ->where('demo.rute', fn ($rute) => $rute[$kunci] === url("/admin/{$halaman['uri']}")));
        }
    }

    public function test_phase3_pages_use_final_components(): void
    {
        $harapan = [
            '/admin/dashboard' => 'Admin/Dashboard',
            '/admin/anggota' => 'Admin/Anggota/Index',
            '/admin/anggota/tambah' => 'Admin/Anggota/Form',
            '/admin/anggota/AG-001/ubah' => 'Admin/Anggota/Form',
            '/admin/buku' => 'Admin/Buku/Index',
            '/admin/buku/tambah' => 'Admin/Buku/Form',
            '/admin/buku/BK-001/ubah' => 'Admin/Buku/Form',
        ];

        foreach ($harapan as $url => $komponen) {
            $this->kunjungi($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($komponen));
        }

    }

    public function test_phase4_pages_use_final_components(): void
    {
        $harapan = [
            '/admin/peminjaman' => 'Admin/Peminjaman',
            '/admin/pengembalian' => 'Admin/Pengembalian',
            '/admin/booking/verifikasi' => 'Admin/Booking/Verifikasi',
            '/admin/booking/realisasi' => 'Admin/Booking/Realisasi',
            '/admin/laporan' => 'Admin/Laporan',
        ];

        foreach ($harapan as $url => $komponen) {
            $this->kunjungi($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($komponen));
        }

    }

    public function test_phase5_anggota_pages_use_final_components(): void
    {
        $harapan = [
            '/anggota/dashboard' => 'Anggota/Dashboard',
            '/anggota/cari-buku' => 'Anggota/CariBuku',
            '/anggota/booking/ajukan' => 'Anggota/Booking/Ajukan',
            '/anggota/booking/status' => 'Anggota/Booking/Status',
            '/anggota/history' => 'Anggota/History',
        ];

        foreach ($harapan as $url => $komponen) {
            $this->kunjungi($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($komponen));
        }

        // Tidak ada lagi halaman yang memakai kerangka sementara.
        foreach ([...NavigasiDemo::halamanAdmin(), ...NavigasiDemo::halamanAnggota()] as $halaman) {
            $this->assertNotSame('Sistem/HalamanDisiapkan', $halaman['komponen']);
        }
    }

    public function test_edit_routes_share_route_parameters(): void
    {
        $this->kunjungi('/admin/anggota/AG-003/ubah')->assertInertia(fn (Assert $page) => $page
            ->where('demo.parameter', ['kodeAnggota' => 'AG-003'])
            ->where('demo.halaman.judul', 'Ubah Anggota')
            ->where('demo.halaman.remah.1.label', 'Data Anggota'));

        $this->kunjungi('/admin/buku/BK-010/ubah')->assertInertia(fn (Assert $page) => $page
            ->where('demo.parameter', ['kodeBuku' => 'BK-010'])
            ->where('demo.halaman.judul', 'Ubah Buku')
            ->where('demo.halaman.remah.1.label', 'Data Buku & Eksemplar'));

        // Kode dengan karakter tidak sah ditolak oleh pola rute.
        $this->kunjungi('/admin/anggota/AG%20001/ubah')->assertNotFound();
        $this->kunjungi('/admin/buku/BK<1>/ubah')->assertNotFound();
    }

    public function test_every_anggota_page_renders(): void
    {
        foreach (NavigasiDemo::halamanAnggota() as $kunci => $halaman) {
            $this->kunjungi("/anggota/{$halaman['uri']}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($halaman['komponen'])
                    ->where('demo.peran', 'anggota')
                    ->where('demo.labelPeran', 'Anggota')
                    ->where('demo.halaman.judul', $halaman['judul'])
                    ->where('demo.rute', fn ($rute) => $rute[$kunci] === url("/anggota/{$halaman['uri']}")));
        }
    }

    public function test_petugas_role_routes_do_not_exist(): void
    {
        // Aplikasi hanya memakai Admin dan Anggota; tidak ada rute peran Petugas (juga lewat /demo lama).
        $this->kunjungi('/petugas/dashboard')->assertNotFound();
        $this->get('/demo/petugas/dashboard')->assertNotFound();

        foreach (NavigasiDemo::halamanAdmin() as $halaman) {
            $this->kunjungi('/petugas/'.strtr($halaman['uri'], self::CONTOH_PARAMETER))->assertNotFound();
        }
    }

    public function test_admin_menu_matches_approved_navigation(): void
    {
        $menu = $this->menuDari('/admin/dashboard');

        $this->assertSame(self::MENU_ADMIN, $this->labelMenu($menu));
        $this->assertNotContains('Kelola Pengguna', $this->labelMenu($menu));
        $this->assertNotContains('Kelola Role', $this->labelMenu($menu));
    }

    public function test_anggota_menu_matches_approved_navigation(): void
    {
        $menu = $this->menuDari('/anggota/dashboard');
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
            '/admin/dashboard' => 'Dashboard',
            '/admin/anggota/tambah' => 'Data Anggota',
            '/admin/anggota/AG-002/ubah' => 'Data Anggota',
            '/admin/buku/BK-002/ubah' => 'Data Buku & Eksemplar',
            '/admin/buku/tambah' => 'Data Buku & Eksemplar',
            '/admin/booking/realisasi' => 'Verifikasi Booking',
            '/admin/laporan' => 'Laporan',
            '/anggota/booking/status' => 'Status Booking',
            '/anggota/history' => 'History Peminjaman',
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
        $this->kunjungi('/admin/anggota/tambah')->assertInertia(fn (Assert $page) => $page
            ->where('demo.halaman.remah.0.label', 'Dashboard')
            ->where('demo.halaman.remah.1.label', 'Data Anggota')
            ->where('demo.halaman.remah.1.url', url('/admin/anggota'))
            ->where('demo.halaman.remah.2.label', 'Tambah Anggota')
            ->where('demo.halaman.remah.2.url', null));
    }

    public function test_every_menu_link_resolves(): void
    {
        foreach (['/admin/dashboard', '/anggota/dashboard'] as $awal) {
            $tautan = collect($this->menuDari($awal))
                ->flatMap(fn ($grup) => $grup['item'])
                ->flatMap(fn ($item) => [$item, ...$item['anak']])
                ->pluck('url')
                ->filter();

            $this->assertNotEmpty($tautan);

            foreach ($tautan as $url) {
                $this->kunjungi($url)->assertOk();
            }
        }
    }

    public function test_role_boundaries_in_urls(): void
    {
        $this->kunjungi('/tamu/dashboard')->assertNotFound();
        $this->kunjungi('/anggota/peminjaman')->assertNotFound();
        $this->kunjungi('/anggota/laporan')->assertNotFound();
        $this->kunjungi('/admin/cari-buku')->assertNotFound();
        $this->kunjungi('/anggota/anggota')->assertNotFound();
    }

    public function test_component_catalogue_page_renders(): void
    {
        $this->kunjungi('/admin/komponen')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Sistem/PratinjauKomponen'));
    }

    public function test_legacy_demo_urls_only_redirect_to_protected_routes(): void
    {
        $this->get('/demo/admin/dashboard')->assertRedirect(url('/admin/dashboard'));
        $this->get('/demo/admin/anggota/AG-001/ubah')->assertRedirect(url('/admin/anggota/AG-001/ubah'));
        $this->get('/demo/anggota/history')->assertRedirect(url('/anggota/history'));
        $this->get('/demo/komponen')->assertRedirect('/admin/komponen');

        // Tujuan redirect tetap terlindungi: tamu diarahkan ke halaman Masuk.
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/anggota/history')->assertRedirect(route('login'));
    }

    public function test_shared_identity_comes_from_authenticated_session(): void
    {
        $admin = $this->akunUntuk('/admin/dashboard');
        $this->kunjungi('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.akun.peran', 'admin')
            ->where('auth.akun.identitas', $admin->username)
            ->where('auth.akun.nama', $admin->name)
            ->missing('auth.akun.kodeAnggota'));

        $anggota = $this->akunUntuk('/anggota/dashboard');
        $this->kunjungi('/anggota/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.akun.peran', 'anggota')
            ->where('auth.akun.kodeAnggota', $anggota->member->member_code)
            ->where('auth.akun.nama', $anggota->member->name));
    }

    /** @return list<array<string, mixed>> */
    private function menuDari(string $url): array
    {
        $menu = [];
        $this->kunjungi($url)->assertOk()->assertInertia(function (Assert $page) use (&$menu) {
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
