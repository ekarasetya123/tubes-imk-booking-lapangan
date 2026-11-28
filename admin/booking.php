<?php
/**
 * admin/booking.php — kelola semua booking: filter, verifikasi/tolak pembayaran.
 * Admin memverifikasi atau menolak bukti transfer dengan catatan alasan wajib saat ditolak.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('admin');
auto_batalkan_lewat_batas_bayar();

// Verifikasi atau tolak pembayaran (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Sesi tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/../admin/booking.php');
    }
    $action   = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    // Ambil booking
    $stmt = db()->prepare('SELECT * FROM booking WHERE id = ? LIMIT 1');
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    if (!$booking) {
        set_flash('danger', 'Data booking tidak ditemukan.');
        redirect(BASE_URL . '/../admin/booking.php');
    }

    if ($action === 'verifikasi') {
        if ($booking['status'] !== 'menunggu_verifikasi') {
            set_flash('warning', 'Hanya booking yang menunggu verifikasi yang dapat diverifikasi.');
        } else {
            try {
                db()->beginTransaction();
                $pending = db()->prepare("UPDATE booking SET status = 'dikonfirmasi' WHERE id = ? AND status = 'menunggu_verifikasi'");
                $pending->execute([$bookingId]);
                // Tandai pembayaran terverifikasi
                $stmtP = db()->prepare("UPDATE pembayaran SET verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE booking_id = ?");
                $stmtP->execute([current_user()['id'], $bookingId]);
                db()->commit();
                set_flash('success', 'Pembayaran booking ' . e($booking['kode']) . ' berhasil diverifikasi.');
            } catch (PDOException $ex) {
                if (db()->inTransaction()) { db()->rollBack(); }
                set_flash('danger', 'Gagal memverifikasi pembayaran.');
            }
        }
    } elseif ($action === 'tolak') {
        $alasan = trim($_POST['alasan'] ?? '');
        if ($alasan === '') {
            set_flash('danger', 'Alasan penolakan wajib diisi.');
        } elseif ($booking['status'] !== 'menunggu_verifikasi' && $booking['status'] !== 'menunggu_pembayaran') {
            set_flash('warning', 'Booking ini tidak dapat ditolak.');
        } else {
            try {
                db()->beginTransaction();
                $stmtU = db()->prepare("UPDATE booking SET status = 'ditolak' WHERE id = ?");
                $stmtU->execute([$bookingId]);
                // Upsert pembayaran dengan catatan admin
                $stmtP = db()->prepare("SELECT id FROM pembayaran WHERE booking_id = ? LIMIT 1");
                $stmtP->execute([$bookingId]);
                $ada = $stmtP->fetch();
                if ($ada) {
                    $stmtU2 = db()->prepare(
                        "UPDATE pembayaran SET catatan_admin = ?, verified_by = NULL, verified_at = NULL
                         WHERE booking_id = ?"
                    );
                    $stmtU2->execute([$alasan, $bookingId]);
                } else {
                    $stmtI = db()->prepare(
                        "INSERT INTO pembayaran (booking_id, catatan_admin, verified_by, verified_at)
                         VALUES (?, ?, NULL, NULL)"
                    );
                    $stmtI->execute([$bookingId, $alasan]);
                }
                db()->commit();
                set_flash('success', 'Pembayaran booking ' . e($booking['kode']) . ' ditolak.');
            } catch (PDOException $ex) {
                if (db()->inTransaction()) { db()->rollBack(); }
                set_flash('danger', 'Gagal menolak pembayaran.');
            }
        }
    }
    redirect(BASE_URL . '/../admin/booking.php');
}

// Filter
$filterStatus = $_GET['status'] ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';
$filterLapangan = (int)($_GET['lapangan'] ?? 0);
$q = trim($_GET['q'] ?? '');
$halaman = max(1, (int)($_GET['page'] ?? 1));
$batas = 15;
$offset = ($halaman - 1) * $batas;

$where = ['1 = 1'];
$params = [];
if ($filterStatus !== '' && in_array($filterStatus, ['menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi','ditolak','dibatalkan'], true)) {
    $where[] = 'b.status = ?'; $params[] = $filterStatus;
}
if ($filterTanggal !== '') { $where[] = 'b.tanggal = ?'; $params[] = $filterTanggal; }
if ($filterLapangan > 0) { $where[] = 'b.lapangan_id = ?'; $params[] = $filterLapangan; }
if ($q !== '') { $where[] = '(b.kode LIKE ? OR u.nama LIKE ? OR l.nama LIKE ?)';
    $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
$sqlWhere = implode(' AND ', $where);

$stmt = db()->prepare("SELECT COUNT(*) FROM booking b JOIN users u ON u.id = b.user_id JOIN lapangan l ON l.id = b.lapangan_id WHERE $sqlWhere");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalHalaman = max(1, ceil($total / $batas));

$stmt = db()->prepare(
    "SELECT b.*, u.nama AS nama_user, l.nama AS nama_lapangan, l.jenis AS jenis_lapangan,
            pb.bukti_file, pb.catatan_admin
     FROM booking b
     JOIN users u ON u.id = b.user_id
     JOIN lapangan l ON l.id = b.lapangan_id
     LEFT JOIN pembayaran pb ON pb.booking_id = b.id
     WHERE $sqlWhere
     ORDER BY b.created_at DESC
     LIMIT $batas OFFSET $offset"
);
$stmt->execute($params);
$daftarBooking = $stmt->fetchAll();

// Data untuk filter dropdown
$lapanganList = db()->query('SELECT * FROM lapangan ORDER BY jenis, nama')->fetchAll();
$allTanggal = db()->query("SELECT DISTINCT tanggal FROM booking ORDER BY tanggal DESC LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);

$qStr = http_build_query(array_filter([
    'status'    => $filterStatus ?: '',
    'tanggal'   => $filterTanggal ?: '',
    'lapangan'  => $filterLapangan ?: '',
    'q'         => $q ?: '',
]));

$title = 'Kelola Semua Booking';
$active = 'booking';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/../admin/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Booking</li>
    </ol>
</nav>

<h1 class="section-title mb-1">Kelola Semua Booking</h1>
<p class="text-muted mb-4">Verifikasi atau tolak bukti transfer pengajuan pembayaran penyewa.</p>

<form method="get" action="booking.php" class="row g-2 align-items-end mb-4 filtri-booking" aria-label="Filter data booking">
    <div class="col-md-3">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-select" onchange="this.form.submit()">
            <option value="" <?php echo $filterStatus === '' ? 'selected' : ''; ?>>Semua</option>
            <?php foreach (['menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi','ditolak','dibatalkan'] as $s): [$t] = status_booking_label($s); ?>
            <option value="<?php echo $s; ?>" <?php echo $filterStatus === $s ? 'selected' : ''; ?>><?php echo e($t); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label for="tanggal" class="form-label">Tanggal</label>
        <input type="date" id="tanggal" name="tanggal" class="form-control" value="<?php echo e($filterTanggal); ?>" onchange="this.form.submit()">
    </div>
    <div class="col-md-3">
        <label for="lapangan" class="form-label">Lapangan</label>
        <select id="lapangan" name="lapangan" class="form-select" onchange="this.form.submit()">
            <option value="" <?php echo $filterLapangan === 0 ? 'selected' : ''; ?>>Semua</option>
            <?php foreach ($lapanganList as $l): ?>
            <option value="<?php echo (int)$l['id']; ?>" <?php echo $filterLapangan === (int)$l['id'] ? 'selected' : ''; ?>><?php echo e($l['nama']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">&nbsp;</label>
        <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Cari kode / nama" value="<?php echo e($q); ?>">
            <button class="btn btn-outline-aksi" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </div>
</form>

<?php if ($daftarBooking): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle table-booking mb-0">
            <thead>
            <tr>
                <th class="align-middle" style="width: 45px;">#</th>
                <th class="align-middle">Kode / Penyewa</th>
                <th class="align-middle">Lapangan</th>
                <th class="align-middle" style="width: 160px;">Tanggal &amp; Waktu</th>
                <th class="align-middle" style="width: 110px;">Total</th>
                <th class="align-middle" style="width: 140px;">Status</th>
                <th class="align-middle" style="width: 150px;">Bukti Bayar</th>
                <th class="align-middle" style="width: 170px;">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php $no = ($halaman - 1) * $batas + 1; foreach ($daftarBooking as $b):
                [$teks, $kelas, $ikon] = status_booking_label($b['status']);
            ?>
            <tr>
                <td class="align-middle"><?php echo $no++; ?></td>
                <td class="align-middle fw-semibold"><?php echo e($b['kode']); ?><br>
                    <span class="small text-muted"><?php echo e($b['nama_user']); ?></span></td>
                <td class="align-middle"><?php echo e($b['nama_lapangan']); ?><br>
                    <span class="small text-muted text-capitalize"><?php echo e($b['jenis_lapangan']); ?></span></td>
                <td class="align-middle"><?php echo format_tanggal($b['tanggal']); ?> · <?php echo format_jam($b['jam_mulai']); ?>–<?php echo format_jam($b['jam_selesai']); ?></td>
                <td class="align-middle"><strong><?php echo rupiah($b['total_harga']); ?></strong></td>
                <td class="align-middle"><span class="badge-status badge-<?php echo e($kelas); ?>"><?php echo e($teks); ?></span></td>
                <td class="align-middle">
                    <?php if (!empty($b['bukti_file'])): ?>
                    <button class="btn btn-sm btn-outline-aksi" data-bs-toggle="modal" data-bs-target="#modalBukti<?php echo (int)$b['id']; ?>">
                        <i class="bi bi-eye"></i> Lihat
                    </button>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
                <td class="align-middle">
                    <div class="btn-group-vertical btn-group-sm d-grid w-100">
                        <?php if ($b['status'] === 'menunggu_verifikasi' || $b['status'] === 'menunggu_pembayaran'): ?>
                        <button class="btn btn-aksi" data-bs-toggle="modal" data-bs-target="#modalVerifikasi<?php echo (int)$b['id']; ?>">
                            Verifikasi
                        </button>
                        <?php endif; ?>
                        <?php if ($b['status'] === 'menunggu_verifikasi' || $b['status'] === 'menunggu_pembayaran'): ?>
                        <button class="btn btn-bahaya" data-bs-toggle="modal" data-bs-target="#modalTolak<?php echo (int)$b['id']; ?>">
                            Tolak
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>

            <!-- Modal lihat bukti -->
            <?php if (!empty($b['bukti_file'])): ?>
            <div class="modal fade" id="modalBukti<?php echo (int)$b['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0">
                        <div class="modal-header bg-krem">
                            <h5 class="modal-title">Bukti Transfer</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="<?php echo BASE_URL; ?>/../uploads/bukti/<?php echo e($b['bukti_file']); ?>"
                                 class="img-fluid" style="max-height: 300px; object-fit: contain;" alt="Bukti transfer">
                            <?php if (!empty($b['catatan_admin'])): ?>
                            <div class="mt-3 p-3 bg-krem border rounded">
                                <div class="fw-semibold small">Catatan admin:</div>
                                <div class="small"><?php echo e($b['catatan_admin']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-aksi" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Modal verifikasi -->
            <?php if ($b['status'] === 'menunggu_verifikasi' || $b['status'] === 'menunggu_pembayaran'): ?>
            <div class="modal fade" id="modalVerifikasi<?php echo (int)$b['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="post" action="booking.php">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="verifikasi">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">
                        <div class="modal-content border-0">
                            <div class="modal-header bg-krem">
                                <h5 class="modal-title">Verifikasi Bukti?</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                            </div>
                            <div class="modal-body">
                                Yakin ingin mengonfirmasi pembayaran booking <strong><?php echo e($b['kode']); ?></strong>?
                                Status akan menjadi <span class="text-success fw-semibold">Dikonfirmasi</span>.
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-aksi" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-aksi">Ya, Verifikasi</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Modal tolak -->
            <?php if ($b['status'] === 'menunggu_verifikasi' || $b['status'] === 'menunggu_pembayaran'): ?>
            <div class="modal fade" id="modalTolak<?php echo (int)$b['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="post" action="booking.php">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="tolak">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">
                        <div class="modal-content border-0">
                            <div class="modal-header bg-krem">
                                <h5 class="modal-title">Tolak Bukti?</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                            </div>
                            <div class="modal-body">
                                <p>Alasan penolakan wajib diisi.</p>
                                <textarea name="alasan" rows="3" class="form-control" placeholder="Contoh: Bukti transfer tidak terlihat jelas..." required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-aksi" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-bahaya">Ya, Tolak</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalHalaman > 1): ?>
<nav aria-label="Halaman booking" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalHalaman; $i++): ?>
        <li class="page-item<?php echo $i === $halaman ? ' active' : ''; ?>">
            <a class="page-link" href="booking.php?<?php echo $qStr; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php else: ?>
<div class="alert alert-info">Tidak ada data booking yang cocok.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
