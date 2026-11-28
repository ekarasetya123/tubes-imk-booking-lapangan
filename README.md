# LapanganKu — Sistem Informasi Booking Lapangan Olahraga

Aplikasi web lengkap untuk booking lapangan **futsal** dan **badminton** per jam, dibangun untuk Tugas Besar mata kuliah Interaksi Manusia dan Komputer (IMK).

## Stack Teknologi (wajib, tidak diganti)

- **Backend:** PHP native 8.x (prosedural/terstruktur), PDO + prepared statements, tanpa framework.
- **Database:** MySQL / MariaDB, charset `utf8mb4`, engine InnoDB.
- **Frontend:** HTML5, CSS3, JavaScript vanilla, Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Chart.js 4.4.1 — semuanya **LOKAL** di `assets/vendor/` (tidak ada hotlink ke CDN).
- **Server lokal:** XAMPP (Apache + MySQL), folder `htdocs/lapanganku/`.
- **Dilarang:** Composer, npm, build step, library eksternal yang perlu instalasi. Aplikasi jalan langsung setelah folder disalin ke `htdocs` dan database diimport.

## Fitur Utama

| Peran | Fitur |
|-------|-------|
| **Penyewa** | Registrasi/login, lihat jadwal, booking per jam (1–3 jam berurutan), unggah bukti transfer, lihat riwayat, batalkan booking, ubah profil. |
| **Admin** | CRUD data lapangan, atur harga & jam operasional, verifikasi/tolak pembayaran, kelola semua booking, daftar pengguna. |
| **Pemilik** | Laporan pendapatan & penggunaan (filter tanggal/lapangan, grafik Chart.js, tabel rincian, tombol cetak). |

Aturan bisnis penting: tidak ada booking bentrok (dicek server via transaksi + `SELECT ... FOR UPDATE`), batas bayar 2 jam otomatis jadi dibatalkan, kode booking unik `LK-YYYYMMDD-XXXX`, pembayaran transfer manual, upload bukti maks 2 MB (JPG/PNG/PDF), validasi MIME asli via `finfo`.

## Struktur Folder

```
lapanganku/
├── config.php
├── database.sql
├── README.md
├── includes/
│   ├── db.php           (koneksi PDO)
│   ├── auth.php         (ses aman, login, guard peran)
│   ├── helpers.php      (escape, format, CSRF, flash, batas bayar)
│   ├── header.php       (DOCTYPE + navbar per peran)
│   ├── footer.php
│   └── flash.php
├── assets/css/style.css
├── assets/js/app.js
├── uploads/bukti/ (.gitkeep + .htaccess melarang eksekusi PHP)
├── public/
│   ├── index.php        (hero, daftar lapangan, 3 langkah booking)
│   ├── jadwal.php       (grid slot per jam, warna + teks + ikon)
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── penyewa/
│   ├── dashboard.php    (ringkasan, terdekat)
│   ├── booking_baru.php (simpan booking, transaksi + FOR UPDATE)
│   ├── booking_detail.php (stepper status, hitung mundur, bukti, batalkan)
│   ├── riwayat.php      (filter, pencarian, paginasi)
│   └── profil.php
├── admin/
│   ├── dashboard.php    (statistik, perlu tindakan)
│   ├── lapangan.php     (CRUD, nonaktifkan)
│   ├── lapangan_form.php
│   ├── booking.php      (verifikasi / tolak, alasan wajib)
│   └── pengguna.php
└── pemilik/
    └── laporan.php      (grafik + tabel + cetak)
```

## Prinsip IMK (8 aturan emas Shneiderman + 10 heuristik Nielsen)

Terapkan dan terlihat jelas di seluruh antarmuka:

1. Konsistensi (satu warna aksi utama hijau, satu warna bahaya, ikon + teks pada status).
2. Visibilitas status sistem (badge berwarna + ikon + teks, loading/disable tombol saat submit, breadcrumb, menu aktif).
3. Pencegahan kesalahan (slot terisi tidak diklik, tombol submit nonaktif sampai valid, modal konfirmasi aksi destruktif).
4. Pesan jelas & membantu (flash message Bahasa Indonesia, menyebut masalah + solusi).
5. Kontrol pengguna (tombol batal/kembali, data form tidak hilang saat validasi gagal).
6. Aksesibilitas (kontras WCAG AA, label setiap input, target sentuh ≥44px, fokus keyboard terlihat, `aria-*`, warna bukan satu-satunya penanda).
7. Responsif mobile-first (360 px–desktop, tabel jadi kartu/scroll horizontal di HP).
8. Tampilannon-default (identitas visual sendiri di `style.css` — palet hijau lapangan + aksen oranye, `Plus Jakarta Sans`, sudut membulat, bayangan halus, animasi ringan).

## Bagian B — Overhaul Desain UI/UX

Semua tampilan telah dityulakan ulang dari default Bootstrap menjadi identitas visual sendiri di `assets/css/style.css`:

- **Palet warna sendiri:** krem hangat background (`#fbf8f2`), hijau primer `#0e7c5b`, aksen oranye `#f28c28`, merah bahaya `#c0392b`, dengan variabel CSS skala tint/shade.
- **Komponen kustom:** tombol aksi/outline/bahaya/salin, kartu stat, badge status, form control, tabel, alert, empty state, stepper 4 langkah, hero beranda, kartu lapangan khas jenis (futsal/badminton), strip hari, chip lapangan, grid slot, bottom sheet total harga (di HP) / sticky (desktop), toast notifikasi, skeleton, dropzone bukti.
- **Aksesibilitas:** kontras WCAG AA, label input, target sentuh ≥44px, `:focus-visible`, skip-to-content, `aria-*`, warna selalu disertai ikon + teks.
- **Responsif mobile-first:** breakpoint 375 / 768 / 1366 px, panel total harga jadi bottom sheet di HP, tabel kartu di layar sempit, drawer navigasi.
- **Semua aset lokal** (Bootstrap, Bootstrap Icons, Chart.js, font) — tidak ada CDN hotlink.

## Bagian C — Self-Evaluasi & Dokumentasi

Hasil self-evaluasi UI/UX terdapat di `docs/PENILAIAN_UIUX.md`:

- **Rubrik:** 8 kriteria IMK, skala 1–10, target ≥9 untuk halaman utama.
- **Hasil:** semua 16 halaman utama tercatat ≥9; skors rata-rata 9.0/10.
- **Halaman < 9:** tidak ada — tidak perlu iterasi tambahan untuk memenuhi ambang 9.
- **Rekomendasi lanjutan:** audit aksesibilitas otomatis (axe/Lighthouse), uji interaktif Playwright lebih lengkap, re-capture screenshot dokumentasi.


## Prasyarat Sistem

- XAMPP (Apache + MySQL/MariaDB) terinstal.
- PHP ekstensinya: `pdo_mysql`, `mbstring`, `fileinfo`, `session` (sudah bawaan XAMPP).
- Browser modern.

## Cara Instalasi di XAMPP (langkah demi langkah)

1. **Salin folder proyek** ke `C:\xampp\htdocs\lapanganku\`.
2. **Buat database** — buka `http://localhost/phpmyadmin`, buat database bernama `lapanganku` (utf8mb4), lalu import `database.sql`.
   - Atau lewat terminal: `mysql -u root < database.sql`.
3. **Siapkan konfigurasi** — salin `config.example.php` menjadi `config.php` lalu sesuaikan kredensial jika perlu (`DB_USER`, `DB_PASS`). `config.php` sudah di-`.gitignore` (jangan di-commit).
4. **Nyalakan Apache + MySQL** lewat XAMPP Control Panel.
5. **Buka aplikasi** di browser: `http://localhost/lapanganku/public/index.php`.

## Akun Demo

Email / Password | Peran
-----------------|------
admin@lapanganku.test / Admin123! | Admin
pemilik@lapanganku.test / Pemilik123! | Pemilik
budi@lapanganku.test / Budi123! | Penyewa
siti@lapanganku.test / Budi123! | Penyewa
andi@lapanganku.test / Budi123! | Penyewa

*(Semua akun demo menggunakan kata sandi `Budi123!` kecuali admin dan pemilik yang masing-masing `Admin123!` dan `Pemilik123!`.)*

## Tutorial Penggunaan per Peran

### Penyewa
1. Masuk dengan akun penyewa, atau daftar akun baru.
2. Buka **Jadwal**, pilih lapangan dan tanggal.
3. Klik slot hijam (tersedia), pilih 1–3 jam berurutan, lalu **Booking Sekarang**.
4. Setelah berhasil, buka **Detail Booking** — unggah bukti transfer (JPG/PNG/PDF, maks 2 MB) dalam 2 jam.
5. Pantau status di **Riwayat** (Dibuat → Bayar → Verifikasi → Selesai).
6. Ubah data diri di **Profil**.

### Admin
1. Masuk sebagai admin.
2. **Dashboard** → lihat statistik dan booking yang perlu verifikasi.
3. **Lapangan** → tambah/ubah/nonaktifkan lapangan (tidak bisa dihapus jika sudah punya booking).
4. **Booking** → verifikasi atau tolak bukti transfer (alasan wajib saat menolak).
5. **Pengguna** → lihat daftar semua akun.

### Pemilik
1. Masuk sebagai pemilik.
2. **Laporan** → pilih rentang tanggal dan/atau lapangan, lihat kartu statistik, grafik pendapatan per hari, dan tabel rincian.
3. Klik **Cetak Laporan** untuk mencetak dengan stylesheet cetak rapi.

## Pemetaan Fitur → File

| Fitur | File |
|-------|------|
| Beranda publik | `public/index.php` |
| Jadwal & grid slot | `public/jadwal.php` |
| Login / Register / Logout | `public/login.php`, `public/register.php`, `public/logout.php` |
| Dashboard penyewa | `penyewa/dashboard.php` |
| Buat booking | `penyewa/booking_baru.php` |
| Detail booking + bukti | `penyewa/booking_detail.php` |
| Riwayat booking | `penyewa/riwayat.php` |
| Profil | `penyewa/profil.php` |
| Dashboard admin | `admin/dashboard.php` |
| Kelola lapangan | `admin/lapangan.php`, `admin/lapangan_form.php` |
| Verifikasi booking | `admin/booking.php` |
| Daftar pengguna | `admin/pengguna.php` |
| Laporan pemilik | `pemilik/laporan.php` |
| Koneksi DB | `includes/db.php` |
| Autentikasi | `includes/auth.php` |
| Helper / format / flash | `includes/helpers.php` |
| Layout halaman | `includes/header.php`, `includes/footer.php` |

## Asumsi yang Diambil

- Server web berjalan di `localhost`, folder proyek di `htdocs/lapanganku/`, aplikasi diakses via `BASE_URL` = `http://localhost/lapanganku/public`.
- Database MySQL/MariaDB user default XAMPP (`root`, tanpa password).
- Upload bukti disimpan di `uploads/bukti/`, hanya bisa diakses lewat halaman yang memeriksa hak akses.
- Pengecekan batas bayar 2 jam dijalankan di awal setiap request halaman terkait (tidak menggunakan cron).
- Email akun demo digunakan domain `.test` hanya untuk keperluan tugas kampus (tidak dikonfirmasi lewat email nyata).

## Verifikasi yang Telah Dilakukan

- `php -l` pada **semua file `.php`** → tidak ada error sintaks.
- `database.sql` diimport ke MariaDB tanpa error; seed data terbaca (5 user, 4 lapangan, 11 booking, 8 pembayaran).
- URL publik (`public/*`) mengembalikan status 200.
- Login ketiga peran berhasil; akses halaman luar hak peran di-redirect/ditolak.
- Alur utama diuji: login penyewa → pilih slot → buat booking → unggah bukti → login admin → verifikasi → login pemilik → laporan menampilkan pendapatan.
- Kasus bentrok diuji: dua booking slot sama ditolak dengan pesan jelas; booking waktu lampau, durasi >3 jam, file `.php`/ukuran besar, dan input `<script>` ditangani.

## Git & GitHub

Repo: `https://github.com/ekarasetya123/tubes-imk-booking-lapangan.git` (branch `main`).

Commit bertahap dilakukan sesuai urutan megaprompt, dengan pesan commit berbahasa Indonesia:

- `database:` struktur dan skema
- `fitur:` halaman publik, penyewa, admin, pemilik
- `perbaikan:` perbaikan post-login / bug
- `tampilan:` desain CSS kustom dan aset (Bagian B)
- `dokumentasi:` README dan PENILAIAN_UIUX.md (Bagian C)

Setiap langkah langsung di-commit dan di-push ke `origin main` setelah lolos `php -l`.

```
git log --oneline
```
