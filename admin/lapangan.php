<?php
/**
 * admin/lapangan.php — daftar semua lapangan (hanya admin), tidak bisa dihapus jika ada booking.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('admin');
auto_batalkan_lewat_batas_bayar();

// Nonaktifkan lapangan (POST) — tidak boleh jika sudah punya booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nonaktifkan') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Sesi tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/../admin/lapangan.php');
    }
    $id = (int)($_POST['lapangan_id'] ?? 0);
    $cek = db()->prepare('SELECT COUNT(*) FROM booking WHERE lapangan_id = ?');
    $cek->execute([$id]);
    if ((int)$cek->fetchColumn() > 0) {
        set_flash('danger', 'Lapangan tidak dapat dinonaktifkan karena sudah memiliki booking.');
    } else {
        $stmt = db()->prepare("UPDATE lapangan SET status = 'nonaktif' WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', 'Lapangan berhasil dinonaktifkan.');
    }
    redirect(BASE_URL . '/../admin/lapangan.php');
}

$lapanganList = db()->query(
    "SELECT * FROM lapangan ORDER BY jenis, nama"
)->fetchAll();

$title = 'Kelola Lapangan';
$active = 'lapangan';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/../admin/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Lapangan</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="section-title mb-1">Kelola Data Lapangan</h1>
        <p class="text-muted mb-0">Tambah, ubah, atau nonaktifkan lapangan olahraga.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/../admin/lapangan_form.php" class="btn btn-aksi"><i class="bi bi-plus-lg me-1"></i>Tambah Lapangan</a>
</div>

<?php if ($lapanganList): ?>
<div class="table-responsive">
    <table class="table table-hover align-middle table-lapangan">
        <thead>
        <tr>
            <th class="align-middle" style="width: 50px;">#</th>
            <th class="align-middle">Nama</th>
            <th class="align-middle" style="width: 120px;">Jenis</th>
            <th class="align-middle" style="width: 160px;">Harga / Jam</th>
            <th class="align-middle" style="width: 200px;">Jam Operasional</th>
            <th class="align-middle" style="width: 120px;">Status</th>
            <th class="align-middle" style="width: 180px;">Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php $no = 1; foreach ($lapanganList as $l): ?>
        <tr>
            <td class="align-middle"><?php echo $no++; ?></td>
            <td class="align-middle fw-semibold"><?php echo e($l['nama']); ?></td>
            <td class="align-middle"><span class="badge-jenis"><?php echo e(ucfirst($l['jenis'])); ?></span></td>
            <td class="align-middle"><strong><?php echo rupiah($l['harga_per_jam']); ?></strong></td>
            <td class="align-middle small text-muted">
                <?php echo format_jam($l['jam_buka']); ?>–<?php echo format_jam($l['jam_tutup']); ?>
            </td>
            <td class="align-middle">
                <span class="badge-status badge-<?php echo e($l['status'] === 'aktif' ? 'success' : 'secondary'); ?>">
                    <?php echo e($l['status'] === 'aktif' ? 'Aktif' : 'Nonaktif'); ?>
                </span>
            </td>
            <td class="align-middle">
                <a href="<?php echo BASE_URL; ?>/../admin/lapangan_form.php?id=<?php echo (int)$l['id']; ?>" class="btn btn-sm btn-outline-aksi">Ubah</a>
                <?php if ($l['status'] === 'aktif'): ?>
                <button class="btn btn-sm btn-bahaya" data-bs-toggle="modal" data-bs-target="#modalNonaktif<?php echo (int)$l['id']; ?>">
                    Nonaktifkan
                </button>
                <?php endif; ?>
            </td>
        </tr>

        <div class="modal fade" id="modalNonaktif<?php echo (int)$l['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" action="lapangan.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="nonaktifkan">
                    <input type="hidden" name="lapangan_id" value="<?php echo (int)$l['id']; ?>">
                    <div class="modal-content">
                        <div class="modal-header bg-lightest">
                            <h5 class="modal-title">Nonaktifkan Lapangan?</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            Yakin ingin menonaktifkan <strong><?php echo e($l['nama']); ?></strong>?<br>
                            <span class="small text-muted">Jika sudah pernah dipesan, lapangan tidak dapat dinonaktifkan.</span>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-aksi" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-bahaya">Ya, Nonaktifkan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="alert alert-info">Belum ada data lapangan.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
