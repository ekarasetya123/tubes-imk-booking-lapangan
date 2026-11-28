<?php
/**
 * public/register.php — registrasi akun penyewa baru. PRG: POST -> redirect -> GET.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();

if (is_logged_in()) {
    redirect(BASE_URL . '/penyewa/dashboard.php');
}

$errors = [];
$nama = $email = $noHp = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Sesi formulir tidak valid. Silakan muat ulang halaman lalu coba lagi.');
        redirect(BASE_URL . '/register.php');
    }
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $noHp  = trim($_POST['no_hp'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    if (mb_strlen($nama) < 3) {
        $errors['nama'] = 'Nama minimal 3 karakter.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Masukkan alamat email yang valid, contoh: nama@email.com.';
    }
    if (!preg_match('/^08\d{8,11}$/', $noHp)) {
        $errors['no_hp'] = 'Nomor HP harus diawali 08 dan terdiri dari 10–13 digit.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Kata sandi minimal 8 karakter.';
    }
    if ($password !== $konfirmasi) {
        $errors['konfirmasi'] = 'Konfirmasi kata sandi tidak sama dengan kata sandi.';
    }

    // Cek email unik
    if (!isset($errors['email'])) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            'INSERT INTO users (nama, email, no_hp, password, role) VALUES (?, ?, ?, ?, "penyewa")'
        );
        $stmt->execute([$nama, $email, $noHp, $hash]);
        set_flash('success', 'Pendaftaran berhasil! Silakan masuk dengan akun baru Anda.');
        redirect(BASE_URL . '/login.php');
    }
}

$title = 'Daftar';
$active = '';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-page py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5 col-xl-4">
                <div class="card auth-card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <h1 class="h4 section-title text-center mb-1">Buat Akun LapanganKu</h1>
                        <p class="text-muted text-center small mb-4">Gratis, hanya untuk penyewa lapangan.</p>

                        <form method="post" action="register.php" novalidate>
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label for="nama" class="form-label">Nama lengkap</label>
                                <input type="text" id="nama" name="nama" class="form-control <?php echo isset($errors['nama']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($nama); ?>" autocomplete="name" required aria-describedby="nama-error">
                                <?php if (isset($errors['nama'])): ?><div class="invalid-feedback" id="nama-error"><?php echo e($errors['nama']); ?></div><?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($email); ?>" autocomplete="email" required aria-describedby="email-error">
                                <?php if (isset($errors['email'])): ?><div class="invalid-feedback" id="email-error"><?php echo e($errors['email']); ?></div><?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="no_hp" class="form-label">Nomor HP (WhatsApp)</label>
                                <input type="tel" id="no_hp" name="no_hp" class="form-control <?php echo isset($errors['no_hp']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($noHp); ?>" placeholder="08xxxxxxxxxx" autocomplete="tel" required aria-describedby="nohp-error">
                                <?php if (isset($errors['no_hp'])): ?><div class="invalid-feedback" id="nohp-error"><?php echo e($errors['no_hp']); ?></div><?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Kata sandi</label>
                                <div class="input-group">
                                    <input type="password" id="password" name="password"
                                           class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                           minlength="8" autocomplete="new-password" required aria-describedby="password-error toggle-password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggle-password" aria-label="Tampilkan kata sandi">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                    <?php if (isset($errors['password'])): ?><div class="invalid-feedback" id="password-error"><?php echo e($errors['password']); ?></div><?php endif; ?>
                                </div>
                                <div class="form-text">Minimal 8 karakter.</div>
                            </div>
                            <div class="mb-3">
                                <label for="konfirmasi_password" class="form-label">Ulangi kata sandi</label>
                                <input type="password" id="konfirmasi_password" name="konfirmasi_password"
                                       class="form-control <?php echo isset($errors['konfirmasi']) ? 'is-invalid' : ''; ?>"
                                       autocomplete="new-password" required aria-describedby="konfirmasi-error">
                                <?php if (isset($errors['konfirmasi'])): ?><div class="invalid-feedback" id="konfirmasi-error"><?php echo e($errors['konfirmasi']); ?></div><?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-aksi w-100" id="btn-daftar">
                                <span class="btn-teks">Daftar Sekarang</span>
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </form>

                        <p class="text-center mt-3 mb-0 small">Sudah punya akun?
                            <a href="<?php echo BASE_URL; ?>/login.php">Masuk di sini</a>
                        </p>
                    </div>
                </div>
                <div class="mt-3 bg-krem text-center py-3">
                    <p class="mb-0 text-muted small">© <?php echo date('Y'); ?> LapanganKu · Tugas Besar IMK</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<style>
.bg-krem { background: var(--warna-bg-netral); }
.auth-page { min-height: 80vh; }
.auth-page .auth-card { border-radius: var(--radius-kard); }
.auth-page .form-control { min-height: 46px; }
</style>
