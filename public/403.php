<?php
/**
 * public/403.php — akses ditolak, halaman ramah dengan tombol kembali.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
$title = 'Akses Ditolak';
require __DIR__ . '/../includes/header.php';
?>

<div class="empty-state text-center py-5">
    <i class="bi bi-shield-exclamation display-4 text-muted"></i>
    <h1 class="section-title mb-3">Akses Ditolak</h1>
    <p class="text-muted mb-4">Anda tidak memiliki hak akses ke halaman tersebut.</p>
    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-aksi"><i class="bi bi-house me-1"></i>Kembali ke Beranda</a>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
