<?php
/**
 * includes/header.php — bagian atas setiap halaman (DOCTYPE, head, navbar per peran).
 * Memakai variabel opsional: $title, $active (menu aktif), $body_class.
 */
start_session();
auto_batalkan_lewat_batas_bayar();
$user = current_user();
$title = $title ?? 'LapanganKu';
$active = $active ?? '';
$BASE = BASE_URL; // BASE_URL berakhir tanpa slash, URL halaman ada di /public
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> — LapanganKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo $BASE; ?>/../assets/css/style.css" rel="stylesheet">
</head>
<body class="<?php echo e($body_class ?? ''); ?>">
<a href="#main-content" class="skip-to-content">Lewati ke konten utama</a>
<nav class="navbar navbar-expand-lg navbar-lapanganku sticky-top" aria-label="Navigasi utama">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo $BASE; ?>/index.php">
            <span class="brand-badge" aria-hidden="true"><i class="bi bi-grid-1x2-fill"></i></span>
            <span class="brand-name">Lapangan<strong>Ku</strong></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarUtama"
                aria-controls="navbarUtama" aria-expanded="false" aria-label="Buka menu navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarUtama">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <?php if (!$user): ?>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'beranda' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/index.php">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'jadwal' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/jadwal.php">Jadwal</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'login' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/login.php">Masuk</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-aksi btn-sm px-3" href="<?php echo $BASE; ?>/register.php">Daftar</a></li>
                <?php elseif ($user['role'] === 'penyewa'): ?>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/../penyewa/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'jadwal' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/jadwal.php">Jadwal</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'riwayat' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/../penyewa/riwayat.php">Riwayat</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'profil' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/../penyewa/profil.php">Profil</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-aksi btn-sm px-3" href="<?php echo $BASE; ?>/jadwal.php"><i class="bi bi-plus-lg me-1"></i>Booking Baru</a></li>
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar" aria-hidden="true"><?php echo e(strtoupper(mb_substr($user['nama'], 0, 1))); ?></span>
                            <?php echo e($user['nama']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo $BASE; ?>/../penyewa/profil.php"><i class="bi bi-person me-2"></i>Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo $BASE; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                        </ul>
                    </li>
                <?php elseif ($user['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/../admin/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'lapangan' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/../admin/lapangan.php">Lapangan</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'booking' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/../admin/booking.php">Booking</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'pengguna' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/../admin/pengguna.php">Pengguna</a></li>
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar avatar-admin" aria-hidden="true"><?php echo e(strtoupper(mb_substr($user['nama'], 0, 1))); ?></span>
                            <?php echo e($user['nama']); ?> <span class="badge rounded-pill text-bg-dark">Admin</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item text-danger" href="<?php echo $BASE; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                        </ul>
                    </li>
                <?php else: // pemilik ?>
                    <li class="nav-item"><a class="nav-link <?php echo $active === 'laporan' ? 'active' : ''; ?>" href="<?php echo $BASE; ?>/../pemilik/laporan.php">Laporan</a></li>
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar avatar-pemilik" aria-hidden="true"><?php echo e(strtoupper(mb_substr($user['nama'], 0, 1))); ?></span>
                            <?php echo e($user['nama']); ?> <span class="badge rounded-pill text-bg-dark">Pemilik</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item text-danger" href="<?php echo $BASE; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main id="main-content" class="main-content">
<div class="container py-4">
<?php require __DIR__ . '/flash.php'; ?>
