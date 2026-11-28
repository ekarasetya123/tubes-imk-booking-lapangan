<?php
/**
 * admin/pengguna.php — daftar semua pengguna (hanya dapat dilihat).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('admin');
auto_batalkan_lewat_batas_bayar();

$users = db()->query(
    "SELECT id, nama, email, no_hp, role, created_at FROM users ORDER BY created_at DESC"
)->fetchAll();

$title = 'Kelola Pengguna';
$active = 'pengguna';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/../admin/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Pengguna</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="section-title mb-1">Daftar Pengguna</h1>
        <p class="text-muted mb-0">Daftar semua akun yang terdaftar di LapanganKu (hanya dapat dilihat).</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <?php if ($users): ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle table-users mb-0">
            <thead>
            <tr>
                <th class="align-middle" style="width: 45px;">#</th>
                <th class="align-middle">Nama</th>
                <th class="align-middle">Email</th>
                <th class="align-middle">No HP</th>
                <th class="align-middle" style="width: 140px;">Peran</th>
                <th class="align-middle" style="width: 170px;">Daftar Pada</th>
            </tr>
            </thead>
            <tbody>
            <?php $no = 1; foreach ($users as $u):
                $labelRole = ['penyewa' => 'Penyewa', 'admin' => 'Admin', 'pemilik' => 'Pemilik'][$u['role']] ?? $u['role'];
                $badgeRole = ['penyewa' => 'primary', 'admin' => 'danger', 'pemilik' => 'secondary'][$u['role']] ?? 'secondary';
            ?>
            <tr>
                <td class="align-middle"><?php echo $no++; ?></td>
                <td class="align-middle fw-semibold"><?php echo e($u['nama']); ?></td>
                <td class="align-middle"><a href="mailto:<?php echo e($u['email']); ?>" class="text-decoration-none"><?php echo e($u['email']); ?></a></td>
                <td class="align-middle"><?php echo e($u['no_hp']); ?></td>
                <td class="align-middle"><span class="badge text-bg-<?php echo e($badgeRole); ?>"><?php echo e($labelRole); ?></span></td>
                <td class="align-middle small text-muted"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="card-body">
        <div class="empty-state text-center py-5">
            <i class="bi bi-people display-4 text-muted"></i>
            <p class="mt-3 mb-1 fw-semibold">Belum ada pengguna terdaftar.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
