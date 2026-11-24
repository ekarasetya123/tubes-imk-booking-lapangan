<?php
/**
 * includes/helpers.php — fungsi helper umum (escaping, format, CSRF, dsb).
 */

/** Escape output HTML agar aman dari XSS. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Format angka sebagai mata uang Rupiah: Rp 150.000 */
function rupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

/** Format tanggal ke dd MMM yyyy (Bahasa Indonesia). */
function format_tanggal(string $tanggal): string
{
    static $bulan = [
        '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
        '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Ags',
        '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des',
    ];
    $ts = strtotime($tanggal);
    if (!$ts) {
        return $tanggal;
    }
    $d = date('d', $ts);
    $m = $bulan[date('m', $ts)] ?? date('m', $ts);
    return $d . ' ' . $m . ' ' . date('Y', $ts);
}

/** Format jam HH:MM dari string jam database (HH:MM:SS). */
function format_jam(string $jam): string
{
    return substr($jam, 0, 5);
}

/** Redirect ke URL lalu hentikan eksekusi. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Buat token CSRF untuk sesi saat ini. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Input hidden berisi token CSRF untuk form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Verifikasi token CSRF dari request POST. */
function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Ambil pesan flash lalu hapus dari sesi. */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash; // ['type' => 'success|danger|warning|info', 'message' => '...']
    }
    return null;
}

/** Simpan pesan flash untuk ditampilkan setelah redirect. */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Cek apakah booking yang masih menunggu pembayaran sudah melewati batas bayar,
 *  lalu ubah statusnya menjadi dibatalkan. Dijalankan di awal request halaman terkait. */
function auto_batalkan_lewat_batas_bayar(): void
{
    db()->exec(
        "UPDATE booking SET status = 'dibatalkan'
         WHERE status = 'menunggu_pembayaran'
           AND batas_bayar < NOW()"
    );
}

/** hasil waktu tersisa (detik) sampai batas bayar; <= 0 berarti sudah lewat. */
function sisa_detik_batas_bayar(?string $batas_bayar): int
{
    if (!$batas_bayar) {
        return 0;
    }
    return strtotime($batas_bayar) - time();
}

/** Label status booking untuk badge (teks + kelas warna Bootstrap). */
function status_booking_label(string $status): array
{
    $map = [
        'menunggu_pembayaran'   => ['Menunggu Pembayaran', 'warning',  'bi-hourglass-split'],
        'menunggu_verifikasi'   => ['Menunggu Verifikasi', 'info',     'bi-file-earmark-check'],
        'dikonfirmasi'          => ['Dikonfirmasi',        'success',  'bi-check-circle'],
        'ditolak'               => ['Ditolak',             'danger',   'bi-x-circle'],
        'dibatalkan'            => ['Dibatalkan',          'secondary','bi-slash-circle'],
    ];
    $default = [$status, 'secondary', 'bi-circle'];
    return $map[$status] ?? $default;
}
