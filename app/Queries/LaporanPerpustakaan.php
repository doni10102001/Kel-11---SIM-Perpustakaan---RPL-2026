<?php

namespace App\Queries;

use App\Models\Book;
use App\Models\BookReturn;
use App\Models\Loan;
use App\Models\Member;
use App\Support\WaktuPerpustakaan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Batch 3 — laporan Admin dari MySQL (SRS 4.6, FR-014/015/029): Daftar Buku,
 * Daftar Anggota, Peminjaman (periode = Tanggal Pinjam), Pengembalian
 * (periode = Tanggal Kembali). Tidak ada format ekspor (TBD-07).
 *
 * - Baris dan RINGKASAN dihitung di server (agregat SQL); browser hanya
 *   menampilkan. Hanya data yang dibutuhkan laporan yang dikirim (mis.
 *   laporan transaksi tanpa kontak anggota dan tanpa daftar akun).
 * - Periode = tanggal kalender WIB → rentang UTC setengah-terbuka
 *   [awal 00:00 WIB, akhir+1 00:00 WIB). Tidak memakai DATE(kolom), sehingga
 *   indeks loaned_at / returned_at dapat dipakai dan batas tengah malam WIB benar.
 * - Tanggal tampilan: instant → kalender WIB; due_date (DATE) apa adanya.
 */
class LaporanPerpustakaan
{
    public const JENIS = ['buku', 'anggota', 'peminjaman', 'pengembalian'];

    /**
     * @param  array{jenis: string, awal: ?string, akhir: ?string}  $filter  sudah divalidasi
     * @return array{jenis: string, baris: list<array<string, mixed>>, ringkasan: list<array{label: string, nilai: int}>}
     */
    public function susun(array $filter): array
    {
        [$mulai, $sebelum] = $this->rentang($filter['awal'] ?? null, $filter['akhir'] ?? null);

        return ['jenis' => $filter['jenis']] + match ($filter['jenis']) {
            'buku' => $this->buku(),
            'anggota' => $this->anggota(),
            'peminjaman' => $this->peminjaman($mulai, $sebelum),
            'pengembalian' => $this->pengembalian($mulai, $sebelum),
        };
    }

    private function buku(): array
    {
        $status = fn (string $s) => ['copies as '.strtolower($s) => fn (Builder $q) => $q->where('status', $s)];
        $baris = Book::query()
            ->withCount(['copies as jumlah_eksemplar', ...$status('Tersedia'), ...$status('Dipesan'), ...$status('Dipinjam')])
            ->orderBy('book_code')
            ->get()
            ->map(fn (Book $b) => [
                'kodeBuku' => $b->book_code,
                'judul' => $b->title,
                'penulis' => $b->author,
                'penerbit' => $b->publisher,
                'tahunTerbit' => (int) $b->publication_year,
                'kategori' => $b->category,
                'jumlahEksemplar' => (int) $b->jumlah_eksemplar,
                'tersedia' => (int) $b->tersedia,
                'dipesan' => (int) $b->dipesan,
                'dipinjam' => (int) $b->dipinjam,
            ])->all();

        $r = DB::table('book_copies')->selectRaw(
            "COUNT(*) AS total, COALESCE(SUM(status = 'Tersedia'), 0) AS tersedia, COALESCE(SUM(status = 'Dipesan'), 0) AS dipesan, COALESCE(SUM(status = 'Dipinjam'), 0) AS dipinjam"
        )->first();

        return [
            'baris' => $this->nomori($baris),
            'ringkasan' => [
                ['label' => 'Jumlah buku', 'nilai' => DB::table('books')->count()],
                ['label' => 'Jumlah eksemplar', 'nilai' => (int) $r->total],
                ['label' => 'Eksemplar Tersedia', 'nilai' => (int) $r->tersedia],
                ['label' => 'Eksemplar Dipesan', 'nilai' => (int) $r->dipesan],
                ['label' => 'Eksemplar Dipinjam', 'nilai' => (int) $r->dipinjam],
            ],
        ];
    }

    private function anggota(): array
    {
        $baris = Member::query()->orderBy('member_code')->get(['member_code', 'name', 'contact', 'status'])
            ->map(fn (Member $a) => ['kodeAnggota' => $a->member_code, 'nama' => $a->name, 'kontak' => $a->contact, 'status' => $a->status->value])
            ->all();
        $r = DB::table('members')->selectRaw("COUNT(*) AS total, COALESCE(SUM(status = 'Aktif'), 0) AS aktif, COALESCE(SUM(status = 'Nonaktif'), 0) AS nonaktif")->first();

        return [
            'baris' => $this->nomori($baris),
            'ringkasan' => [
                ['label' => 'Jumlah anggota', 'nilai' => (int) $r->total],
                ['label' => 'Aktif', 'nilai' => (int) $r->aktif],
                ['label' => 'Nonaktif', 'nilai' => (int) $r->nonaktif],
            ],
        ];
    }

    private function peminjaman(?CarbonImmutable $mulai, ?CarbonImmutable $sebelum): array
    {
        $periode = fn ($q) => $q
            ->when($mulai, fn ($x) => $x->where('loaned_at', '>=', $mulai))
            ->when($sebelum, fn ($x) => $x->where('loaned_at', '<', $sebelum));

        $baris = $periode(Loan::query())
            ->with(['member:id,member_code,name', 'copy:id,book_id,copy_code', 'copy.book:id,title', 'booking:id,booking_code'])
            ->orderBy('loaned_at')->orderBy('loan_code')
            ->get()
            ->map(fn (Loan $p) => [
                'idPeminjaman' => $p->loan_code,
                // Asal booking bila peminjaman hasil realisasi (null = peminjaman langsung).
                'idBooking' => $p->booking?->booking_code,
                'anggota' => ['kodeAnggota' => $p->member->member_code, 'nama' => $p->member->name],
                'buku' => ['judul' => $p->copy->book->title],
                'kodeEksemplar' => $p->copy->copy_code,
                'tanggalPinjam' => WaktuPerpustakaan::tanggalLokal($p->loaned_at),
                'tanggalJatuhTempo' => $p->getRawOriginal('due_date'),
                'status' => $p->status->value,
            ])->all();

        $r = $periode(DB::table('loans'))->selectRaw(
            "COUNT(*) AS total, COALESCE(SUM(status = 'Aktif'), 0) AS aktif, COALESCE(SUM(status = 'Selesai'), 0) AS selesai"
        )->first();

        return [
            'baris' => $this->nomori($baris),
            'ringkasan' => [
                ['label' => 'Jumlah peminjaman', 'nilai' => (int) $r->total],
                ['label' => 'Aktif', 'nilai' => (int) $r->aktif],
                ['label' => 'Selesai', 'nilai' => (int) $r->selesai],
            ],
        ];
    }

    private function pengembalian(?CarbonImmutable $mulai, ?CarbonImmutable $sebelum): array
    {
        $periode = fn ($q) => $q
            ->when($mulai, fn ($x) => $x->where('returned_at', '>=', $mulai))
            ->when($sebelum, fn ($x) => $x->where('returned_at', '<', $sebelum));

        $baris = $periode(BookReturn::query())
            ->with(['loan:id,loan_code,member_id,copy_id,loaned_at', 'loan.member:id,member_code,name', 'loan.copy:id,book_id,copy_code', 'loan.copy.book:id,title', 'staff:id,name,username,role'])
            ->orderBy('returned_at')->orderBy('return_code')
            ->get()
            ->map(fn (BookReturn $k) => [
                'idPengembalian' => $k->return_code,
                'idPeminjaman' => $k->loan->loan_code,
                'anggota' => ['kodeAnggota' => $k->loan->member->member_code, 'nama' => $k->loan->member->name],
                'buku' => ['judul' => $k->loan->copy->book->title],
                'kodeEksemplar' => $k->loan->copy->copy_code,
                'tanggalPinjam' => WaktuPerpustakaan::tanggalLokal($k->loan->loaned_at),
                'tanggalKembali' => WaktuPerpustakaan::tanggalLokal($k->returned_at),
                'pemroses' => ['nama' => $k->staff->nama_tampilan],
            ])->all();

        return [
            'baris' => $this->nomori($baris),
            'ringkasan' => [['label' => 'Jumlah pengembalian', 'nilai' => $periode(DB::table('returns'))->count()]],
        ];
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} batas UTC [mulai, sebelum) */
    private function rentang(?string $awal, ?string $akhir): array
    {
        return [
            $awal ? WaktuPerpustakaan::awalHariLokal($awal)->utc() : null,
            $akhir ? WaktuPerpustakaan::awalHariLokal($akhir)->addDay()->utc() : null,
        ];
    }

    private function nomori(array $baris): array
    {
        return array_map(fn ($r, $i) => ['no' => $i + 1] + $r, $baris, array_keys($baris));
    }
}
