<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Konfigurasi pusat halaman, rute, dan navigasi untuk pratinjau statis.
 *
 * Prototipe memakai DUA peran: Admin dan Anggota (lihat docs/KEPUTUSAN_DESAIN.md).
 * SRS v1.1 membedakan Admin dan Petugas; seluruh fungsi operasional Petugas
 * dijalankan oleh Admin pada prototipe ini — perlu diselaraskan di dokumentasi final.
 *
 * - Semua URL dibangkitkan dengan route() sehingga tautan tidak bisa patah.
 * - Setiap item navigasi menyimpan daftar `peran` yang berhak, sebagai dasar
 *   otorisasi berbasis peran di tahap berikutnya. Pada tahap statis ini
 *   TIDAK ada autentikasi maupun otorisasi server; peran hanya dibaca dari rute.
 */
class NavigasiDemo
{
    public const PERAN_ADMIN = 'admin';

    public const PERAN_ANGGOTA = 'anggota';

    /** Peran yang tersedia di prototipe; juga menjadi prefiks URL /demo/{peran}/... */
    public const PERAN = [self::PERAN_ADMIN, self::PERAN_ANGGOTA];

    public const LABEL_PERAN = [
        self::PERAN_ADMIN => 'Admin',
        self::PERAN_ANGGOTA => 'Anggota',
    ];

    /** Komponen sementara untuk halaman yang isinya dibangun pada phase berikutnya. */
    public const KOMPONEN_DISIAPKAN = 'Sistem/HalamanDisiapkan';

    /**
     * Daftar halaman per peran. Kunci = akhiran nama rute `demo.{peran}.{kunci}`.
     *
     * @return array<string, array{uri: string, judul: string, deskripsi: string, induk: ?string, komponen: string}>
     */
    public static function halaman(string $peran): array
    {
        return match ($peran) {
            self::PERAN_ADMIN => self::halamanAdmin(),
            self::PERAN_ANGGOTA => self::halamanAnggota(),
        };
    }

    /** @return array<string, array{uri: string, judul: string, deskripsi: string, induk: ?string, komponen: string}> */
    public static function halamanAdmin(): array
    {
        return [
            'dashboard' => [
                'uri' => 'dashboard',
                'judul' => 'Dashboard',
                'deskripsi' => 'Ringkasan data dan layanan perpustakaan.',
                'induk' => null,
                'komponen' => 'Admin/Dashboard',
            ],
            'anggota.index' => [
                'uri' => 'anggota',
                'judul' => 'Data Anggota',
                'deskripsi' => 'Kelola data dan status keanggotaan perpustakaan.',
                'induk' => 'dashboard',
                'komponen' => 'Admin/Anggota/Index',
            ],
            'anggota.tambah' => [
                'uri' => 'anggota/tambah',
                'judul' => 'Tambah Anggota',
                'deskripsi' => 'Daftarkan anggota baru perpustakaan.',
                'induk' => 'anggota.index',
                'komponen' => 'Admin/Anggota/Form',
            ],
            'anggota.ubah' => [
                'uri' => 'anggota/{kodeAnggota}/ubah',
                'judul' => 'Ubah Anggota',
                'deskripsi' => 'Perbarui data dan status keanggotaan.',
                'induk' => 'anggota.index',
                'komponen' => 'Admin/Anggota/Form',
            ],
            'buku.index' => [
                'uri' => 'buku',
                'judul' => 'Data Buku & Eksemplar',
                'deskripsi' => 'Kelola data bibliografi buku beserta eksemplar fisiknya.',
                'induk' => 'dashboard',
                'komponen' => 'Admin/Buku/Index',
            ],
            'buku.tambah' => [
                'uri' => 'buku/tambah',
                'judul' => 'Tambah Buku',
                'deskripsi' => 'Tambahkan data buku beserta eksemplarnya.',
                'induk' => 'buku.index',
                'komponen' => 'Admin/Buku/Form',
            ],
            'buku.ubah' => [
                'uri' => 'buku/{kodeBuku}/ubah',
                'judul' => 'Ubah Buku',
                'deskripsi' => 'Perbarui data buku dan tambahkan eksemplar.',
                'induk' => 'buku.index',
                'komponen' => 'Admin/Buku/Form',
            ],
            'peminjaman' => [
                'uri' => 'peminjaman',
                'judul' => 'Peminjaman Buku',
                'deskripsi' => 'Catat peminjaman eksemplar oleh anggota aktif.',
                'induk' => 'dashboard',
                'komponen' => self::KOMPONEN_DISIAPKAN,
            ],
            'pengembalian' => [
                'uri' => 'pengembalian',
                'judul' => 'Pengembalian Buku',
                'deskripsi' => 'Proses pengembalian untuk peminjaman yang masih aktif.',
                'induk' => 'dashboard',
                'komponen' => self::KOMPONEN_DISIAPKAN,
            ],
            'booking.verifikasi' => [
                'uri' => 'booking/verifikasi',
                'judul' => 'Verifikasi Booking',
                'deskripsi' => 'Tinjau dan putuskan pengajuan booking dari anggota.',
                'induk' => 'dashboard',
                'komponen' => self::KOMPONEN_DISIAPKAN,
            ],
            'booking.realisasi' => [
                'uri' => 'booking/realisasi',
                'judul' => 'Realisasi Booking',
                'deskripsi' => 'Proses booking yang telah disetujui menjadi peminjaman.',
                'induk' => 'booking.verifikasi',
                'komponen' => self::KOMPONEN_DISIAPKAN,
            ],
            'laporan' => [
                'uri' => 'laporan',
                'judul' => 'Laporan',
                'deskripsi' => 'Tampilkan dan cetak laporan berdasarkan jenis dan periode.',
                'induk' => 'dashboard',
                'komponen' => self::KOMPONEN_DISIAPKAN,
            ],
        ];
    }

    /** @return array<string, array{uri: string, judul: string, deskripsi: string, induk: ?string, komponen: string}> */
    public static function halamanAnggota(): array
    {
        return [
            'dashboard' => [
                'uri' => 'dashboard',
                'judul' => 'Dashboard',
                'deskripsi' => 'Ringkasan booking dan peminjaman Anda.',
                'induk' => null,
                'komponen' => self::KOMPONEN_DISIAPKAN,
            ],
            'cari-buku' => [
                'uri' => 'cari-buku',
                'judul' => 'Cari Buku',
                'deskripsi' => 'Temukan buku berdasarkan judul, kode buku, atau kategori.',
                'induk' => 'dashboard',
                'komponen' => self::KOMPONEN_DISIAPKAN,
            ],
            'booking.ajukan' => [
                'uri' => 'booking/ajukan',
                'judul' => 'Ajukan Booking',
                'deskripsi' => 'Ajukan booking untuk eksemplar buku yang tersedia.',
                'induk' => 'dashboard',
                'komponen' => self::KOMPONEN_DISIAPKAN,
            ],
            'booking.status' => [
                'uri' => 'booking/status',
                'judul' => 'Status Booking',
                'deskripsi' => 'Pantau status booking yang telah Anda ajukan.',
                'induk' => 'dashboard',
                'komponen' => self::KOMPONEN_DISIAPKAN,
            ],
            'history' => [
                'uri' => 'history',
                'judul' => 'History Peminjaman',
                'deskripsi' => 'Riwayat peminjaman dan pengembalian buku Anda.',
                'induk' => 'dashboard',
                'komponen' => self::KOMPONEN_DISIAPKAN,
            ],
        ];
    }

    /**
     * Struktur menu sidebar. `halaman` merujuk kunci halaman; `aktif` berisi pola
     * kunci yang membuat item disorot; `peran` = peran yang berhak melihat item.
     *
     * @return list<array<string, mixed>>
     */
    private static function strukturMenu(string $peran): array
    {
        if ($peran === self::PERAN_ANGGOTA) {
            $anggota = [self::PERAN_ANGGOTA];

            return [
                ['judul' => 'Menu Utama', 'item' => [
                    ['label' => 'Dashboard', 'ikon' => 'bi-grid-1x2', 'halaman' => 'dashboard', 'aktif' => ['dashboard'], 'peran' => $anggota],
                    ['label' => 'Cari Buku', 'ikon' => 'bi-search', 'halaman' => 'cari-buku', 'aktif' => ['cari-buku'], 'peran' => $anggota],
                    ['label' => 'Booking Pinjam Buku', 'ikon' => 'bi-bookmark-plus', 'peran' => $anggota, 'anak' => [
                        ['label' => 'Ajukan Booking', 'halaman' => 'booking.ajukan', 'aktif' => ['booking.ajukan'], 'peran' => $anggota],
                        ['label' => 'Status Booking', 'halaman' => 'booking.status', 'aktif' => ['booking.status'], 'peran' => $anggota],
                    ]],
                    ['label' => 'History Peminjaman', 'ikon' => 'bi-clock-history', 'halaman' => 'history', 'aktif' => ['history'], 'peran' => $anggota],
                ]],
            ];
        }

        $admin = [self::PERAN_ADMIN];

        return [
            ['judul' => 'Menu Utama', 'item' => [
                ['label' => 'Dashboard', 'ikon' => 'bi-grid-1x2', 'halaman' => 'dashboard', 'aktif' => ['dashboard'], 'peran' => $admin],
            ]],
            ['judul' => 'Data Master', 'item' => [
                ['label' => 'Data Anggota', 'ikon' => 'bi-people', 'halaman' => 'anggota.index', 'aktif' => ['anggota.*'], 'peran' => $admin],
                ['label' => 'Data Buku & Eksemplar', 'ikon' => 'bi-journal-bookmark', 'halaman' => 'buku.index', 'aktif' => ['buku.*'], 'peran' => $admin],
            ]],
            ['judul' => 'Transaksi', 'item' => [
                ['label' => 'Peminjaman Buku', 'ikon' => 'bi-journal-arrow-up', 'halaman' => 'peminjaman', 'aktif' => ['peminjaman'], 'peran' => $admin],
                ['label' => 'Pengembalian Buku', 'ikon' => 'bi-journal-arrow-down', 'halaman' => 'pengembalian', 'aktif' => ['pengembalian'], 'peran' => $admin],
                // Realisasi Booking dibuka dari alur verifikasi; tetap menyorot item ini.
                ['label' => 'Verifikasi Booking', 'ikon' => 'bi-clipboard-check', 'halaman' => 'booking.verifikasi', 'aktif' => ['booking.*'], 'peran' => $admin],
            ]],
            ['judul' => 'Pelaporan', 'item' => [
                ['label' => 'Laporan', 'ikon' => 'bi-file-earmark-text', 'halaman' => 'laporan', 'aktif' => ['laporan'], 'peran' => $admin],
            ]],
        ];
    }

    /** Pola nilai parameter kode (mis. AG-001, BK-010) pada rute berparameter. */
    public const POLA_KODE = '[A-Za-z0-9._-]+';

    public static function punyaParameter(string $uri): bool
    {
        return str_contains($uri, '{');
    }

    public static function namaRute(string $peran, string $halaman): string
    {
        return "demo.{$peran}.{$halaman}";
    }

    public static function url(string $peran, string $halaman): string
    {
        return route(self::namaRute($peran, $halaman));
    }

    /** Peran yang sedang dipratinjau, dibaca dari nama rute aktif (demo.{peran}.*). */
    public static function peranAktif(Request $request): ?string
    {
        $nama = $request->route()?->getName() ?? '';

        foreach (self::PERAN as $peran) {
            if (str_starts_with($nama, "demo.{$peran}.")) {
                return $peran;
            }
        }

        return null;
    }

    /** Kunci halaman aktif (tanpa prefiks rute). */
    private static function kunciHalaman(Request $request, string $peran): ?string
    {
        $nama = $request->route()?->getName() ?? '';
        $prefiks = "demo.{$peran}.";

        return str_starts_with($nama, $prefiks) ? substr($nama, strlen($prefiks)) : null;
    }

    /**
     * Data bersama (shared props) untuk semua halaman Inertia.
     *
     * @return array<string, mixed>
     */
    public static function konteks(Request $request): array
    {
        $peran = self::peranAktif($request);

        return [
            'peran' => $peran,
            'labelPeran' => $peran ? self::LABEL_PERAN[$peran] : null,
            'menu' => $peran ? self::menu($request, $peran) : [],
            'rute' => self::rute($peran),
            'halaman' => $peran ? self::metaHalaman($request, $peran) : null,
            // Route::inertia tidak meneruskan parameter rute; dibagikan di sini.
            // Hanya parameter URI (bukan defaults rute seperti component/props).
            'parameter' => $request->route()
                ? array_intersect_key($request->route()->parameters(), array_flip($request->route()->parameterNames()))
                : [],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function menu(Request $request, string $peran): array
    {
        $aktif = self::kunciHalaman($request, $peran);
        $cocok = fn (array $pola): bool => $aktif !== null
            && collect($pola)->contains(fn (string $p) => Str::is($p, $aktif));

        $bangun = function (array $item) use (&$bangun, $peran, $cocok): ?array {
            if (! in_array($peran, $item['peran'], true)) {
                return null;
            }

            $anak = array_values(array_filter(array_map($bangun, $item['anak'] ?? [])));

            return [
                'label' => $item['label'],
                'ikon' => $item['ikon'] ?? null,
                'url' => isset($item['halaman']) ? self::url($peran, $item['halaman']) : null,
                'aktif' => isset($item['aktif']) ? $cocok($item['aktif']) : collect($anak)->contains('aktif', true),
                'anak' => $anak,
            ];
        };

        return collect(self::strukturMenu($peran))
            ->map(fn (array $grup) => [
                'judul' => $grup['judul'],
                'item' => array_values(array_filter(array_map($bangun, $grup['item']))),
            ])
            ->filter(fn (array $grup) => $grup['item'] !== [])
            ->values()
            ->all();
    }

    /**
     * Peta kunci rute → URL yang boleh dipakai halaman Vue.
     *
     * @return array<string, string>
     */
    public static function rute(?string $peran): array
    {
        $rute = ['login' => route('login')];

        foreach (self::PERAN as $p) {
            $rute["beranda.{$p}"] = self::url($p, 'dashboard');
        }

        if ($peran === null) {
            return $rute;
        }

        foreach (self::halaman($peran) as $kunci => $halaman) {
            $rute[$kunci] = self::punyaParameter($halaman['uri'])
                ? url("/demo/{$peran}/{$halaman['uri']}")
                : self::url($peran, $kunci);
        }

        return $rute;
    }

    /** @return array{judul: string, deskripsi: string, remah: list<array{label: string, url: ?string}>}|null */
    private static function metaHalaman(Request $request, string $peran): ?array
    {
        $kunci = self::kunciHalaman($request, $peran);
        $halaman = self::halaman($peran);

        if ($kunci === null || ! isset($halaman[$kunci])) {
            return null;
        }

        $remah = [];
        for ($k = $kunci; $k !== null; $k = $halaman[$k]['induk']) {
            array_unshift($remah, [
                'label' => $halaman[$k]['judul'],
                'url' => $k === $kunci ? null : self::url($peran, $k),
            ]);
        }

        return [
            'judul' => $halaman[$kunci]['judul'],
            'deskripsi' => $halaman[$kunci]['deskripsi'],
            'remah' => $remah,
        ];
    }
}
