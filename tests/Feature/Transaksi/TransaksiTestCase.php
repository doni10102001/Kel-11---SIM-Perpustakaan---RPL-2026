<?php

namespace Tests\Feature\Transaksi;

use App\Enums\StatusAnggota;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Member;
use App\Models\User;
use App\Support\WaktuPerpustakaan;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Database\MySqlTestCase;

/**
 * Batch 2 — dasar test transaksi (MySQL uji, di-rollback per test).
 */
abstract class TransaksiTestCase extends MySqlTestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->buatAdmin();
    }

    /** Anggota + akun login (layak masuk). */
    protected function anggotaDenganAkun(string $kode, StatusAnggota $status = StatusAnggota::Aktif): Member
    {
        $akun = $this->buatAkunAnggota(['username' => strtolower($kode).'.uji']);

        return $this->buatAnggota(['member_code' => $kode, 'status' => $status], $akun);
    }

    protected function eksemplarBaru(string $kode = 'EK-T01', string $status = 'Tersedia', ?Book $buku = null): BookCopy
    {
        $buku ??= Book::factory()->create(['book_code' => 'BK-'.$kode, 'title' => "Buku {$kode}"]);

        return BookCopy::factory()->for($buku)->create(['copy_code' => $kode, 'status' => $status]);
    }

    protected function besok(int $hari = 7): string
    {
        return CarbonImmutable::parse(WaktuPerpustakaan::hariIni())->addDays($hari)->format('Y-m-d');
    }

    protected function sebagaiAdmin(?User $admin = null): static
    {
        $this->flushSession();

        return $this->actingAs($admin ?? $this->admin);
    }

    protected function sebagai(Member $anggota): static
    {
        $this->flushSession();

        return $this->actingAs($anggota->user);
    }

    protected function aksiAudit(): array
    {
        return AuditLog::query()->orderBy('id')->pluck('action')->all();
    }

    /** @return array<string, mixed> */
    protected function props(string $url): array
    {
        $props = [];
        $this->get($url)->assertOk()->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    }
}
