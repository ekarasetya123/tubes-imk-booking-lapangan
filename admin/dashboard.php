<?php
/**
 * admin/dashboard.php — kartu statistik dan daftar booking yang perlu tindakan admin.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('admin');
auto_batalkan_lewat_batas_bayar();

$today = date('Y-m-d');
$bulanIni = date('Y-m');

// Statistik kartu
$totalHariIni = (int)db()->query(
    "SELECT COUNT(*) FROM booking WHERE tanggal = ?", [$today]
)->fetchColumn();

$menungguVerifikasi = (int)db()->query(
    "SELECT COUNT(*) FROM booking WHERE status = 'menunggu_verifikasi'"
)->fetchColumn();

$pendapatanBulan = (float)db()->query(
    "SELECT COALESCE(SUM(total_harga),0) FROM booking
     WHERE status = 'dikonfirmasi' AND DATE_FORMAT(created_at, '%Y-%m') = ?",
    [$bulanIni]
)->fetchColumn();

$lapanganAktif = (int)db()->query(
    "SELECT COUNT(*) FROM lapangan WHERE status = 'aktif'"
)->fetchColumn();

// Booking perlu tindakan (menunggu verifikasi) diurutkan terbaru
$stmt = db()->prepare(
    "SELECT b.*, l.nama AS nama_lapangan, u.nama AS nama_user
     FROM booking b JOIN lapangan l ON l.id = b.lapangan_id
     JOIN users u ON u.id = b.user_id
     WHERE b.status = 'menunggu_verifikasi'
     ORDER BY b.created_at DESC LIMIT 8"
);
$stmt->execute();
$perluTindakan = $stmt->fetchAll();

// Booking hari ini (dikonfirmasi & datang)
$stmt = db()->prepare(
    "SELECT b.*, l.nama AS nama_lapangan, u.nama AS nama_user
     FROM booking b JOIN lapangan l ON l.id = b.lapangan_id
     JOIN users u ON u.id = b.user_id
     WHERE b.tanggal = ? AND b.status = 'dikonfirmasi'
     ORDER BY b.jam_mulai ASC"
);
$stmt->execute([$today]);
$bookingHariIni = $stmt->fetchAll();

$title = 'Dashboard Admin';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="section-title mb-1">Dashboard Admin</h1>
        <p class="text-muted mb-0">Kelola lapangan, verifikasi pembayaran, dan pantau booking.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/../admin/lapangan.php" class="btn btn-aksi"><i class="bi bi-plus-square me-1"></i>Kelola Lapangan</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="stat-card stat-hari-ini">
            <div class="stat-ikon stat-ikon-biru"><i class="bi bi-calendar-today"></i></div>
            <div><p class="stat-angka"><?php echo $totalHariIni; ?></p><p class="stat-label">Booking hari ini</p></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat-card stat-verifikasi">
            <div class="stat-ikon stat-ikon-oranye"><i class="bi bi-file-earmark-check"></i></div>
            <div><p class="stat-angka"><?php echo $menungguVerifikasi; ?></p><p class="stat-label">Menunggu verifikasi</p></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat-card stat-pendapatan">
            <div class="stat-ikon stat-ikon-hijau"><i class="bi bi-cash-coin"></i></div>
            <div><p class="stat-angka"><?php echo rupiah($pendapatanBulan); ?></p><p class="stat-label">Pendapatan bulan ini</p></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat-card stat-lapangan">
            <div class="stat-ikon stat-ikon-hijau"><i class="bi bi-grid-fill"></i></div>
            <div><p class="stat-angka"><?php echo $lapanganAktif; ?></p><p class="stat-label">Lapangan aktif</p></div>
        </div>
    </div>
</div>

<?php if ($perluTindakan): ?>
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 card-subtitle mb-0">Perlu Tindakan Admin</h3>
            <a href="<?php echo BASE_URL; ?>/../admin/booking.php" class="btn btn-sm btn-outline-aksi">Lihat Semua</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle table-booking">
                <thead>
                <tr>
                    <th>Kode</th><th>Penyewa</th><th>Lapangan</th><th>Tanggal &amp; Waktu</th><th>Total</th><th>Batas Bayar</th><th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($perluTindakan as $b): ?>
                <tr>
                    <td><code class="text-monospace fw-semibold"><?php echo e($b['kode']); ?></code></td>
                    <td><?php echo e($b['nama_user']); ?></td>
                    <td><?php echo e($b['nama_lapangan']); ?></td>
                    <td><?php echo format_tanggal($b['tanggal']); ?> · <?php echo format_jam($b['jam_mulai']); ?>–<?php echo format_jam($b['jam_selesai']); ?></td>
                    <td><strong><?php echo rupiah($b['total_harga']); ?></strong></td>
                    <td>
                        <?php if ($b['status'] === 'menunggu_pembayaran'): ?>
                        <div class="small text-muted" style="line-height:1.2;">Batas pembayaran</div>
                        <div class="fw-semibold text-danger hitung-mundur-verif" data-detik="<?php echo (int) sisa_detik_batas_bayar($b['batas_bayar']); ?>">--:--:--</div>
                        <?php else: ?>
                        <span class="badge badge-ringkasan">Siap diverifikasi</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?php echo BASE_URL; ?>/../admin/booking.php" class="btn btn-sm btn-outline-aksi">Verifikasi</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($bookingHariIni): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 card-subtitle mb-0">Booking Hari Ini</h3>
            <span class="badge badge-ringkasan"><?php echo count($bookingHariIni); ?> booking</span>
        </div>
        <div class="row g-3">
            <?php foreach ($bookingHariIni as $b):
            [$teks] = status_booking_label($b['status']); ?>
            <div class="col-md-6 col-lg-4">
                <div class="card booking-card-sm h-100 border-0 shadow-sm">
                    <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fw-semibold small"><?php echo e($b['nama_lapangan']); ?></div>
                            <div class="small text-muted text-capitalize"><?php echo e($b['jenis']); ?></div>
                        </div>
                        <div class="text-center min-w-150">
                            <div class="fw-semibold"><?php echo format_jam($b['jam_mulai']); ?>–<?php echo format_jam($b['jam_selesai']); ?></div>
                            <div class="small text-muted"><?php echo e($b['nama_user']); ?></div>
                        </div>
                        <div class="text-center">
                            <span class="badge-status badge-<?php echo e(array_flip(['menunggu_pembayaran' => 'warning','menunggu_verifikasi' => 'info','dikonfirmasi' => 'success','ditolak' => 'danger','dibatalkan' => 'secondary'])[$b['status']] ?? 'secondary'); ?>"><?php echo e($teks); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info">Tidak ada booking yang dijadwalkan untuk hari ini.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
(function () {
    document.querySelectorAll('.hitung-mundur-verif').forEach(function (el) {
        var total = parseInt(el.getAttribute('data-detik'), 10) || 0;
        function fmt(s) {
            var h = Math.floor(s / 3600);
            var m = Math.floor((s % 3600) / 60);
            var d = s % 60;
            return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (d < 10 ? '0' : '') + d;
        }
        function aktual() {
            if (total <= 0) {
                el.textContent = 'Waktu habis';
                el.classList.add('text-danger');
                return;
            }
            el.textContent = fmt(total);
            total--;
        }
        aktual();
        setInterval(aktual, 1000);
    });
})();
</script>

<style>
.hitung-mundur-verif { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.9rem; }
</style>
