<?php
/**
 * penyewa/profil.php — ubah profil dan kata sandi sendiri. PRG: POST -> redirect -> GET.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('penyewa');

$user = current_user();
$errors = [];
$nama = $user['nama'];
$noHp = $user['no_hp'];
$email = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Sesi tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/../penyewa/profil.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'profil') {
        $nama  = trim($_POST['nama'] ?? '');
        $noHp  = trim($_POST['no_hp'] ?? '');
        if (mb_strlen($nama) < 3) {
            $errors['nama'] = 'Nama minimal 3 karakter.';
        }
        if (!preg_match('/^08\d{8,11}$/', $noHp)) {
            $errors['no_hp'] = 'Nomor HP harus diawali 08 dan terdiri dari 10–13 digit.';
        }
        if (!$errors) {
            $stmt = db()->prepare('UPDATE users SET nama = ?, no_hp = ? WHERE id = ?');
            $stmt->execute([$nama, $noHp, $user['id']]);
            $_SESSION['user']['nama'] = $nama;
            $_SESSION['user']['no_hp'] = $noHp;
            set_flash('success', 'Profil berhasil diperbarui.');
            redirect(BASE_URL . '/../penyewa/profil.php');
        }
    } elseif ($action === 'password') {
        $lama = $_POST['password_lama'] ?? '';
        $baru = $_POST['password_baru'] ?? '';
        $konf = $_POST['konfirmasi_password'] ?? '';
        if (strlen($baru) < 8) {
            $errors['baru'] = 'Kata sandi baru minimal 8 karakter.';
        }
        if ($baru !== $konf) {
            $errors['konf'] = 'Konfirmasi kata sandi baru tidak cocok.';
        }
        if (!$errors && !password_verify($lama, $user['password_hash'] ?? '')) {
            // Reload password_hash dari DB karena current_user tidak menyimpan hash
            $stmt = db()->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $dbUser = $stmt->fetch();
            if (!$dbUser || !password_verify($lama, $dbUser['password'])) {
                $errors['lama'] = 'Kata sandi saat ini tidak sesuai.';
            }
        }
        if (!$errors) {
            $hash = password_hash($baru, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hash, $user['id']]);
            set_flash('success', 'Kata sandi berhasil diubah.');
            redirect(BASE_URL . '/../penyewa/profil.php');
        }
    }
}

$title = 'Profil Saya';
$active = 'profil';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card auth-card border-0 shadow-sm mb-4">
            <div class="card-body p-4 p-md-5">
                <h3 class="h5 section-title mb-1">Ubah Profil</h3>
                <p class="text-muted small mb-3">Perbarui nama dan nomor HP Anda.</p>
                <form method="post" action="profil.php" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="profil">
                    <div class="mb-3">
                        <label for="nama" class="form-label required">Nama lengkap</label>
                        <input type="text" id="nama" name="nama" class="form-control <?php echo isset($errors['nama']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo e($nama); ?>" required aria-describedby="nama-error">
                        <?php if (isset($errors['nama'])): ?><div class="invalid-feedback" id="nama-error"><?php echo e($errors['nama']); ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="no_hp" class="form-label required">Nomor HP (WhatsApp)</label>
                        <input type="tel" id="no_hp" name="no_hp" class="form-control <?php echo isset($errors['no_hp']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo e($noHp); ?>" placeholder="08xxxxxxxxxx" required aria-describedby="nohp-error">
                        <?php if (isset($errors['no_hp'])): ?><div class="invalid-feedback" id="nohp-error"><?php echo e($errors['no_hp']); ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-1">
                            <span class="text-muted small">Email:</span>
                            <span class="fw-medium"><?php echo e($email); ?></span>
                        </div>
                        <div class="form-text">Email tidak dapat diubah dari sini.</div>
                    </div>
                    <button type="submit" class="btn btn-aksi w-100">Simpan Perubahan</button>
                </form>
            </div>
        </div>

        <div class="card auth-card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h3 class="h5 section-title mb-1">Ubah Kata Sandi</h3>
                <p class="text-muted small mb-3">Isi semua kolom untuk mengganti kata sandi akun.</p>
                <form method="post" action="profil.php" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3">
                        <label for="password_lama" class="form-label required">Kata sandi saat ini</label>
                        <input type="password" id="password_lama" name="password_lama"
                               class="form-control <?php echo isset($errors['lama']) ? 'is-invalid' : ''; ?>"
                               required aria-describedby="lama-error toggle-pw-lama">
                        <div class="input-group mt-1">
                            <button class="btn btn-outline-secondary" type="button" id="toggle-pw-lama" aria-label="Tampilkan kata sandi">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['lama'])): ?><div class="invalid-feedback" id="lama-error"><?php echo e($errors['lama']); ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="password_baru" class="form-label required">Kata sandi baru</label>
                        <div class="input-group">
                            <input type="password" id="password_baru" name="password_baru"
                                   class="form-control <?php echo isset($errors['baru']) ? 'is-invalid' : ''; ?>"
                                   minlength="8" required aria-describedby="baru-error toggle-pw-baru">
                            <button class="btn btn-outline-secondary" type="button" id="toggle-pw-baru" aria-label="Tampilkan">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                            <?php if (isset($errors['baru'])): ?><div class="invalid-feedback" id="baru-error"><?php echo e($errors['baru']); ?></div><?php endif; ?>
                        </div>
                        <div class="form-text">Minimal 8 karakter.</div>
                    </div>
                    <div class="mb-3">
                        <label for="konfirmasi_password" class="form-label required">Ulangi kata sandi baru</label>
                        <input type="password" id="konfirmasi_password" name="konfirmasi_password"
                               class="form-control <?php echo isset($errors['konf']) ? 'is-invalid' : ''; ?>"
                               required aria-describedby="konf-error toggle-pw-konf">
                        <div class="input-group mt-1">
                            <button class="btn btn-outline-secondary" type="button" id="toggle-pw-konf" aria-label="Tampilkan kata sandi">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['konf'])): ?><div class="invalid-feedback" id="konf-error"><?php echo e($errors['konf']); ?></div><?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-aksi w-100">Ubah Kata Sandi</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<style>
.bg-krem { background: var(--warna-bg-netral); }
</style>
