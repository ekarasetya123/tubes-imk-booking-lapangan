# STRUKTUR MENU LAPANGANKU

Dokumen ini menjelaskan struktur menu dan navigasi aplikasi **LapanganKu** (tugas besar IMKBooking Lapangan), berbagi 3 peran pengguna: **Penyewa**, **Admin**, dan **Pemilik**.

## 1. Halaman Publik (Tanpa Login)

| Halaman | Path | Deskripsi |
|---------|------|-----------|
| Beranda | `public/index.php` | Hero, daftar lapangan aktif, 3 langkah booking |
| Jadwal | `public/jadwal.php` | Pilih tanggal & lapangan, grid slot jam, panel total harga sticky |
| Masuk | `public/login.php` | Form login 2-kolom, tombol demo akun admin |
| Daftar | `public/register.php` | Registrasi akun penyewa baru |
| Keluar | `public/logout.php` | Keluar dari sesi |

## 2. Menu Penyewa

| Halaman | Path | Deskripsi |
|---------|------|-----------|
| Dashboard | `penyewa/dashboard.php` | Ringkasan booking aktif, booking terdekat dengan hitung mundur, tombol Booking Baru |
| Riwayat | `penyewa/riwayat.php` | Daftar semua booking dengan filter status & pencarian, paginasi |
| Detail Booking | `penyewa/booking_detail.php?id=X` | Detail booking, hitung mundur batas bayar, kartu rekening + salin, dropzone bukti transfer, modal batalkan |

Navigasi penyewa diakses setelah login dengan peran `penyewa`.

## 3. Menu Admin

| Halaman | Path | Deskripsi |
|---------|------|-----------|
| Dashboard | `admin/dashboard.php` | Kartu statistik (booking hari ini, menunggu verifikasi, pendapatan, lapangan aktif), tabel perlu tindakan dengan hitung mundur batas bayar |
| Kelola Booking | `admin/booking.php` | Tabel semua booking dengan filter status/tanggal/lapangan & pencarian, verifikasi/tolak pembayaran, modal pratinjau bukti transfer |
| Kelola Lapangan | `admin/lapangan.php` | CRUD data lapangan (nama, jenis, harga, jam buka/tutup, status) |
| Kelola Pengguna | `admin/pengguna.php` | Kelola data pengguna (penyewa, admin, pemilik) |

Navigasi admin diakses setelah login dengan peran `admin`.

## 4. Menu Pemilik

| Halaman | Path | Deskripsi |
|---------|------|-----------|
| Laporan | `pemilik/laporan.php` | Kartu statistik pendapatan, grafik Chart.js pendapatan per hari, tabel rincian booking, tombol cetak |

Navigasi pemilik diakses setelah login dengan peran `pemilik`.

## 5. Alur Navigasi Utama

```
Beranda → Jadwal → Pilih Slot → Detail Booking → Bayar & Unggah Bukti → Tunggu Verifikasi → Selesai
```

Admin memverifikasi penolakan bukti transfer melalui `admin/booking.php`; pemilik memantau pendapatan melalui `pemilik/laporan.php`.

---

*Dokumentasi untuk Tugas Besar IMK — LapanganKu*
