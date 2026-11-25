<?php
/**
 * penyewa/booking_detail.php — detail booking + bukti transfer & batalkan.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('penyewa');
auto_batalkan_lewat_batas_bayar();

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bookingId <= 0) {
    redirect(BASE_URL . '/../penyewa/riwayat.php');
}

$stmt = db()->prepare(
    "SELECT b.*, l.nama AS nama_lapangan, l.jenis AS jenis_lapangan,
            u.email AS email_penyewa
     FROM booking b JOIN lapangan l ON l.id = b.lapangan_id
     JOIN users u ON u.id = b.user_id
     WHERE b.id = ? AND b.user_id = ? LIMIT 1"
);
$stmt->execute([$bookingId, current_user()['id']]);
$booking = $stmt->fetch();
if (!$booking) {
    set_flash('danger', 'Data booking tidak ditemukan.');
    redirect(BASE_URL . '/../penyewa/riwayat.php');
}

// Pembayaran terkait (jika ada)
$stmt = db()->prepare(
    "SELECT p.*, u2.nama AS pemeriksa_oleh
     FROM pembayaran p LEFT JOIN users u2 ON u2.id = p.verified_by
     WHERE p.booking_id = ? ORDER BY p.uploaded_at DESC LIMIT 1"
);
$stmt->execute([$bookingId]);
$pembayaran = $stmt->fetch();

$sisaDetik = sisa_detik_batas_bayar($booking['batas_bayar']);
[$teksStatus, $kelasStatus, $ikonStatus] = status_booking_label($booking['status']);

// Tanggal dapat dibatalkan (belum dikonfirmasi) atau konferminasi >= 3 jam sebelum mulai
$mulai = strtotime($booking['tanggal'] . ' ' . $booking['jam_mulai']);
$bisaBatalkan = ($booking['status'] !== 'dikonfirmasi' && $booking['status'] !== 'ditolak' && $booking['status'] !== 'dibatalkan')
    || ($booking['status'] === 'dikonfirmasi' && $mulai >= time() + 3 * 60 * 60);

// Aksi batalkan (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batalkan') {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/helpers.php';
    require_once __DIR__ . '/../includes/auth.php';
    start_session();
    require_role('penyewa');
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Sesi tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
    }
    if (!$bisaBatalkan) {
        set_flash('danger', 'Booking ini tidak dapat dibatalkan. (Hanya bisa dibatalkan sebelum dikonfirmasi, atau minimal 3 jam sebelum waktu mulai.)');
        redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
    }
    try {
        db()->beginTransaction();
        $pdo = db();
        $stmt = $pdo->prepare(
            "UPDATE booking SET status = 'dibatalkan'
             WHERE id = ? AND user_id = ?
               AND status IN ('menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi')"
        );
        $stmt->execute([$bookingId, current_user()['id']]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            set_flash('danger', 'Gagal membatalkan booking. Status mungkin telah berubah.');
        } else {
            $pdo->commit();
            set_flash('success', 'Booking berhasil dibatalkan.');
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        set_flash('danger', 'Terjadi kesalahan saat membatalkan booking.');
    }
    redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
}

// Aksi unggah bukti (POST upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bukti') {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/helpers.php';
    require_once __DIR__ . '/../includes/auth.php';
    start_session();
    require_role('penyewa');
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Sesi tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
    }
    if ($booking['status'] !== 'menunggu_pembayaran') {
        set_flash('danger', 'Bukti pembayaran hanya bisa diunggah pada status menunggu pembayaran.');
        redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
    }
    $uploadDir = UPLOAD_DIR;
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
    if (!isset($_FILES['bukti']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
        set_flash('danger', 'Silakan pilih file bukti transfer terlebih dahulu.');
        redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
    }
    $file = $_FILES['bukti'];
    $ext = strtolower(strrchr($file['name'], '.'));
    $allowed = ['.jpg', '.jpeg', '.png', '.pdf'];
    if (!in_array($ext, $allowed, true)) {
        set_flash('danger', 'File bukti harus berformat JPG, PNG, atau PDF.');
        redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        set_flash('danger', 'File bukti maksimal 2 MB.');
        redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
    }
    // Validasi MIME sebenarnya via finfo (tidak tergantung ekstensi nama file)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mime, $allowedMime, true)) {
        set_flash('danger', 'Tipe file bukti tidak valid. Mohon unggah ulang file JPG/PNG/PDF asli.');
        redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
    }
    $namaFile = bin2hex(random_bytes(12)) . $ext;
    $tujuan = $uploadDir . DIRECTORY_SEPARATOR . $namaFile;
    if (!move_uploaded_file($file['tmp_name'], $tujuan)) {
        set_flash('danger', 'Gagal menyimpan file bukti. Silakan coba lagi.');
        redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
    }
    try {
        db()->beginTransaction();
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT id FROM pembayaran WHERE booking_id = ? LIMIT 1"
        );
        $stmt->execute([$bookingId]);
        $ada = $stmt->fetch();
        if ($ada) {
            $stmt = $pdo->prepare(
                "UPDATE pembayaran SET bukti_file = ?, catatan_admin = NULL, verified_by = NULL, verified_at = NULL, uploaded_at = CURRENT_TIMESTAMP
                 WHERE booking_id = ?"
            );
            $stmt->execute([$namaFile, $bookingId]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO pembayaran (booking_id, bukti_file) VALUES (?, ?)"
            );
            $stmt->execute([$bookingId, $namaFile]);
        }
        $stmt = $pdo->prepare(
            "UPDATE booking SET status = 'menunggu_verifikasi', batas_bayar = batas_bayar WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$bookingId, current_user()['id']]);
        $pdo->commit();
        set_flash('success', 'Bukti transfer berhasil diunggah. Admin akan memverifikasi segera.');
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        set_flash('danger', 'Gagal menyimpan bukti pembayaran.');
    }
    redirect(BASE_URL . '/../penyewa/booking_detail.php?id=' . $bookingId);
}

$title = 'Detail Booking';
$active = 'riwayat';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="section-title mb-1">Detail Booking</h1>
        <p class="text-muted mb-0">Kode: <strong><?php echo e($booking['kode']); ?></strong> · <?php echo e($booking['nama_lapangan']); ?></p>
    </div>
    <a href="<?php echo BASE_URL; ?>/../penyewa/riwayat.php" class="btn btn-outline-aksi"><i class="bi bi-arrow-left me-1"></i>Kembali ke Riwayat</a>
</div>

<?php if ($booking['status'] === 'menunggu_pembayaran'): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert">
    <i class="bi bi-hourglass-split fs-4" aria-hidden="true"></i>
    <div>
        <div class="fw-semibold">Batas waktu pembayaran</div>
        <div class="fs-5 fw-bold" id="hitung-mundur"></div>
        <div class="small">Unggah bukti transfer dalam waktu 2 jam, atau booking akan otomatis dibatalkan.</div>
    </div>
</div>
<?php endif; ?>

<!-- STEPPER STATUS -->
<div class="stepper mb-4">
    <div class="stepper-item<?php echo in_array('menunggu_pembayaran', ['menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi']) ? ' aktif' : ' selesai'; ?>"><span class="step-ball"><i class="bi bi-circle-fill"></i></span><span class="step-label">Dibuat</span></div>
    <div class="stepper-item<?php echo $booking['status'] === 'menunggu_verifikasi' ? ' aktif' : (in_array($booking['status'], ['dikonfirmasi','ditolak']) ? ' selesai' : ''); ?>"><span class="step-ball"><span class="step-ikonen">£</span></span><span class="step-label">Bayar</span></div>
    <div class="stepper-item<?php echo in_array($booking['status'], ['menunggu_verifikasi','dikonfirmasi','ditolak']) ? ' aktif' : ($booking['status'] === 'dibatalkan' ? '' : ''); ?>"><span class="step-ball"><span class="step-ikonen">v</span></span><span class="step-label">Verifikasi</span></div>
    <div class="stepper-item<?php echo $booking['status'] === 'dikonfirmasi' ? ' aktif' : ''; ?>"><span class="step-ball"><i class="bi bi-check-circle-fill"></i></span><span class="step-label">Selesai</span></div>
</div>
<div class="text-muted small mb-3">Status saat ini:
    <span class="badge-status badge-<?php echo e($kelasStatus); ?>"><i class="bi <?php echo e($ikonStatus); ?>"></i> <?php echo e($teksStatus); ?></span>
</div>

<div class="row g-3">
    <!-- Informasi booking -->
    <div class="col-lg-8 col-xl-9">
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <h3 class="h6 card-subtitle">Ringkasan Pesanan</h3>
                <table class="table table-borderless align-middle">
                    <tr>
                        <td class="fw-semibold" style="width: 35%;">Lapangan</td>
                        <td><?php echo e($booking['nama_lapangan']); ?> (<?php echo e(ucfirst($booking['jenis_lapangan'])); ?>)</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Tanggal</td>
                        <td><?php echo format_tanggal($booking['tanggal']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Waktu</td>
                        <td><?php echo format_jam($booking['jam_mulai']); ?> – <?php echo format_jam($booking['jam_selesai']); ?> (<?php echo (int)$booking['durasi']; ?> jam berurutan)</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Total harga</td>
                        <td><strong><?php echo rupiah($booking['total_harga']); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Pembuat</td>
                        <td><?php echo e(current_user()['nama']); ?> · <?php echo e($booking['email_penyewa']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Tanggal dibuat</td>
                        <td><?php echo date('d M Y, H:i', strtotime($booking['created_at'])); ?> WIB</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Bukti transfer / status pembayaran -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <h3 class="h6 card-subtitle">Pembayaran Transfer Manual</h3>
                <div class="bg-lightest rounded p-3 mb-3">
                    <p class="small text-muted mb-1">Rekening tujuan</p>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="fw-semibold"><?php echo e(BANK_NAMA); ?></span>
                        <span class="fw-bold" style="letter-spacing:.5px;"><?php echo e(BANK_NOREK); ?></span>
                        <span class="small text-muted">a/n <?php echo e(BANK_ATAS_NAMA); ?></span>
                    </div>
                    <div class="mt-2 small"><i class="bi bi-info-circle me-1"></i>Bukti harus berupa foto tangkap layar (JPG/PNG) atau studi kasus (PDF) maksimal 2 MB.</div>
                </div>

                <?php if ($booking['status'] === 'menunggu_pembayaran' || $booking['status'] === 'menunggu_verifikasi'): ?>
                <form method="post" action="booking_detail.php?id=<?php echo $bookingId; ?>" enctype="multipart/form-data" class="mb-3">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="bukti">
                    <div class="mb-3">
                        <label for="bukti" class="form-label"><i class="bi bi-upload me-1"></i>Unggah bukti transfer</label>
                        <input type="file" id="bukti" name="bukti" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                    <button type="submit" class="btn btn-aksi"><i class="bi bi-paperclip me-1"></i>Kirim Bukti</button>
                </form>
                <?php endif; ?>

                <?php if ($pembayaran): ?>
                <div class="pembayaran-info">
                    <h6 class="mb-2">Status Berkas Anda</h6>
                    <ul class="list-unstyled small">
                        <li><strong>File bukti:</strong> <?php echo e(basename($pembayaran['bukti_file'])); ?> · diunggah <?php echo date('d M Y, H:i', $pembayaran['uploaded_at']); ?> WIB</li>
                        <?php if (!empty($pembayaran['verified_by'])): ?>
                        <li><strong>Diverifikasi oleh:</strong> <?php echo e($pembayaran['pemeriksa_oleh']); ?></li>
                        <li><strong>Waktu verifikasi:</strong> <?php echo date('d M Y, H:i', $pembayaran['verified_at']); ?> WIB</li>
                        <?php endif; ?>
                        <?php if ($booking['status'] === 'ditolak' && !empty($pembayaran['catatan_admin'])): ?>
                        <li class="text-danger"><strong>Alasan penolakan:</strong> <?php echo e($pembayaran['catatan_admin']); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Aksi samping -->
    <div class="col-lg-4 col-xl-3">
        <div class="card border-0 shadow-sm sticky-top" style="top:100px;">
            <div class="card-body">
                <h3 class="h6 card-subtitle">Aksi</h3>
                <?php if ($bisaBatalkan): ?>
                <button class="btn btn-ahaya w-100 mb-2" data-bs-toggle="modal" data-bs-target="#modalBatalkan" title="Batalkan booking ini">
                    <i class="bi bi-trash3 me-1"></i>Batalkan Booking
                </button>
                <?php else: ?>
                <button class="btn btn-outline-aksi w-100 mb-2 disabled" title="Tidak dapat dibatalkan pada status ini"><i class="bi bi-trash3 me-1"></i>Batalkan Booking</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal konfirmasi batalkan -->
<div class="modal fade" id="modalBatalkan" tabindex="-1" aria-hidden="true" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="booking_detail.php?id=<?php echo $bookingId; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="batalkan">
            <div class="modal-content">
                <div class="modal-header bg-lightest">
                    <h5 class="modal-title">Batalkan Booking?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p>Yakin ingin membatalkan booking <strong><?php echo e($booking['kode']); ?></strong> di <?php echo e($booking['nama_lapangan']); ?>?</p>
                    <p class="small text-muted">Booking yang sudah diterima tidak dapat dibatalkan di sini.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-aksi" data-bs-dismiss="modal">Tidak</button>
                    <button type="submit" class="btn btn-ahaya">Ya, Batalkan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
