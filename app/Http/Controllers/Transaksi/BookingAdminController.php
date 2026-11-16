<?php

namespace App\Http\Controllers\Transaksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaksi\RealisasiBookingRequest;
use App\Http\Requests\Transaksi\TolakBookingRequest;
use App\Services\Transaksi\LayananBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Batch 2 — verifikasi (setujui/tolak) dan realisasi booking oleh Admin. */
class BookingAdminController extends Controller
{
    public function setujui(Request $request, LayananBooking $layanan, string $kodeBooking): RedirectResponse
    {
        Gate::authorize('kelola-transaksi');
        $booking = $layanan->setujui($kodeBooking, $request->user());

        return redirect()->route('admin.booking.verifikasi')
            ->with('status', "Booking {$booking->booking_code} disetujui.")
            ->with('transaksi', $booking->booking_code);
    }

    public function tolak(TolakBookingRequest $request, LayananBooking $layanan, string $kodeBooking): RedirectResponse
    {
        $booking = $layanan->tolakBooking($kodeBooking, (string) $request->validated('alasanPenolakan'), $request->user());

        return redirect()->route('admin.booking.verifikasi')
            ->with('status', "Booking {$booking->booking_code} ditolak.")
            ->with('transaksi', $booking->booking_code);
    }

    public function realisasikan(RealisasiBookingRequest $request, LayananBooking $layanan, string $kodeBooking): RedirectResponse
    {
        $pinjam = $layanan->realisasikan($kodeBooking, (string) $request->validated('tanggalJatuhTempo'), $request->user());

        return redirect()->route('admin.booking.realisasi')
            ->with('status', "Booking {$kodeBooking} direalisasikan menjadi peminjaman {$pinjam->loan_code}.")
            ->with('transaksi', $pinjam->loan_code);
    }
}
