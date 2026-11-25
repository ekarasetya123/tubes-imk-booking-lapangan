<?php
/**
 * penyewa/booking_baru.php — proses booking baru.
 * Pengecekan bentrok dilakukan di dalam transaksi database dengan SELECT ... FOR UPDATE.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('penyewa');
auto_batalkan_lewat_batas_bayar();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/jadwal.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    set_flash('danger', 'Sesi formulir tidak valid. Silakan ulangi dari halaman jadwal.');
    redirect(BASE_URL . '/jadwal.php');
}

$lapanganId = (int)($_POST['lapangan_id'] ?? 0);
$tanggal    = $_POST['tanggal'] ?? '';
$jamDipilih = array_filter(array_map('intval', explode(',', $_POST['jam_dipilih'] ?? '')));
sort($jamDipilih);

// Validasi dasar input
$errors = [];
$lapangan = null;
if ($lapanganId > 0) {
    $stmt = db()->prepare("SELECT * FROM lapangan WHERE id = ? AND status = 'aktif'");
    $stmt->execute([$lapanganId]);
    $lapangan = $stmt->fetch() ?: null;
}
if (!$lapangan) {
    $errors[] = 'Lapangan yang dipilih tidak tersedia.';
}
$ts = strtotime($tanggal);
$minTanggal = date('Y-m-d');
$maksTanggal = date('Y-m-d', strtotime('+30 days'));
if (!$ts || $tanggal !== date('Y-m-d', $ts) || $tanggal < $minTanggal || $tanggal > $maksTanggal) {
    $errors[] = 'Tanggal booking tidak valid. Pilih tanggal hari ini sampai 30 hari ke depan.';
}
if (count($jamDipilih) < 1 || count($jamDipilih) > 3) {
    $errors[] = 'Pilih 1 sampai 3 jam berurutan.';
}
// Harus berurutan
if (!$errors && count($jamDipilih) > 1) {
    for ($i = 1; $i < count($jamDipilih); $i++) {
        if ($jamDipilih[$i] !== $jamDipilih[$i - 1] + 1) {
            $errors[] = 'Jam yang dipilih harus berurutan tanpa jeda.';
            break;
        }
    }
}

$jamMulai = $jamDipilih ? $jamDipilih[0] : 0;
$jamSelesai = $jamDipilih ? end($jamDipilih) + 1 : 0;

if (!$errors && $lapangan) {
    $buka = (int)substr($lapangan['jam_buka'], 0, 2);
    $tutup = (int)substr($lapangan['jam_tutup'], 0, 2);
    if ($jamMulai < $buka || $jamSelesai > $tutup) {
        $errors[] = 'Jam yang dipilih di luar jam operasional lapangan (' .
            sprintf('%02d:00–%02d:00', $buka, $tutup) . ').';
    }
    // Tidak boleh booking jam yang sudah lewat pada hari ini
    if ($tanggal === date('Y-m-d') && $jamSelesai <= (int)date('H') + 0 && $jamMulai <= (int)date('H')) {
        $errors[] = 'Jam yang dipilih sudah lewat. Silakan pilih jam yang masih tersedia hari ini.';
    }
}

$bookingId = null;
if (!$errors) {
    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Kunci baris booking pada lapangan & tanggal ini agar tidak bentrok (FOR UPDATE)
        $stmt = $pdo->prepare(
            "SELECT jam_mulai, jam_selesai FROM booking
             WHERE lapangan_id = ? AND tanggal = ?
               AND status IN ('menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi')
             FOR UPDATE"
        );
        $stmt->execute([$lapanganId, $tanggal]);
        $bentrok = false;
        $pesanBentrok = '';
        foreach ($stmt->fetchAll() as $b) {
            $m = (int)substr($b['jam_mulai'], 0, 2);
            $s = (int)substr($b['jam_selesai'], 0, 2);
            // Bentrok jika rentang beririsan: mulai < selesaiLain && selesai > mulaiLain
            if ($jamMulai < $s && $jamSelesai > $m) {
                $bentrok = true;
                $pesanBentrok = sprintf('Jam %02d:00–%02d:00 sudah dipesan. Silakan pilih jam lain.', $m, $s);
                break;
            }
        }

        if ($bentrok) {
            $pdo->rollBack();
            set_flash('danger', $pesanBentrok);
            redirect(BASE_URL . '/jadwal.php?lapangan=' . $lapanganId . '&tanggal=' . urlencode($tanggal));
        }

        // Buat kode booking unik LK-YYYYMMDD-XXXX
        $kode = 'LK-' . date('Ymd') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        // Pastikan unik (retry seperlunya)
        for ($i = 0; $i < 5; $i++) {
            $cek = $pdo->prepare('SELECT id FROM booking WHERE kode = ?');
            $cek->execute([$kode]);
            if (!$cek->fetch()) break;
            $kode = 'LK-' . date('Ymd') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        $durasi = count($jamDipilih);
        $total = $lapangan['harga_per_jam'] * $durasi;
        $batasBayar = date('Y-m-d H:i:s', time() + BATAS_BAYAR_DETIK);

        $stmt = $pdo->prepare(
            "INSERT INTO booking (kode, user_id, lapangan_id, tanggal, jam_mulai, jam_selesai, durasi, total_harga, status, batas_bayar)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'menunggu_pembayaran', ?)"
        );
        $stmt->execute([
            $kode,
            current_user()['id'],
            $lapanganId,
            $tanggal,
            sprintf('%02d:00:00', $jamMulai),
            sprintf('%02d:00:00', $jamSelesai),
            $durasi,
            $total,
            $batasBayar,
        ]);
        $bookingId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Kode unik bentrok (race condition langka) → pesan ramah
        if ($ex->getCode() === '23000') {
            set_flash('warning', 'Terjadi perebutan slot dengan pengguna lain. Silakan coba lagi.');
        } else {
            set_flash('danger', 'Terjadi kesalahan saat menyimpan booking. Silakan coba lagi.');
        }
        redirect(BASE_URL . '/jadwal.php?lapangan=' . $lapanganId . '&tanggal=' . urlencode($tanggal));
    }
}

if ($errors) {
    set_flash('danger', implode(' ', $errors));
    redirect(BASE_URL . '/jadwal.php?lapangan=' . $lapanganId . '&tanggal=' . urlencode($tanggal));
}

set_flash('success', 'Booking berhasil dibuat dengan kode ' . $kode . '. Silakan selesaikan pembayaran dalam 2 jam.');
redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
