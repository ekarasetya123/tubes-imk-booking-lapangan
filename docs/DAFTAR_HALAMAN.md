# DAFTAR HALAMAN LAPANGANKU

Daftar lengkap halaman aplikasi LapanganKu (tugas besar IMK), dikelompokkan berdasarkan peran pengguna.

## A. Halaman Publik (Tanpa Login)

| No | Halaman | Path | Deskripsi |
|----|---------|------|-----------|
| 01 | Beranda | `public/index.php` | Hero, daftar lapangan, 3 langkah booking |
| 02 | Jadwal | `public/jadwal.php` | Pilih tanggal & lapangan, grid slot jam, panel total harga |
| 03 | Masuk | `public/login.php` | Login semua peran, tombol demo akun admin |
| 04 | Daftar | `public/register.php` | Registrasi akun penyewa baru |
| 05 | Keluar | `public/logout.php` | Keluar sesi |

## B. Halaman Penyewa (Setelah Login, peran: penyewa)

| No | Halaman | Path | Deskripsi |
|----|---------|------|-----------|
| 06 | Dashboard Penyewa | `penyewa/dashboard.php` | Ringkasan booking aktif, booking terdekat + hitung mundur |
| 07 | Riwayat Booking | `penyewa/riwayat.php` | Daftar semua booking, filter & pencarian, paginasi |
| 08 | Detail Booking | `penyewa/booking_detail.php?id=X` | Detail, hitung mundur, rekening + salin, dropzone bukti, modal batalkan |

## C. Halaman Admin (Setelah Login, peran: admin)

| No | Halaman | Path | Deskripsi |
|----|---------|------|-----------|
| 09 | Dashboard Admin | `admin/dashboard.php` | Statistik kartu, perlu tindakan dengan hitung mundur, booking hari ini |
| 10 | Kelola Semua Booking | `admin/booking.php` | Tabel booking, filter/pencarian, verifikasi/tolak, modal pratinjau bukti |
| 11 | Kelola Lapangan | `admin/lapangan.php` | CRUD data lapangan |
| 12 | Kelola Pengguna | `admin/pengguna.php` | CRUD data pengguna |

## D. Halaman Pemilik (Setelah Login, peran: pemilik)

| No | Halaman | Path | Deskripsi |
|----|---------|------|-----------|
| 13 | Laporan | `pemilik/laporan.php` | Statistik pendapatan, grafik Chart.js, tabel rincian, cetak |

## E. File Inklusi (Helpers / Template)

| File | Path | Deskripsi |
|------|------|-----------|
| Database | `includes/db.php` | Koneksi PDO ke basis data |
| Helper | `includes/helpers.php` | Escape, format, CSRF, flash, label status |
| Auth | `includes/auth.php` | Sesi, login, logout, role, password hash |
| Flash | `includes/flash.php` | Render pesan flash |
| Header | `includes/header.php` | Template header halaman |
| Footer | `includes/footer.php` | Template footer halaman |

## F. Contoh Kode Booking

```text
LK-20261126-0001
```

Format: `LK-YYYYMMDD-XXXX` (XXXX = urutan 4 digit).

---

*Dokumentasi untuk Tugas Besar IMK — LapanganKu*
