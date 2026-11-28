<?php
/**
 * pemilik/laporan.php — laporan pendapatan dan penggunaan lapangan.
 * Filter rentang tanggal dan lapangan, grafik pendapatan per hari (Chart.js),
 * tabel rincian, dan tombol cetak (stylesheet cetak rapi).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('pemilik');
auto_batalkan_lewat_batas_bayar();

// Filter tanggal (default: 30 hari terakhir)
$mulai = $_GET['mulai'] ?? date('Y-m-d', strtotime('-30 days'));
$selesai = $_GET['selesai'] ?? date('Y-m-d');
$lapanganFilter = $_GET['lapangan'] ?? '';

// Validasi tanggal
if ($mulai > $selesai) { $mulai = $selesai; }
if (strtotime($mulai) > time() + 86400) { $mulai = date('Y-m-d'); } // jangan lewat hari ini

// Ambil semua lapangan untuk filter dropdown
$semuaLapangan = db()->query('SELECT * FROM lapangan ORDER BY jenis, nama')->fetchAll();

// Ambil data booking konfirmasi dalam rentang
$where = ['b.status = ?', 'b.tanggal BETWEEN ? AND ?'];
$params = ['dikonfirmasi', $mulai, $selesai];
if ($lapanganFilter !== '') {
    $where[] = 'b.lapangan_id = ?';
    $params[] = $lapanganFilter;
}
$sqlWhere = implode(' AND ', $where);

// Kartu statistik
$totalPendapatan = (float)db_query(
    "SELECT COALESCE(SUM(total_harga),0) FROM booking WHERE $sqlWhere",
    $params
)->fetchColumn();

$jumlahBooking = (int)db_query(
    "SELECT COUNT(*) FROM booking WHERE $sqlWhere",
    $params
)->fetchColumn();

$jamTerpakai = (int)db_query(
    "SELECT COALESCE(SUM(durasi),0) FROM booking WHERE $sqlWhere",
    $params
)->fetchColumn();

// Grafik pendapatan per hari (array tanggal -> total)
$grafik = [];
$tglCursor = strtotime($mulai);
$tglAkhir = strtotime($selesai);
for ($t = $tglCursor; $t <= $tglAkhir; $t += 86400) {
    $grafik[date('Y-m-d', $t)] = 0;
}

$stmt = db()->prepare(
    "SELECT b.tanggal, COALESCE(SUM(b.total_harga),0) AS total
     FROM booking b WHERE $sqlWhere
     GROUP BY b.tanggal ORDER BY b.tanggal ASC"
);
$stmt->execute($params);
foreach ($stmt->fetchAll() as $r) {
    $grafik[$r['tanggal']] = (float)$r['total'];
}

$grafikLabels = array_keys($grafik);
$grafikData   = array_values($grafik);

// Tabel rincian booking dalam rentang
$stmt = db()->prepare(
    "SELECT b.*, l.nama AS nama_lapangan, l.jenis AS jenis_lapangan, u.nama AS nama_user
     FROM booking b
     JOIN lapangan l ON l.id = b.lapangan_id
     JOIN users u ON u.id = b.user_id
     WHERE $sqlWhere
     ORDER BY b.tanggal DESC, b.jam_mulai ASC"
);
$stmt->execute($params);
$detailBooking = $stmt->fetchAll();

$title = 'Laporan';
$active = 'laporan';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/../pemilik/laporan.php">Laporan</a></li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="section-title mb-1">Laporan Pemilik</h1>
        <p class="text-muted mb-0">Pendapatan dan penggunaan lapangan dapat difilter per tanggal maupun lapangan.</p>
    </div>
    <button id="btn-cetak" class="btn btn-outline-aksi btn-lg" onclick="window.print();"><i class="bi bi-printer me-1"></i>Cetak Laporan</button>
</div>

<form method="get" action="laporan.php" class="row g-2 align-items-end mb-4 filtri-laporan" aria-label="Filter laporan">
    <div class="col-md-3">
        <label for="mulai" class="form-label required">Dari Tanggal</label>
        <input type="date" id="mulai" name="mulai" class="form-control" value="<?php echo e($mulai); ?>">
    </div>
    <div class="col-md-3">
        <label for="selesai" class="form-label required">Sampai Tanggal</label>
        <input type="date" id="selesai" name="selesai" class="form-control" value="<?php echo e($selesai); ?>">
    </div>
    <div class="col-md-4">
        <label for="lapangan" class="form-label">Lapangan</label>
        <select id="lapangan" name="lapangan" class="form-select">
            <option value="" <?php echo $lapanganFilter === '' ? 'selected' : ''; ?>>Semua Lapangan</option>
            <?php foreach ($semuaLapangan as $l): ?>
            <option value="<?php echo (int)$l['id']; ?>" <?php echo $lapanganFilter === (string)(int)$l['id'] ? 'selected' : ''; ?>><?php echo e($l['nama']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-aksi w-100">Tampilkan</button>
    </div>
</form>

<!-- KARTU STATISTIK -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-pendapatan">
            <div class="stat-ikon stat-ikon-hijau"><i class="bi bi-cash-coin"></i></div>
            <div><p class="stat-angka"><?php echo rupiah($totalPendapatan); ?></p><p class="stat-label">Total Pendapatan</p></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-booking">
            <div class="stat-ikon stat-ikon-biru"><i class="bi bi-calendar-check"></i></div>
            <div><p class="stat-angka"><?php echo $jumlahBooking; ?></p><p class="stat-label">Jumlah Booking</p></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-jam">
            <div class="stat-ikon stat-ikon-oranye"><i class="bi bi-stopwatch"></i></div>
            <div><p class="stat-angka"><?php echo $jamTerpakai; ?></p><p class="stat-label">Jam Terpakai</p></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- GRAFIK -->
    <div class="col-lg-8 card-grafik">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h3 class="h6 card-subtitle mb-3">Pendapatan per Hari</h3>
                <canvas id="grafikPendapatan" height="220"></canvas>
            </div>
        </div>
        <div class="small text-muted text-center mt-2">
            Pendapatan hanya dihitung dari booking berstatus <strong>Dikonfirmasi</strong>.
        </div>
    </div>

    <!-- TABEL RINCIAN -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top:100px;">
            <div class="card-body p-4">
                <h3 class="h6 card-subtitle mb-3">Tabel Rincian</h3>
                <div class="table-responsive small">
                    <table class="table table-hover align-middle table-detail mb-0">
                        <thead>
                        <tr>
                            <th>Tanggal</th><th>Lapangan</th><th>Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($detailBooking): ?>
                        <?php foreach ($detailBooking as $row): ?>
                        <tr>
                            <td><?php echo format_tanggal($row['tanggal']); ?></td>
                            <td>
                                <?php echo e($row['nama_lapangan']); ?><br>
                                <span class="text-muted text-capitalize"><?php echo e($row['jenis_lapangan']); ?></span>
                            </td>
                            <td class="fw-semibold text-end"><?php echo rupiah($row['total_harga']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Tidak ada data</td></tr>
                        <?php endif; ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="2" class="fw-semibold">Total</th>
                            <th class="fw-semibold text-end"><?php echo rupiah($totalPendapatan); ?></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script src="<?php echo $BASE; ?>/../assets/js/vendor/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var ctx = document.getElementById('grafikPendapatan').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($grafikLabels); ?>,
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: <?php echo json_encode($grafikData); ?>,
                borderColor: '#0e7c5b',
                backgroundColor: 'rgba(14, 124, 91, 0.1)',
                borderWidth: 2,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
});
</script>

<style>
.bg-krem { background: var(--warna-bg-netral); }
</style>
