/**
 * Buku (data bibliografi) — SRS 6.1 `books` (book_code, title, author,
 * publisher, year, category), Class Diagram `Buku`
 * (kodeBuku, judul, penulis, penerbit, tahunTerbit, kategori).
 *
 * Judul BK-001 dan BK-002 mengikuti Object Diagram. Penulis dan penerbit fiktif.
 * Jumlah eksemplar TIDAK disimpan di sini — dihitung dari bookCopies.js.
 */
import { bekukan } from './util.js';

export const BUKU = bekukan([
    { kodeBuku: 'BK-001', judul: 'Algoritma Dasar', penulis: 'Arief Hidayat', penerbit: 'Cakrawala Ilmu', tahunTerbit: 2021, kategori: 'Informatika' },
    { kodeBuku: 'BK-002', judul: 'Basis Data', penulis: 'Lina Marlina', penerbit: 'Media Informatika Press', tahunTerbit: 2022, kategori: 'Informatika' },
    { kodeBuku: 'BK-003', judul: 'Pemrograman Web dengan Laravel', penulis: 'Teguh Santoso', penerbit: 'Media Informatika Press', tahunTerbit: 2024, kategori: 'Informatika' },
    { kodeBuku: 'BK-004', judul: 'Rekayasa Perangkat Lunak', penulis: 'Wahyu Hermawan', penerbit: 'Cakrawala Ilmu', tahunTerbit: 2020, kategori: 'Informatika' },
    { kodeBuku: 'BK-005', judul: 'Jaringan Komputer', penulis: 'Sri Handayani', penerbit: 'Nusantara Pustaka', tahunTerbit: 2019, kategori: 'Informatika' },
    { kodeBuku: 'BK-006', judul: 'Kalkulus I', penulis: 'Bambang Sutrisno', penerbit: 'Nusantara Pustaka', tahunTerbit: 2018, kategori: 'Matematika' },
    { kodeBuku: 'BK-007', judul: 'Statistika Terapan', penulis: 'Ratna Kartikasari', penerbit: 'Cakrawala Ilmu', tahunTerbit: 2023, kategori: 'Matematika' },
    { kodeBuku: 'BK-008', judul: 'Pengantar Manajemen', penulis: 'Agus Setiawan', penerbit: 'Nusantara Pustaka', tahunTerbit: 2021, kategori: 'Manajemen' },
    { kodeBuku: 'BK-009', judul: 'Bahasa Indonesia untuk Penulisan Ilmiah', penulis: 'Yuliana Sari', penerbit: 'Cakrawala Ilmu', tahunTerbit: 2022, kategori: 'Bahasa' },
    { kodeBuku: 'BK-010', judul: 'Sistem Informasi Manajemen', penulis: 'Joko Purnomo', penerbit: 'Media Informatika Press', tahunTerbit: 2023, kategori: 'Informatika' },
]);
