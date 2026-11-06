<?php

use Illuminate\Support\Facades\Route;

// Tahap awal: hanya satu halaman uji integrasi Laravel → Inertia → Vue → Vite.
Route::inertia('/hello/echo', 'HelloEcho')->name('hello.echo');
