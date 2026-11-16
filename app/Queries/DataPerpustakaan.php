<?php

namespace App\Queries;

use App\Enums\Peran;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Booking;
use App\Models\BookReturn;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use App\Support\WaktuPerpustakaan;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 8B — query service baca data halaman dari MySQL (Eloquent).
 *
 * Mengembalikan HANYA koleksi yang dibutuhkan satu halaman, dalam bentuk yang
 * sama dengan data prototipe (kodeAnggota, judul, tanggalPinjam, ...) agar
 * komponen Vue tidak perlu didesain ulang.
 *
 * Otorisasi data (bukan sekadar navigasi):
 * - Admin: data operasional seluruh perpustakaan.
 * - Anggota: katalog publik (buku + status eksemplar) dan data MILIKNYA saja.
 *   Identitas Anggota SELALU dari akun sesi (`$akun->member`), tidak pernah dari
 *   parameter request. Tidak ada `pengguna`, kontak/nama Anggota lain, atau
 *   username Admin pada halaman Anggota.
 * - Tidak pernah ada hash kata sandi, remember_token, atau id internal.
 *
 * Tanggal: kolom timestamp UTC dikirim sebagai tanggal kalender WIB (Y-m-d) —
 * sama dengan format prototipe; due_date (DATE) dikirim apa adanya.
 * Relasi dimuat dengan eager loading (tanpa N+1).
 */
class DataPerpustakaan
{
    public const KOLEKSI = ['pengguna', 'anggota', 'buku', 'eksemplar', 'peminjaman', 'pengembalian', 'booking'];

    /** Phase 9B — halaman dengan pencarian buku di MySQL + pagination (peran → kunci halaman). */
    public const HALAMAN_PENCARIAN_BUKU = ['admin' => 'buku.index', 'anggota' => 'cari-buku'];

    public const BUKU_PER_HALAMAN = 20;

    /** Koleksi per halaman Anggota (tanpa `pengguna`). */
    private const KOLEKSI_ANGGOTA = [
        'dashboard' => ['anggota', 'buku', 'eksemplar', 'peminjaman', 'pengembalian', 'booking'],
        'cari-buku' => ['buku', 'eksemplar'],
        'booking.ajukan' => ['anggota', 'buku', 'eksemplar', 'peminjaman', 'booking'],
        'booking.status' => ['anggota', 'buku', 'eksemplar', 'peminjaman', 'booking'],
        'history' => ['anggota', 'buku', 'eksemplar', 'peminjaman', 'pengembalian', 'booking'],
    ];

    /** Koleksi per jenis laporan Admin. */
    private const KOLEKSI_LAPORAN = [
        'buku' => ['buku', 'eksemplar'],
        'anggota' => ['anggota'],
        'peminjaman' => ['pengguna', 'anggota', 'buku', 'eksemplar', 'peminjaman', 'pengembalian'],
        'pengembalian' => ['pengguna', 'anggota', 'buku', 'eksemplar', 'peminjaman', 'pengembalian'],
    ];

    /**
     * @param  array{jenis?: string, awal?: ?string, akhir?: ?string}|null  $laporan  filter Laporan yang sudah tervalidasi
     * @return array<string, list<array<string, mixed>>>
     */
    public function untukHalaman(string $peran, string $halaman, User $akun, ?array $laporan = null): array
    {
        if ($akun->role?->value !== $peran) {
            throw new AuthorizationException('Data halaman ini bukan untuk peran akun.');
        }

        return $peran === Peran::Admin->value
            ? $this->admin($halaman, $laporan)
            : $this->anggota($halaman, $akun);
    }

    // ------------------------------------------------------------------ Admin

    private function admin(string $halaman, ?array $laporan): array
    {
        // Halaman Ubah Kata Sandi tidak memerlukan data perpustakaan.
        if ($halaman === 'kata-sandi') {
            return [];
        }

        if ($halaman !== 'laporan') {
            return $this->muat(self::KOLEKSI, cakupan: null);
        }

        // Laporan: tanpa filter = belum ada laporan yang ditampilkan → tanpa data.
        if (! isset($laporan['jenis'], self::KOLEKSI_LAPORAN[$laporan['jenis']])) {
            return [];
        }

        return $this->muat(self::KOLEKSI_LAPORAN[$laporan['jenis']], cakupan: null, laporan: $laporan);
    }

    // ------------------------------------------------------------------ Anggota

    private function anggota(string $halaman, User $akun): array
    {
        $anggota = $akun->member;
        if ($anggota === null) {
            throw new AuthorizationException('Akun Anggota tidak tertaut ke data Anggota.');
        }

        return $this->muat(self::KOLEKSI_ANGGOTA[$halaman] ?? [], cakupan: $anggota);
    }

    // ------------------------------------------------------------------ Pemuat

    /**
     * @param  list<string>  $koleksi
     * @param  Member|null  $cakupan  null = Admin (semua); Member = hanya milik Anggota ini
     */
    private function muat(array $koleksi, ?Member $cakupan, ?array $laporan = null): array
    {
        $hasil = [];
        $admin = $cakupan === null;

        // Rentang periode Laporan (kalender WIB → UTC), hanya untuk jenis bertanggal.
        [$mulai, $sebelum] = $this->rentang($laporan);
        $jenis = $laporan['jenis'] ?? null;

        $pinjam = in_array('peminjaman', $koleksi, true) ? $this->queryPeminjaman($cakupan) : null;

        $kembali = null;
        if (in_array('pengembalian', $koleksi, true)) {
            $kembali = BookReturn::query()
                ->with(['loan:id,loan_code', 'staff:id,username'])
                ->when($cakupan, fn (Builder $q) => $q->whereHas('loan', fn (Builder $l) => $l->where('member_id', $cakupan->id)))
                ->when($jenis === 'pengembalian', fn (Builder $q) => $this->dalamRentang($q, 'returned_at', $mulai, $sebelum))
                ->orderBy('return_code')
                ->get();
        }

        if ($pinjam !== null) {
            if ($jenis === 'peminjaman') {
                $this->dalamRentang($pinjam, 'loaned_at', $mulai, $sebelum);
            } elseif ($jenis === 'pengembalian') {
                $pinjam->whereIn('id', $kembali->pluck('loan_id'));
            }
            $pinjam = $pinjam->get();
        }

        foreach ($koleksi as $nama) {
            $hasil[$nama] = match ($nama) {
                'pengguna' => $admin ? $this->pengguna() : [],
                'anggota' => $this->anggotaData($cakupan),
                'buku' => Book::query()->orderBy('book_code')->get()->map(fn (Book $b) => $this->barisBuku($b))->all(),
                'eksemplar' => BookCopy::query()->with('book:id,book_code')->orderBy('copy_code')->get()->map(fn (BookCopy $e) => [
                    'kodeEksemplar' => $e->copy_code,
                    'kodeBuku' => $e->book->book_code,
                    'status' => $e->status->value,
                ])->all(),
                'peminjaman' => $pinjam->map(fn (Loan $p) => [
                    'idPeminjaman' => $p->loan_code,
                    'kodeAnggota' => $p->member->member_code,
                    'kodeEksemplar' => $p->copy->copy_code,
                    'tanggalPinjam' => WaktuPerpustakaan::tanggalLokal($p->loaned_at),
                    'tanggalJatuhTempo' => $p->getRawOriginal('due_date'),
                    'diprosesOleh' => $admin ? $p->staff->username : null,
                    'status' => $p->status->value,
                ])->values()->all(),
                'pengembalian' => $kembali->map(fn (BookReturn $k) => [
                    'idPengembalian' => $k->return_code,
                    'idPeminjaman' => $k->loan->loan_code,
                    'tanggalKembali' => WaktuPerpustakaan::tanggalLokal($k->returned_at),
                    'diprosesOleh' => $admin ? $k->staff->username : null,
                ])->values()->all(),
                'booking' => $this->bookingData($cakupan),
            };
        }

        return $hasil;
    }

    /** @return array<string, mixed> */
    private function barisBuku(Book $b): array
    {
        return [
            'kodeBuku' => $b->book_code,
            'judul' => $b->title,
            'penulis' => $b->author,
            'penerbit' => $b->publisher,
            'tahunTerbit' => (int) $b->publication_year,
            'kategori' => $b->category,
        ];
    }

    // ------------------------------------------------------------------ Pencarian buku (Phase 9B)

    public static function halamanPencarianBuku(string $peran, string $halaman): bool
    {
        return (self::HALAMAN_PENCARIAN_BUKU[$peran] ?? null) === $halaman;
    }

    /**
     * Data Buku (Admin) dan Cari Buku (Anggota): pencarian dijalankan MySQL.
     *
     * - Kata kunci `q` dicocokkan pada Kode Buku, Judul, atau Kategori (LIKE
     *   berparameter; wildcard % _ \ di-escape). Tidak peka huruf besar/kecil
     *   mengikuti collation utf8mb4_0900_ai_ci.
     * - `kategori` (opsional) = filter persis.
     * - 20 buku per halaman, urut Kode Buku; halaman di luar rentang → halaman terakhir.
     * - `eksemplar` hanya milik buku di halaman ini, LENGKAP per buku, sehingga
     *   jumlah ketersediaan tetap akurat. Satu query (whereIn), tanpa N+1.
     * - Tidak ada data peminjam/pemesan, kontak, atau username.
     *
     * @param  array{q?: string, kategori?: string, halaman?: int}  $filter  sudah dirapikan controller
     * @return array{data: array<string, list<mixed>>, meta: array<string, mixed>}
     */
    public function cariBuku(string $peran, User $akun, array $filter): array
    {
        if ($akun->role?->value !== $peran || ! isset(self::HALAMAN_PENCARIAN_BUKU[$peran])) {
            throw new AuthorizationException('Data halaman ini bukan untuk peran akun.');
        }
        if ($peran === Peran::Anggota->value && $akun->member === null) {
            throw new AuthorizationException('Akun Anggota tidak tertaut ke data Anggota.');
        }

        $q = (string) ($filter['q'] ?? '');
        $kategori = (string) ($filter['kategori'] ?? '');

        $query = Book::query()
            ->when($q !== '', function (Builder $b) use ($q) {
                $pola = '%'.addcslashes($q, '\\%_').'%';
                $b->where(fn (Builder $w) => $w->where('book_code', 'like', $pola)
                    ->orWhere('title', 'like', $pola)
                    ->orWhere('category', 'like', $pola));
            })
            ->when($kategori !== '', fn (Builder $b) => $b->where('category', $kategori))
            ->orderBy('book_code');

        $hal = (clone $query)->paginate(self::BUKU_PER_HALAMAN, ['*'], 'page', max(1, (int) ($filter['halaman'] ?? 1)));
        if ($hal->isEmpty() && $hal->total() > 0) {
            $hal = (clone $query)->paginate(self::BUKU_PER_HALAMAN, ['*'], 'page', $hal->lastPage());
        }

        $kodeBuku = $hal->getCollection()->pluck('book_code', 'id');
        $eksemplar = BookCopy::query()
            ->whereIn('book_id', $kodeBuku->keys())
            ->orderBy('copy_code')
            ->get(['book_id', 'copy_code', 'status'])
            ->map(fn (BookCopy $e) => [
                'kodeEksemplar' => $e->copy_code,
                'kodeBuku' => $kodeBuku[$e->book_id],
                'status' => $e->status->value,
            ])->all();

        $terfilter = $q !== '' || $kategori !== '';

        return [
            'data' => [
                'buku' => $hal->getCollection()->map(fn (Book $b) => $this->barisBuku($b))->values()->all(),
                'eksemplar' => $eksemplar,
                // Pilihan filter kategori (Cari Buku) — semua kategori, bukan hanya halaman ini.
                'kategori' => Book::query()->distinct()->orderBy('category')->pluck('category')->all(),
            ],
            'meta' => [
                'q' => $q,
                'kategori' => $kategori,
                'halaman' => $hal->currentPage(),
                'perHalaman' => self::BUKU_PER_HALAMAN,
                'halamanTerakhir' => $hal->lastPage(),
                'total' => $hal->total(),
                'totalSemua' => $terfilter ? Book::query()->count() : $hal->total(),
            ],
        ];
    }

    private function queryPeminjaman(?Member $cakupan): Builder
    {
        return Loan::query()
            ->with(['member:id,member_code', 'copy:id,copy_code', 'staff:id,username'])
            ->when($cakupan, fn (Builder $q) => $q->where('member_id', $cakupan->id))
            ->orderBy('loan_code');
    }

    /** @return list<array<string, mixed>> */
    private function anggotaData(?Member $cakupan): array
    {
        return Member::query()
            ->with('user:id,username')
            ->when($cakupan, fn (Builder $q) => $q->whereKey($cakupan->id))
            ->orderBy('member_code')
            ->get()
            ->map(fn (Member $a) => [
                'kodeAnggota' => $a->member_code,
                'idPengguna' => $cakupan === null ? $a->user?->username : null,
                'nama' => $a->name,
                'kontak' => $a->contact,
                'status' => $a->status->value,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function bookingData(?Member $cakupan): array
    {
        $admin = $cakupan === null;

        return Booking::query()
            ->with(['member:id,member_code', 'copy:id,copy_code', 'staff:id,username', 'loan:id,booking_id,loan_code'])
            ->when($cakupan, fn (Builder $q) => $q->where('member_id', $cakupan->id))
            ->orderBy('booking_code')
            ->get()
            ->map(fn (Booking $b) => [
                'idBooking' => $b->booking_code,
                'kodeAnggota' => $b->member->member_code,
                'kodeEksemplar' => $b->copy->copy_code,
                'tanggalBooking' => WaktuPerpustakaan::tanggalLokal($b->submitted_at),
                'status' => $b->status->value,
                'tanggalKeputusan' => $b->decided_at ? WaktuPerpustakaan::tanggalLokal($b->decided_at) : null,
                'diputuskanOleh' => $admin ? $b->staff?->username : null,
                'alasanPenolakan' => $b->rejection_reason,
                'idPeminjaman' => $b->loan?->loan_code,
                // Batch 2: instant UTC (ISO-8601 "Z"); frontend menampilkan WIB.
                'batasPengambilan' => $b->expires_at ? WaktuPerpustakaan::isoUtc($b->expires_at) : null,
                'waktuKedaluwarsa' => $b->expired_at ? WaktuPerpustakaan::isoUtc($b->expired_at) : null,
            ])->all();
    }

    /**
     * Akun untuk halaman Admin. Kunci `idPengguna` = username (bukan id internal).
     * `passwordHash` selalu null: hash TIDAK pernah dikirim ke browser.
     *
     * @return list<array<string, mixed>>
     */
    private function pengguna(): array
    {
        return User::query()->with('member:id,user_id,name,status')->orderBy('username')->get()
            ->map(fn (User $u) => [
                'idPengguna' => $u->username,
                'username' => $u->username,
                'nama' => $u->nama_tampilan,
                'peran' => $u->role->value,
                'statusAkun' => $u->bolehMasuk() ? 'Aktif' : 'Nonaktif',
                'passwordHash' => null,
            ])->all();
    }

    // ------------------------------------------------------------------ Periode

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} batas UTC [mulai, sebelum) */
    private function rentang(?array $laporan): array
    {
        $awal = $laporan['awal'] ?? null;
        $akhir = $laporan['akhir'] ?? null;

        return [
            $awal ? WaktuPerpustakaan::awalHariLokal($awal)->utc() : null,
            $akhir ? WaktuPerpustakaan::awalHariLokal($akhir)->addDay()->utc() : null,
        ];
    }

    private function dalamRentang(Builder $q, string $kolom, ?CarbonImmutable $mulai, ?CarbonImmutable $sebelum): Builder
    {
        return $q
            ->when($mulai, fn (Builder $x) => $x->where($kolom, '>=', $mulai))
            ->when($sebelum, fn (Builder $x) => $x->where($kolom, '<', $sebelum));
    }
}
