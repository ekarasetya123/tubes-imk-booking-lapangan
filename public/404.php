<?php
/**
 * public/404.php — halaman tidak ditemukan, ramah dengan tombol kembali.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
$title = 'Halaman Tidak Ditemukan';
require __DIR__ . '/../includes/header.php';
?>

<div class="empty-state text-center py-5">
    <i class="bi bi-search-off display-4 text-muted"></i>
    <h1 class="section-title mb-3">Halaman Tidak Ditemukan</h1>
    <p class="text-muted mb-4">Halaman yang Anda cari tidak ada atau sudah dipindahkan.</p>
    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-aksi"><i class="bi bi-house me-1"></i>Kembali ke Beranda</a>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
