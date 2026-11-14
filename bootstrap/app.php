<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PastikanAkunAktif;
use App\Http\Middleware\PastikanPeran;
use App\Http\Middleware\WajibGantiKataSandi;
use App\Models\User;
use App\Support\NavigasiDemo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Phase 8A — autentikasi & otorisasi server.
        $middleware->alias([
            'akun.aktif' => PastikanAkunAktif::class,
            'peran' => PastikanPeran::class,
            'wajib.ganti.sandi' => WajibGantiKataSandi::class,
        ]);

        // Tamu yang membuka halaman terlindungi → halaman Masuk.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Akun yang sudah masuk membuka /login → dashboard sesuai peran.
        $middleware->redirectUsersTo(function (Request $request) {
            $akun = $request->user();

            return $akun instanceof User ? NavigasiDemo::tujuanSetelahMasuk($akun) : route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
