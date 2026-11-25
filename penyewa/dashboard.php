<?php
/**
 * penyewa/dashboard.php — ringkasan booking aktif & terdekat bagi penyewa.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('penyewa');
auto_batalkan_lewat_batas_bayar();
$uid = current_user()['id'];

// Booking aktif (belum selesai/ditolak/dibatalkan)
$stmt = db()->prepare(
    "SELECT b.*, l.nama AS nama_lapangan, l.jenis
     FROM booking b JOIN lapangan l ON l.id = b.lapangan_id
     WHERE b.user_id = ? AND b.status IN ('menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi')
     ORDER BY b.tanggal, b.jam_mulai"
);
$stmt->execute([$uid]);
$aktif = $stmt->fetchAll();

// Booking terdekat yang masih aktif
$terdekat = null;
$now = date('Y-m-d H:i:s');
foreach ($aktif as $b) {
    $mulai = $b['tanggal'] . ' ' . $b['jam_mulai'];
    if ($mulai >= date('Y-m-d H:i:00') || $b['status'] !== 'dikonfirmasi') {
        if (!$terdekat || $mulai < $terdekat['tanggal'] . ' ' . $terdekat['jam_mulai']) {
            $terdekat = $b;
        }
    }
}

// Hitung statistik kecil
$totalAktif = count($aktif);
$menungguBayar = count(array_filter($aktif, fn($b) => $b['status'] === 'menunggu_pembayaran'));

$title = 'Dashboard Penyewa';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="section-title mb-1">Halo, <?php echo e(current_user()['nama']); ?> 👋</h1>
        <p class="text-muted mb-0">Ini ringkasan booking lapangan Anda.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/jadwal.php" class="btn btn-aksi"><i class="bi bi-plus-lg me-1"></i>Booking Baru</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-ikon stat-ikon-hijau"><i class="bi bi-calendar-check"></i></div>
            <div><p class="stat-angka"><?php echo $totalAktif; ?></p><p class="stat-label">Booking aktif</p></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-ikon stat-ikon-oranye"><i class="bi bi-hourglass-split"></i></div>
            <div><p class="stat-angka"><?php echo $menungguBayar; ?></p><p class="stat-label">Menunggu pembayaran</p></div>
        </div>
    </div>
</div>

<?php if ($terdekat): ?>
<div class="card mb-4 border-0 shadow-sm card-terdekat">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <p class="text-muted small mb-1"><i class="bi bi-star-fill text-warning me-1"></i>Booking terdekat Anda</p>
            <h2 class="h5 mb-1"><?php echo e($terdekat['nama_lapangan']); ?></h2>
            <p class="mb-0">
                <i class="bi bi-calendar3 me-1"></i><?php echo format_tanggal($terdekat['tanggal']); ?>
                · <i class="bi bi-clock me-1"></i><?php echo format_jam($terdekat['jam_mulai']); ?>–<?php echo format_jam($terdekat['jam_selesai']); ?>
            </p>
        </div>
        <?php [$teks, $kelas, $ikon] = status_booking_label($terdekat['status']); ?>
        <span class="badge-status badge-<?php echo e($kelas); ?>"><i class="bi <?php echo e($ikon); ?>"></i> <?php echo e($teks); ?></span>
    </div>
</div>
<?php endif; ?>

<h2 class="h5 mb-3">Booking aktif</h2>
<?php if ($aktif): ?>
<div class="row g-3">
    <?php foreach ($aktif as $b): [$teks, $kelas, $ikon] = status_booking_label($b['status']); ?>
    <div class="col-md-6">
        <article class="card booking-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <h3 class="h6 mb-1"><?php echo e($b['nama_lapangan']); ?></h3>
                        <p class="small text-muted mb-1">Kode: <strong><?php echo e($b['kode']); ?></strong></p>
                    </div>
                    <span class="badge-status badge-<?php echo e($kelas); ?>"><i class="bi <?php echo e($ikon); ?>"></i> <?php echo e($teks); ?></span>
                </div>
                <p class="small mb-1"><i class="bi bi-calendar3 me-1"></i><?php echo format_tanggal($b['tanggal']); ?>
                    · <?php echo format_jam($b['jam_mulai']); ?>–<?php echo format_jam($b['jam_selesai']); ?>
                    (<?php echo (int)$b['durasi']; ?> jam)</p>
                <p class="small mb-3">Total: <strong><?php echo rupiah($b['total_harga']); ?></strong></p>
                <a href="<?php echo BASE_URL; ?>/../penyewa/booking_detail.php?id=<?php echo (int)$b['id']; ?>" class="btn btn-outline-aksi btn-sm">Lihat Detail <i class="bi bi-arrow-right"></i></a>
            </div>
        </article>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state text-center py-5">
    <i class="bi bi-calendar2-x display-4 text-muted"></i>
    <p class="mt-3 mb-1 fw-semibold">Belum ada booking aktif</p>
    <p class="text-muted small">Pilih jadwal kosong lalu buat booking pertama Anda.</p>
    <a href="<?php echo BASE_URL; ?>/jadwal.php" class="btn btn-aksi mt-2">Lihat Jadwal</a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
