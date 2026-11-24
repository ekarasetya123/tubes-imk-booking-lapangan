<?php
/**
 * public/login.php — masuk akun (semua peran). PRG: POST -> redirect -> GET.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();

// Jika sudah login, arahkan ke dashboard sesuai peran
if (is_logged_in()) {
    $u = current_user();
    $tujuan = $u['role'] === 'admin' ? '/../admin/dashboard.php'
            : ($u['role'] === 'pemilik' ? '/../pemilik/laporan.php'
            : '/../penyewa/dashboard.php');
    redirect(BASE_URL . $tujuan);
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Sesi formulir tidak valid. Silakan muat ulang halaman lalu coba lagi.');
        redirect(BASE_URL . '/login.php');
    }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $kembali = $_POST['kembali'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Masukkan alamat email yang valid.';
    }
    if ($password === '') {
        $errors['password'] = 'Masukkan kata sandi Anda.';
    }

    if (!$errors) {
        if (attempt_login($email, $password)) {
            set_flash('success', 'Selamat datang kembali, ' . current_user()['nama'] . '!');
            // Arahkan ke URL kembali (hanya path internal) atau dashboard peran
            if ($kembali && str_starts_with($kembali, '/')) {
                redirect(BASE_URL . '/..' . $kembali);
            }
            $u = current_user();
            $tujuan = $u['role'] === 'admin' ? '/../admin/dashboard.php'
                    : ($u['role'] === 'pemilik' ? '/../pemilik/laporan.php'
                    : '/../penyewa/dashboard.php');
            redirect(BASE_URL . $tujuan);
        }
        $errors['form'] = 'Email atau kata sandi salah. Periksa kembali data Anda.';
    }
}

$kembali = isset($_GET['kembali']) && str_starts_with($_GET['kembali'], '/') ? $_GET['kembali'] : '';
$title = 'Masuk';
$active = 'login';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card auth-card">
            <div class="card-body p-4">
                <h1 class="h4 section-title text-center mb-1">Masuk ke LapanganKu</h1>
                <p class="text-muted text-center small mb-4">Gunakan akun yang sudah terdaftar.</p>

                <?php if (!empty($errors['form'])): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <div><?php echo e($errors['form']); ?></div>
                </div>
                <?php endif; ?>

                <form method="post" action="login.php" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="kembali" value="<?php echo e($kembali); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo e($email); ?>" autocomplete="email" required aria-describedby="email-error">
                        <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback" id="email-error"><?php echo e($errors['email']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Kata sandi</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password"
                                   class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                   autocomplete="current-password" required aria-describedby="password-error toggle-password">
                            <button class="btn btn-outline-secondary" type="button" id="toggle-password" aria-label="Tampilkan kata sandi">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                            <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback" id="password-error"><?php echo e($errors['password']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-aksi w-100" id="btn-login">
                        <span class="btn-teks">Masuk</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </form>

                <p class="text-center mt-3 mb-0 small">Belum punya akun?
                    <a href="<?php echo BASE_URL; ?>/register.php">Daftar di sini</a>
                </p>
                <p class="text-center mt-2 mb-0 small text-muted">Akun demo: admin@lapanganku.test / Admin123!</p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
