<?php
/**
 * penyewa/booking_detail.php — detail booking + bukti transfer & batalkan.
 * Poles UI/UX (PROMPT 2 D.2): stepper 4 langkah, hitung mundur batas bayar,
 * kartu rekening dengan tombol salin + toast, dropzone bukti drag-and-drop
 * dengan pratinjau gambar, modal konfirmasi batalkan.
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

$sisaDetik = (int) sisa_detik_batas_bayar($booking['batas_bayar']);
[$teksStatus, $kelasStatus, $ikonStatus] = status_booking_label($booking['status']);

// Tanggal dapat dibatalkan (belum dikonfirmasi) atau dikonfirmasi >= 3 jam sebelum mulai
$mulai = strtotime($booking['tanggal'] . ' ' . $booking['jam_mulai']);
$bisaBatalkan = ($booking['status'] !== 'dikonfirmasi' && $booking['status'] !== 'ditolak' && $booking['status'] !== 'dibatalkan')
    || ($booking['status'] === 'dikonfirmasi' && $mulai >= time() + 3 * 60 * 60);

// Aksi batalkan (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batalkan') {
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
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert" id="alertBatasBayar">
    <i class="bi bi-hourglass-split fs-4" aria-hidden="true"></i>
    <div>
        <div class="fw-semibold">Batas waktu pembayaran</div>
        <div class="fs-5 fw-bold" id="hitung-mundur" data-detik="<?php echo (int) $sisaDetik; ?>">--:--:--</div>
        <div class="small">Unggah bukti transfer dalam waktu 2 jam, atau booking akan otomatis dibatalkan.</div>
    </div>
</div>
<?php endif; ?>

<!-- STEPPER STATUS (4 langkah) -->
<div class="stepper mb-4" role="tablist" aria-label="Progres status booking">
    <!-- 1. Dibuat -->
    <?php
    $kelasStepper1 = '';
    if ($booking['status'] === 'menunggu_pembayaran') {
        $kelasStepper1 = ' aktif';
    } elseif (in_array($booking['status'], ['menunggu_verifikasi', 'dikonfirmasi', 'ditolak', 'dibatalkan'])) {
        $kelasStepper1 = ' selesai';
    }
    ?>
    <div class="stepper-item<?php echo $kelasStepper1; ?>" role="tab" aria-selected="<?php echo $booking['status'] === 'menunggu_pembayaran' ? 'true' : 'false'; ?>">
        <span class="step-ball"><i class="bi bi-circle-fill"></i></span>
        <span class="step-label">Dibuat</span>
    </div>
    <!-- 2. Bayar -->
    <?php
    $kelasStepper2 = '';
    if ($booking['status'] === 'menunggu_verifikasi') {
        $kelasStepper2 = ' aktif';
    } elseif ($booking['status'] === 'ditolak') {
        $kelasStepper2 = ' gagal';
    } elseif (in_array($booking['status'], ['dikonfirmasi', 'dibatalkan'])) {
        $kelasStepper2 = ' selesai';
    }
    ?>
    <div class="stepper-item<?php echo $kelasStepper2; ?>" role="tab" aria-selected="<?php echo $booking['status'] === 'menunggu_verifikasi' ? 'true' : 'false'; ?>">
        <span class="step-ball"><i class="bi bi-credit-card"></i></span>
        <span class="step-label">Bayar</span>
    </div>
    <!-- 3. Verifikasi -->
    <div class="stepper-item<?php
        echo in_array($booking['status'], ['menunggu_verifikasi', 'dikonfirmasi', 'ditolak']) ? ' aktif'
            : ($booking['status'] === 'dikonfirmasi' ? ' selesai' : '');
    ?>" role="tab" aria-selected="<?php echo in_array($booking['status'], ['menunggu_verifikasi', 'dikonfirmasi']) ? 'true' : 'false'; ?>">
        <span class="step-ball"><i class="bi bi-file-earmark-check"></i></span>
        <span class="step-label">Verifikasi</span>
    </div>
    <!-- 4. Selesai -->
    <div class="stepper-item<?php echo $booking['status'] === 'dikonfirmasi' ? ' aktif selesai' : ''; ?>" role="tab" aria-selected="<?php echo $booking['status'] === 'dikonfirmasi' ? 'true' : 'false'; ?>">
        <span class="step-ball"><i class="bi bi-check-circle-fill"></i></span>
        <span class="step-label">Selesai</span>
    </div>
</div>
<div class="text-muted small mb-3">Status saat ini:
    <span class="badge-status badge-<?php echo e($kelasStatus); ?>"><i class="bi <?php echo e($ikonStatus); ?>"></i> <?php echo e($teksStatus); ?></span>
</div>

<div class="row g-3">
    <!-- Informasi booking & pembayaran -->
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
                    <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                        <span class="fw-semibold"><?php echo e(BANK_NAMA); ?></span>
                        <span class="fw-bold" style="letter-spacing:.5px;" id="rekening"><?php echo e(BANK_NOREK); ?></span>
                        <span class="small text-muted">a/n <?php echo e(BANK_ATAS_NAMA); ?></span>
                        <button type="button" class="btn btn-sm btn-outline-aksi btn-salin" id="btnSalin" data-salin="<?php echo e(BANK_NOREK); ?>" title="Salin nomor rekening">
                            <i class="bi bi-clipboard me-1"></i> Salin
                        </button>
                    </div>
                    <div class="mt-2 small"><i class="bi bi-info-circle me-1"></i>Bukti harus berupa foto tangkap layar (JPG/PNG) atau studi kasus (PDF) maksimal 2 MB.</div>
                </div>

                <?php if ($booking['status'] === 'menunggu_pembayaran' || $booking['status'] === 'menunggu_verifikasi'): ?>
                <form method="post" action="booking_detail.php?id=<?php echo $bookingId; ?>" enctype="multipart/form-data" class="mb-3">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="bukti">
                    <div class="mb-3">
                        <label for="bukti" class="form-label required"><i class="bi bi-upload me-1"></i>Unggah bukti transfer</label>
                        <div class="dropzone border rounded-3 p-3 text-center position-relative" id="dropzoneBukti" role="button" tabindex="0" aria-label="Tarik atau pilih file bukti transfer">
                            <input type="file" id="bukti" name="bukti" class="position-absolute w-100 h-100 opacity-0" accept=".jpg,.jpeg,.png,.pdf" required aria-hidden="true" onchange="previewBukti(this)">
                            <div id="dropzoneIkon">
                                <i class="bi bi-cloud-arrow-up-fill" aria-hidden="true"></i>
                                <p>Seret file ke sini atau klik untuk memilih</p>
                                <p class="small text-muted mt-1">JPG, PNG, atau PDF · maks 2 MB</p>
                            </div>
                            <img id="previewBuktiImg" src="" alt="Pratinjau bukti" class="d-none mt-2 rounded-2" style="max-height: 140px; max-width: 100%;">
                        </div>
                        <div class="invalid-feedback d-none" id="buktiError"></div>
                    </div>
                    <button type="submit" class="btn btn-aksi" id="btnKirimBukti"><i class="bi bi-paperclip me-1"></i>Kirim Bukti</button>
                </form>
                <?php endif; ?>

                <?php if ($pembayaran): ?>
                <div class="pembayaran-info mt-3">
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
        <div class="card border-0 shadow-sm sticky-aksi" style="top:100px;">
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
<div class="modal fade" id="modalBatalkan" tabindex="-1" aria-hidden="true" aria-labelledby="modalBatalkanLabel">
    <div class="modal-dialog">
        <form method="post" action="booking_detail.php?id=<?php echo $bookingId; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="batalkan">
            <div class="modal-content">
                <div class="modal-header bg-lightest">
                    <h5 class="modal-title" id="modalBatalkanLabel">Batalkan Booking?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p>Yakin ingin membatalkan booking <strong><?php echo e($booking['kode']); ?></strong> di <?php echo e($booking['nama_lapangan']); ?>?</p>
                    <p class="small text-muted">Booking yang sudah dikonfirmasi tidak dapat dibatalkan di sini.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-aksi" data-bs-dismiss="modal">Tidak</button>
                    <button type="submit" class="btn btn-ahaya">Ya, Batalkan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Toast notifikasi -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
/* Hitung mundur batas pembayaran */
(function () {
    var el = document.getElementById('hitung-mundur');
    if (!el) { return; }
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
            el.parentElement.querySelector('.small') && (el.parentElement.querySelector('.small').textContent = 'Batas pembayaran telah lewat.');
            return;
        }
        el.textContent = fmt(total);
        total--;
    }
    aktual();
    setInterval(aktual, 1000);
})();

/* Salin nomor rekening ke clipboard + toast */
(function () {
    var btn = document.getElementById('btnSalin');
    var rekening = btn ? btn.getAttribute('data-salin') : null;
    if (!btn || !rekening) { return; }
    btn.addEventListener('click', function () {
        navigator.clipboard.writeText(rekening).then(function () {
            tampilkanToast('Nomor rekening disalin: ' + rekening, 'success');
        });
    });
})();

/* Pratinjau gambar bukti saat dipilih */
function previewBukti(input) {
    var dropzone = document.getElementById('dropzoneBukti');
    var ikon = document.getElementById('dropzoneIkon');
    var img = document.getElementById('previewBuktiImg');
    var file = input && input.files && input.files[0];
    if (!file) { return; }
    var url = URL.createObjectURL(file);
    img.src = url;
    img.classList.remove('d-none');
    if (ikon) { ikon.style.display = 'none'; }
    if (dropzone) { dropzone.classList.add('kotak-terisi'); }
}

/* Toast sederhana */
function tampilkanToast(pesan, jenis) {
    var container = document.getElementById('toastContainer');
    if (!container) { return; }
    var el = document.createElement('div');
    el.className = 'toast-lapanganku align-items-center text-white border-0 ' + (jenis === 'success' ? 'bg-success' : 'bg-danger');
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'assertive');
    el.innerHTML = '<div class="d-flex"><div class="toast-body">' + pesan + '</div><button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="toast" aria-label="Tutup"></button></div>';
    container.appendChild(el);
    var bsToast = new bootstrap.Toast(el, { autohide: true, delay: 4000 });
    bsToast.show();
    el.addEventListener('hidden.bs.toast', function () { el.remove(); });
}
</script>

<style>
/* Khusus halaman ini */
.btn-salin { font-size: 0.85rem; padding: 0.25rem 0.6rem; }
.stepper-item.gagal .step-ball { background: var(--warna-bahaya); border-color: var(--warna-bahaya); color: #fff; }
.stepper-item.gagal .step-ball i { color: #fff; }
.stepper-item.gagal .step-label { color: var(--warna-bahaya); }
.kotak-terisi { background: #f0fdf4 !important; border-color: var(--warna-primer) !important; }
.sticky-aksi { position: sticky; top: 100px; }
</style>
