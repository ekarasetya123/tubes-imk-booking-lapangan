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
                redirect(BASE_URL . $kembali);
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

<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="row g-0 align-items-center auth-shell min-vh-100">
    <!-- Kolom ilustrasi (hanya desktop) -->
    <div class="col-lg-5 d-none d-lg-flex auth-ilustrasi">
        <div class="p-5 d-flex flex-column justify-content-center align-items-center text-center">
            <img src="<?php echo BASE_URL; ?>/../assets/img/illust-auth.svg" class="img-fluid auth-img" alt="Booking lapangan olahraga" style="max-width: 260px; height: auto;">
            <h2 class="h4 mt-4 mb-0">Main dengan mudah, booking dalam beberapa klik.</h2>
        </div>
    </div>

    <!-- Kolom form -->
    <div class="col-lg-7 auth-form-kolom">
        <div class="card auth-card border-0 shadow-sm h-100">
            <div class="card-body p-4 p-md-5">
                <div class="row justify-content-center">
                    <div class="col-md-9 col-lg-8">
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
                                <label for="email" class="form-label required">Email</label>
                                <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($email); ?>" autocomplete="email" required aria-describedby="email-error">
                                <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback" id="email-error"><?php echo e($errors['email']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3 position-relative">
                                <label for="password" class="form-label required">Kata sandi</label>
                                <div class="input-group">
                                    <input type="password" id="password" name="password"
                                           class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                           autocomplete="current-password" required aria-describedby="password-error toggle-password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggle-password" aria-label="Tampilkan kata sandi">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback" id="password-error"><?php echo e($errors['password']); ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Tombol demo untuk mengisi form otomatis -->
                            <div class="mb-3 d-flex justify-content-between align-items-center small">
                                <span class="text-muted"><i class="bi bi-info-circle me-1"></i>Cepat masuk:</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-email="admin@lapanganku.test" data-password="Admin123!" onclick="isiAkunDemo(this)">
                                    <i class="bi bi-person-plus me-1"></i>Akun Admin
                                </button>
                            </div>

                            <button type="submit" class="btn btn-aksi w-100" id="btn-login">
                                <span class="btn-teks">Masuk</span>
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </form>

                        <p class="text-center mt-3 mb-0 small">Belum punya akun?
                            <a href="<?php echo BASE_URL; ?>/register.php">Daftar di sini</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-netral-hangat py-4 d-flex align-items-center justify-content-center">
            <p class="mb-0 text-muted small text-center">© <?php echo date('Y'); ?> LapanganKu · Tugas Besar IMK</p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
/* Isi form login dengan akun demo saat tombol demo diklik */
function isiAkunDemo(btn) {
    var email = btn.getAttribute('data-email');
    var pw = btn.getAttribute('data-password');
    var inputEmail = document.getElementById('email');
    var inputPw = document.getElementById('password');
    if (inputEmail) { inputEmail.value = email; inputEmail.dispatchEvent(new Event('input')); }
    if (inputPw) { inputPw.value = pw; }
}
</script>
<style>
.auth-shell { min-height: 100vh; }
.auth-shell .auth-ilustrasi { background: linear-gradient(135deg, var(--warna-primer) 0%, var(--warna-primer-gelap) 70%); }
.auth-shell .auth-img { max-width: 280px; }
.auth-form-kolom { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.auth-form-kolom .card { border: none; }
.required::after { content: " *"; color: var(--warna-bahaya); }
.auth-shell .btn-outline-secondary { color: var(--warna-tekst-redup) !important; border-color: #dee2e6 !important; }
.auth-shell .btn-outline-secondary:hover { background: var(--warna-netral-hangat); border-color: #adb5bd !important; }
@media (max-width: 991.98px) {
  .auth-shell .auth-ilustrasi { display: none !important; }
  .auth-form-kolom { min-height: auto; padding: 2rem 0; }
}
</style>
