<?php
/**
 * config.example.php — contoh konfigurasi LapanganKu.
 * Salin file ini menjadi config.php lalu isi kredensial sesuai lingkungan Anda.
 * JANGAN commit config.php yang berisi kredensial asli ke git.
 */

// Mode pengembangan: tampilkan semua error saat mengembangkan.
// Matikan saat produksi dengan mengganti ke: error_reporting(0); ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('display_errors', '1');

date_default_timezone_set('Asia/Jakarta');

// Koneksi database (MySQL/MariaDB via XAMPP: user default "root", tanpa password)
define('DB_HOST', 'localhost');
define('DB_NAME', 'lapanganku');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// URL dasar aplikasi (tanpa slash di akhir). Sesuaikan jika nama folder berbeda.
define('BASE_URL', 'http://localhost/lapanganku/public');

// Informasi rekening tujuan transfer manual (ditampilkan ke penyewa)
define('BANK_NAMA', 'Bank BCA');
define('BANK_NOREK', '1234567890');
define('BANK_ATAS_NAMA', 'PT LapanganKu Nusantara');

// Batas waktu pembayaran (dalam detik) — default 2 jam
define('BATAS_BAYAR_DETIK', 2 * 60 * 60);

// Batas unggah bukti pembayaran
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2 MB
define('UPLOAD_DIR', __DIR__ . '/uploads/bukti');
