<?php

namespace App\Http\Controllers\Transaksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaksi\AjukanBookingRequest;
use App\Services\Transaksi\LayananBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Batch 2 — Anggota mengajukan dan membatalkan booking MILIKNYA. Pemilik
 * selalu `$request->user()->member` (sesi), tidak pernah dari request.
 */
class BookingAnggotaController extends Controller
{
    public function store(AjukanBookingRequest $request, LayananBooking $layanan): RedirectResponse
    {
        $booking = $layanan->ajukan($request->user()->member, $request->isian(), $request->user());

        return redirect()->route('anggota.booking.ajukan')
            ->with('status', "Booking {$booking->booking_code} berhasil diajukan.")
            ->with('transaksi', $booking->booking_code);
    }

    public function batalkan(Request $request, LayananBooking $layanan, string $kodeBooking): RedirectResponse
    {
        Gate::authorize('booking-anggota');
        $booking = $layanan->batalkan($request->user()->member, $kodeBooking, $request->user());

        return redirect()->route('anggota.booking.status')
            ->with('status', "Booking {$booking->booking_code} dibatalkan.")
            ->with('transaksi', $booking->booking_code);
    }
}
