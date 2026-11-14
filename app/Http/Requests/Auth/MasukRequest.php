<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 8A — permintaan masuk (username + kata sandi) terhadap tabel `users` MySQL.
 *
 * Keamanan:
 * - Pesan gagal kredensial GENERIK (tidak menyebut username atau kata sandi yang salah).
 * - Username tidak ditemukan tetap menjalankan satu verifikasi hash pengecoh agar
 *   waktu respons tidak membedakan "akun ada" vs "tidak ada".
 * - Alasan status (Admin nonaktif, Anggota nonaktif, relasi tidak valid) baru
 *   diungkap SETELAH kata sandi cocok (perilaku prototipe yang disetujui).
 * - Pembatasan percobaan: 5 kali gagal per (username, IP) per 60 detik; setiap
 *   percobaan yang ditolak dihitung, termasuk penolakan status.
 * - Kata sandi tidak di-trim, tidak disimpan, dan tidak dikembalikan ke form.
 */
class MasukRequest extends FormRequest
{
    public const MAKS_PERCOBAAN = 5;

    public const JENDELA_DETIK = 60;

    public const PESAN_GAGAL = 'Identitas pengguna atau kata sandi tidak sesuai. Periksa kembali, lalu coba lagi.';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.required' => 'Identitas Pengguna wajib diisi.',
            'password.required' => 'Kata Sandi wajib diisi.',
            'username.max' => self::PESAN_GAGAL,
            'password.max' => self::PESAN_GAGAL,
        ];
    }

    /**
     * Verifikasi kredensial dan kelayakan; kembalikan akun yang boleh masuk.
     * Tidak membuat sesi — itu tugas controller.
     *
     * @throws ValidationException
     */
    public function autentikasi(): User
    {
        $this->pastikanTidakDibatasi();

        $sandi = (string) $this->input('password');
        $akun = User::query()->where('username', (string) $this->input('username'))->first();

        if ($akun === null) {
            Hash::check($sandi, self::hashPengecoh());
            $this->tolak(self::PESAN_GAGAL);
        }

        if (! Hash::check($sandi, $akun->password)) {
            $this->tolak(self::PESAN_GAGAL);
        }

        if ($alasan = $akun->alasanTidakBolehMasuk()) {
            $this->tolak($alasan->pesan());
        }

        RateLimiter::clear($this->kunciPembatas());

        return $akun;
    }

    /** @throws ValidationException */
    private function tolak(string $pesan): never
    {
        RateLimiter::hit($this->kunciPembatas(), self::JENDELA_DETIK);

        throw ValidationException::withMessages(['username' => $pesan]);
    }

    /** @throws ValidationException */
    private function pastikanTidakDibatasi(): void
    {
        if (! RateLimiter::tooManyAttempts($this->kunciPembatas(), self::MAKS_PERCOBAAN)) {
            return;
        }

        event(new Lockout($this));

        $detik = RateLimiter::availableIn($this->kunciPembatas());

        throw ValidationException::withMessages([
            'username' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
        ]);
    }

    public function kunciPembatas(): string
    {
        return 'masuk:'.Str::transliterate(Str::lower((string) $this->input('username')).'|'.$this->ip());
    }

    /**
     * Hash bcrypt dari string acak yang tidak disimpan di mana pun; hanya untuk
     * menyamakan waktu verifikasi. Bukan kredensial: tidak ada akun yang memakainya.
     */
    private static function hashPengecoh(): string
    {
        return Cache::rememberForever('autentikasi:hash-pengecoh', fn () => Hash::make(Str::random(40)));
    }
}
