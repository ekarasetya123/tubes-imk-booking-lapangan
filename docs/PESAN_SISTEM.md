# PESAN SISTEM LAPANGANKU

Dokumen ini mencantumkan pesan sistem (flash dan label status) yang digunakan di seluruh aplikasi LapanganKu, disusun menurut peran pengguna.

## 1. Pesan Flash (Notifikasi setelah Aksi)

| Tipe | Keterangan | Contoh Penggunaan |
|------|------------|-------------------|
| `success` | Aksi berhasil | "Booking berhasil dibuat.", "Bukti transfer berhasil diunggah." |
| `danger` | Kesalahan / gagal | "Email atau kata sandi salah.", "Gagal menyimpan bukti pembayaran." |
| `warning` | Peringatan | "Batas waktu pembayaran telah lewat.", "Hanya booking yang menunggu verifikasi yang dapat diverifikasi." |
| `info` | Informasi | "Tidak ada booking yang dijadwalkan untuk hari ini." |

Pesan flash disimpan di sesi (`$_SESSION['flash']`) dan ditampilkan sekali setelah redirect (PRG pattern).

## 2. Label Status Booking

| Status | Teks | Warna Badge | Ikon | Keterangan |
|--------|------|-------------|------|------------|
| `menunggu_pembayaran` | Menunggu Pembayaran | Kuning (`warning`) | Jam pasir | Menunggu penyewa mengunggah bukti transfer |
| `menunggu_verifikasi` | Menunggu Verifikasi | Biru (`info`) | Berkas centang | Tunggu admin memverifikasi bukti |
| `dikonfirmasi` | Dikonfirmasi | Hijau (`success`) | Lingkaran centang | Pembayaran diverifikasi, booking aktif |
| `ditolak` | Ditolak | Merah (`danger`) | Silang lingkaran | Bukti transfer ditolak admin |
| `dibatalkan` | Dibatalkan | Abu-abu (`secondary`) | Lingkaran garis | Booking dibatalkan penyewa atau sistem |

Label dihasilkan oleh fungsi `status_booking_label()` di `includes/helpers.php`.

## 3. Pesan Validasi Formulir

| Konteks | Pesan |
|---------|-------|
| Login gagal | "Email atau kata sandi salah. Periksa kembali data Anda." |
| CSRF tidak valid | "Sesi formulir tidak valid. Silakan muat ulang halaman lalu coba lagi." |
| Email kosong / tidak valid | "Masukkan alamat email yang valid." |
| Kata sandi kosong | "Masukkan kata sandi Anda." |
| Bukti format salah | "File bukti harus berformat JPG, PNG, atau PDF." |
| Bukti ukuran terlalu besar | "File bukti maksimal 2 MB." |
| Bukti MIME tidak valid | "Tipe file bukti tidak valid. Mohon unggah ulang file JPG/PNG/PDF asli." |
| Batal tidak diperbolehkan | "Booking ini tidak dapat dibatalkan. (Hanya bisa dibatalkan sebelum dikonfirmasi, atau minimal 3 jam sebelum waktu mulai.)" |

## 4. Pesan Batas Waktu Pembayaran

- Penyewa memiliki waktu **2 jam** untuk mengunggah bukti transfer setelah booking dibuat.
- Sistem menampilkan hitung mundur (`HH:MM:SS`) pada halaman detail booking dan dashboard penyewa.
- Jika waktu habis sebelum bukti diunggah, booking secara otomatis berstatus `dibatalkan` (dijalankan oleh `auto_batalkan_lewat_batas_bayar()` di awal setiap halaman terkait).

## 5. Akses Tidak Diizinkan

- Pengguna yang belum login yang mencoba masuk ke halaman tersembunyi akan diarahkan ke `public/login.php`.
- Pengguna dengan peran yang tidak sesuai (mis. penyewa membuka halaman admin) akan ditolak oleh `require_role()`.

---

*Dokumentasi untuk Tugas Besar IMK — LapanganKu*
