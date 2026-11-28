<?php
/**
 * admin/lapangan_form.php — tambah atau ubah data lapangan (admin).
 * PRG: POST -> redirect -> GET.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('admin');

$edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$lapangan = null;
if ($edit) {
    $stmt = db()->prepare('SELECT * FROM lapangan WHERE id = ? LIMIT 1');
    $stmt->execute([$edit]);
    $lapangan = $stmt->fetch();
    if (!$lapangan) {
        set_flash('danger', 'Data lapangan tidak ditemukan.');
        redirect(BASE_URL . '/../admin/lapangan.php');
    }
}

$errors = [];
$nama = $lapangan['nama'] ?? '';
$jenis = $lapangan['jenis'] ?? 'futsal';
$deskripsi = $lapangan['deskripsi'] ?? '';
$harga = (string)($lapangan['harga_per_jam'] ?? '');
$jamBuka = $lapangan['jam_buka'] ?? '08:00:00';
$jamTutup = $lapangan['jam_tutup'] ?? '22:00:00';
$status = $lapangan['status'] ?? 'aktif';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Sesi tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/../admin/lapangan_form.php' . ($edit ? '?id=' . $edit : ''));
    }
    $nama  = trim($_POST['nama'] ?? '');
    $jenis = $_POST['jenis'] ?? 'futsal';
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga   = $_POST['harga'] ?? '';
    $jamBuka = $_POST['jam_buka'] ?? '08:00:00';
    $jamTutup = $_POST['jam_tutup'] ?? '22:00:00';
    $status  = $_POST['status'] ?? 'aktif';

    if (mb_strlen($nama) < 3) {
        $errors['nama'] = 'Nama lapangan minimal 3 karakter.';
    }
    if (!in_array($jenis, ['futsal', 'badminton'], true)) {
        $errors['jenis'] = 'Jenis lapangan harus dipilih.';
    }
    if (!is_numeric($harga) || (float)$harga <= 0) {
        $errors['harga'] = 'Masukkan harga per jam yang valid (lebih besar dari 0).';
    }
    $tb = strtotime($jamBuka);
    $tt = strtotime($jamTutup);
    if (!$tb || !$tt || $tt <= $tb) {
        $errors['jam_tutup'] = 'Jam tutup harus setelah jam buka.';
    }
    if (!in_array($status, ['aktif', 'nonaktif'], true)) {
        $errors['status'] = 'Status tidak valid.';
    }

    if (!$errors) {
        if ($edit) {
            $stmt = db()->prepare(
                "UPDATE lapangan SET nama = ?, jenis = ?, deskripsi = ?, harga_per_jam = ?,
                 jam_buka = ?, jam_tutup = ?, status = ? WHERE id = ?"
            );
            $stmt->execute([$nama, $jenis, $deskripsi, $harga, $jamBuka, $jamTutup, $status, $edit]);
            set_flash('success', 'Data lapangan berhasil diperbarui.');
        } else {
            $stmt = db()->prepare(
                "INSERT INTO lapangan (nama, jenis, deskripsi, harga_per_jam, jam_buka, jam_tutup, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$nama, $jenis, $deskripsi, $harga, $jamBuka, $jamTutup, $status]);
            set_flash('success', 'Lapangan baru berhasil ditambahkan.');
        }
        redirect(BASE_URL . '/../admin/lapangan.php');
    }
}

$title = $edit ? 'Ubah Lapangan' : 'Tambah Lapangan';
$active = 'lapangan';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/../admin/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/../admin/lapangan.php">Lapangan</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?php echo $title; ?></li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card auth-card border-0 shadow-sm mb-4">
            <div class="card-body p-4 p-md-5">
                <h3 class="h5 section-title mb-1"><?php echo $title; ?></h3>
                <p class="text-muted small mb-4">Isi semua kolom berikut untuk menyimpan data lapangan.</p>
                <form method="post" action="lapangan_form.php<?php echo $edit ? '?id=' . $edit : ''; ?>" novalidate>
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="nama" class="form-label required">Nama Lapangan</label>
                        <input type="text" id="nama" name="nama" class="form-control <?php echo isset($errors['nama']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo e($nama); ?>" placeholder="Contoh: Lapangan Futsal A" required aria-describedby="nama-error">
                        <?php if (isset($errors['nama'])): ?><div class="invalid-feedback" id="nama-error"><?php echo e($errors['nama']); ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Jenis Lapangan</label>
                        <div class="row g-2">
                            <div class="col-auto">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" id="jenisFutsal" name="jenis" value="futsal"
                                           type="radio" <?php echo $jenis === 'futsal' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jenisFutsal"><i class="bi bi-futbol me-1"></i>Futsal</label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" id="jenisBadminton" name="jenis" value="badminton"
                                           type="radio" <?php echo $jenis === 'badminton' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jenisBadminton"><i class="bi bi-feather me-1"></i>Badminton</label>
                                </div>
                            </div>
                        </div>
                        <?php if (isset($errors['jenis'])): ?><div class="invalid-feedback"><?php echo e($errors['jenis']); ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" rows="3" class="form-control"
                                  placeholder="Ceritakan keistimewaan lapangan ini..."><?php echo e($deskripsi); ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-4">
                            <label for="harga" class="form-label required">Harga per Jam</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text">Rp</span>
                                <input type="number" id="harga" name="harga" class="form-control <?php echo isset($errors['harga']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($harga); ?>" min="0" step="1000" required aria-describedby="harga-error">
                                <?php if (isset($errors['harga'])): ?><div class="invalid-feedback mt-1" style="height:auto;"><?php echo e($errors['harga']); ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <label for="jam_buka" class="form-label required">Jam Buka</label>
                            <input type="time" id="jam_buka" name="jam_buka" class="form-control"
                                   value="<?php echo e(substr($jamBuka, 0, 5)); ?>" required>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <label for="jam_tutup" class="form-label required">Jam Tutup</label>
                            <input type="time" id="jam_tutup" name="jam_tutup" class="form-control"
                                   value="<?php echo e(substr($jamTutup, 0, 5)); ?>" required>
                            <?php if (isset($errors['jam_tutup'])): ?><div class="invalid-feedback mt-2"><?php echo e($errors['jam_tutup']); ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label d-block mb-2 required">Status</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" id="statusAktif" name="status" value="aktif"
                                   type="radio" <?php echo $status === 'aktif' ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold text-success" for="statusAktif"><i class="bi bi-check-circle me-1"></i>Aktif</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" id="statusNonaktif" name="status" value="nonaktif"
                                   type="radio" <?php echo $status === 'nonaktif' ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold text-muted" for="statusNonaktif"><i class="bi bi-pause-circle me-1"></i>Nonaktif</label>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-aksi flex-fill"><?php echo $edit ? 'Simpan Perubahan' : 'Tambah Lapangan'; ?></button>
                        <a href="<?php echo BASE_URL; ?>/../admin/lapangan.php" class="btn btn-outline-aksi">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
