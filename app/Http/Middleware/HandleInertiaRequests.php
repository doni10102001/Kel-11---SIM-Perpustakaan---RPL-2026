<?php

namespace App\Http\Middleware;

use App\Support\AkunSesi;
use App\Support\NavigasiDemo;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Konteks navigasi: peran dari rute aktif, menu, peta rute, dan meta halaman.
            'demo' => fn () => NavigasiDemo::konteks($request),
            // Phase 8A: identitas dari sesi server (bukan akun pratinjau di browser).
            'auth' => fn () => ['akun' => AkunSesi::untuk($request->user())],
            // Pesan satu kali, mis. setelah keluar atau sesi diakhiri karena status akun.
            'flash' => fn () => ['status' => $request->hasSession() ? $request->session()->get('status') : null],
        ];
    }
}
