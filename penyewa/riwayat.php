<?php
/**
 * penyewa/riwayat.php — daftar semua booking penyewa dengan filter status, pencarian, dan paginasi.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('penyewa');
auto_batalkan_lewat_batas_bayar();
$uid = current_user()['id'];

// Parameter query
$filterStatus = $_GET['status'] ?? '';
$q            = trim($_GET['q'] ?? '');
$halaman      = max(1, (int)($_GET['page'] ?? 1));
$batas        = 8;
$offset       = ($halaman - 1) * $batas;

// Bangun WHERE
$where = ['b.user_id = ?'];
$params = [$uid];
if ($filterStatus !== '' && in_array($filterStatus, ['menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi','ditolak','dibatalkan'], true)) {
    $where[] = 'b.status = ?';
    $params[] = $filterStatus;
}
if ($q !== '') {
    $where[] = '(b.kode LIKE ? OR l.nama LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$sqlWhere = implode(' AND ', $where);

// Hitung total
$stmt = db()->prepare("SELECT COUNT(*) FROM booking b JOIN lapangan l ON l.id = b.lapangan_id WHERE $sqlWhere");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalHalaman = max(1, ceil($total / $batas));

// Ambil data
$stmt = db()->prepare(
    "SELECT b.*, l.nama AS nama_lapangan, l.jenis AS jenis_lapangan
     FROM booking b JOIN lapangan l ON l.id = b.lapangan_id
     WHERE $sqlWhere
     ORDER BY b.tanggal DESC, b.jam_mulai DESC
     LIMIT $batas OFFSET $offset"
);
$stmt->execute($params);
$bookingList = $stmt->fetchAll();

// URL untuk paginasi
$qStr = http_build_query(array_filter(['status' => $filterStatus ?? '', 'q' => $q ?: '']));

$title = 'Riwayat Booking';
$active = 'riwayat';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/../penyewa/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Riwayat</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="section-title mb-1">Riwayat Booking Saya</h1>
        <p class="text-muted mb-0">Daftar semua booking yang pernah Anda buat di LapanganKu.</p>
    </div>
</div>

<form method="get" action="riwayat.php" class="row g-2 align-items-end mb-4 filtri-riwayat" aria-label="Filter riwayat booking">
    <div class="col-md-4">
        <label for="status" class="form-label required">Status</label>
        <select id="status" name="status" class="form-select" onchange="this.form.submit()">
            <option value="" <?php echo $filterStatus === '' ? 'selected' : ''; ?>>Semua</option>
            <?php foreach (['menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi','ditolak','dibatalkan'] as $s): [$t] = status_booking_label($s); ?>
            <option value="<?php echo $s; ?>" <?php echo $filterStatus === $s ? 'selected' : ''; ?>><?php echo e($t); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-5">
        <label for="q" class="form-label">Cari kode atau nama lapangan</label>
        <div class="input-group">
            <input type="text" id="q" name="q" class="form-control" placeholder="contoh: LK-20260101-0001" value="<?php echo e($q); ?>">
            <button class="btn btn-outline-aksi" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </div>
</form>

<?php if ($bookingList): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle table-riwayat mb-0">
            <thead>
            <tr>
                <th class="align-middle">Kode</th>
                <th class="align-middle">Lapangan</th>
                <th class="align-middle">Tanggal &amp; Waktu</th>
                <th class="align-middle">Durasi</th>
                <th class="align-middle">Total</th>
                <th class="align-middle">Status</th>
                <th class="align-middle" style="width: 150px;">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($bookingList as $b):
                [$teks] = status_booking_label($b['status']);
            ?>
            <tr>
                <td><code class="text-monospace fw-semibold"><?php echo e($b['kode']); ?></code></td>
                <td><?php echo e($b['nama_lapangan']); ?><br><span class="small text-muted text-capitalize"><?php echo e($b['jenis_lapangan']); ?></span></td>
                <td><?php echo format_tanggal($b['tanggal']); ?> · <?php echo format_jam($b['jam_mulai']); ?>–<?php echo format_jam($b['jam_selesai']); ?></td>
                <td><?php echo (int)$b['durasi']; ?> jam</td>
                <td><strong><?php echo rupiah($b['total_harga']); ?></strong></td>
                <td><span class="badge-status badge-<?php echo e(array_flip(['menunggu_pembayaran' => 'warning','menunggu_verifikasi' => 'info','dikonfirmasi' => 'success','ditolak' => 'danger','dibatalkan' => 'secondary'])[$b['status']] ?? 'secondary'); ?>"><?php echo e($teks); ?></span></td>
                <td>
                    <a href="<?php echo BASE_URL; ?>/../penyewa/booking_detail.php?id=<?php echo (int)$b['id']; ?>" class="btn btn-sm btn-outline-aksi">Detail</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalHalaman > 1): ?>
<nav aria-label="Halaman riwayat" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalHalaman; $i++): ?>
        <li class="page-item<?php echo $i === $halaman ? ' active' : ''; ?>">
            <a class="page-link" href="riwayat.php?<?php echo $qStr; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php else: ?>
<div class="empty-state text-center py-5">
    <i class="bi bi-clock-history display-4 text-muted"></i>
    <p class="mt-3 mb-1 fw-semibold">Belum ada booking</p>
    <p class="text-muted small"><?php echo $filterStatus !== '' || $q !== '' ? 'Coba ubah filter atau kata kunci pencarian.' : 'Mulai booking lapangan favorit Anda sekarang.'; ?></p>
    <a href="<?php echo BASE_URL; ?>/jadwal.php" class="btn btn-aksi mt-2">Lihat Jadwal</a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
