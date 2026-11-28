<?php
/**
 * public/index.php — halaman beranda publik: hero, daftar lapangan, cara booking.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$lapanganAktif = db()->query(
    "SELECT * FROM lapangan WHERE status = 'aktif' ORDER BY jenis, nama"
)->fetchAll();

// Hitung statistik untuk panel ringkasan hero
$totalLapangan = count($lapanganAktif);
$hargaMulai = null;
foreach ($lapanganAktif as $l) {
    $h = (float)$l['harga_per_jam'];
    if ($hargaMulai === null || $h < $hargaMulai) { $hargaMulai = $h; }
}

$title = 'Beranda';
$active = 'beranda';
$body_class = 'page-beranda';
require __DIR__ . '/../includes/header.php';
?>

<!-- HERO -->
<section class="hero-lapanganku mb-5">
    <div class="hero-inner container">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-7">
                <p class="hero-eyebrow"><i class="bi bi-lightning-charge-fill me-1"></i> Booking lapangan olahraga dalam 3 langkah</p>
                <h1 class="hero-title">Main futsal &amp; badminton,<br>tinggal <span class="hero-highlight">pilih jam</span>.</h1>
                <p class="hero-sub">LapanganKu memudahkan Anda melihat jadwal kosong secara langsung, booking per jam,
                dan mengunggah bukti transfer — tanpa perlu telepon.</p>
                <div class="d-flex flex-wrap gap-2 mt-5">
                    <a href="<?php echo BASE_URL; ?>/jadwal.php" class="btn btn-aksi btn-lg"><i class="bi bi-calendar2-check me-2"></i>Lihat Jadwal &amp; Booking</a>
                    <?php if (!is_logged_in()): ?>
                    <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-outline-aksi btn-lg">Buat Akun Gratis</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block">
                <div class="hero-card">
                    <img src="<?php echo BASE_URL; ?>/../assets/img/illust-lapangan.svg" class="img-fluid rounded-3 mb-3" alt="Illustrasi lapangan futsal dan badminton" style="max-height: 120px;">
                    <p class="hero-card-angka"><?php echo $totalLapangan; ?>+</p>
                    <p class="hero-card-label">lapangan aktif siap dibooking hari ini</p>
                    <?php if ($hargaMulai !== null): ?>
                    <div class="hero-card-strip mt-3">
                        <span><i class="bi bi-tag-fill lap-futsal" aria-hidden="true"></i> Harga mulai <?php echo rupiah($hargaMulai); ?> / jam</span>
                        <span class="lap-futsal"><i class="bi bi-circle-fill lap-futsal" aria-hidden="true"></i> Futsal</span>
                        <span class="lap-badminton"><i class="bi bi-circle-fill lap-badminton" aria-hidden="true"></i> Badminton</span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-5">
                    <h6 class="text-muted small mb-2">Contoh harga per jam</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (array_slice($lapanganAktif, 0, 3) as $l): ?>
                        <span class="chip-lapangan" style="cursor:default;" title="<?php echo e($l['nama']); ?>">
                            <span class="kotak-warna kotak-tersedia" aria-hidden="true"></span>
                            <?php echo e($l['nama']); ?> · <?php echo rupiah($l['harga_per_jam']); ?>/jam
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- DAFTAR LAPANGAN -->
<section class="mb-5" aria-labelledby="judul-lapangan">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
        <div>
            <h2 id="judul-lapangan" class="section-title mb-1">Pilih lapanganmu</h2>
            <p class="text-muted mb-0">Harga sudah termasuk sewa lapangan per jam.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/jadwal.php" class="btn btn-link-lapanganku">Lihat jadwal semua lapangan <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-3 g-md-4">
        <?php foreach ($lapanganAktif as $l): ?>
        <div class="col-md-6 col-lg-3">
            <article class="card-lapangan h-100">
                <div class="card-lapangan-visual lap-<?php echo e($l['jenis']); ?>">
                    <img src="<?php echo BASE_URL; ?>/../assets/img/<?php echo $l['jenis'] === 'futsal' ? 'bola-sepak.svg' : 'shuttlecock-raket.svg'; ?>" alt="<?php echo ucfirst($l['jenis']); ?>" style="max-height: 44px; width: auto;">
                    <span class="badge-jenis"><?php echo e(ucfirst($l['jenis'])); ?></span>
                </div>
                <div class="card-lapangan-body">
                    <h3 class="card-lapangan-nama"><?php echo e($l['nama']); ?></h3>
                    <p class="card-lapangan-desc"><?php echo e($l['deskripsi']); ?></p>
                    <div class="d-flex justify-content-between align-items-center mt-auto pt-2">
                        <div>
                            <span class="harga"><?php echo rupiah($l['harga_per_jam']); ?></span>
                            <span class="text-muted small">/ jam</span>
                        </div>
                        <span class="jam-operasional"><i class="bi bi-clock me-1"></i><?php echo format_jam($l['jam_buka']); ?>–<?php echo format_jam($l['jam_tutup']); ?></span>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/jadwal.php?lapangan=<?php echo (int)$l['id']; ?>" class="btn btn-aksi w-100 mt-3">Booking Lapangan Ini</a>
                </div>
            </article>
        </div>
        <?php endforeach; ?>
        <?php if (!$lapanganAktif): ?>
        <div class="col-12"><div class="alert alert-info">Belum ada lapangan aktif saat ini.</div></div>
        <?php endif; ?>
    </div>
</section>

<!-- 3 LANGKAH -->
<section class="mb-4" aria-labelledby="judul-langkah">
    <h2 id="judul-langkah" class="section-title mb-4 text-center">Cara booking di LapanganKu</h2>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="langkah-card h-100">
                <span class="langkah-nomor">1</span>
                <i class="bi bi-calendar2-week langkah-ikon"></i>
                <h3>Pilih tanggal &amp; jam</h3>
                <p>Buka halaman jadwal, pilih lapangan dan tanggal, lalu klik slot hijau yang masih kosong (1–3 jam berurutan).</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="langkah-card h-100">
                <span class="langkah-nomor">2</span>
                <i class="bi bi-credit-card langkah-ikon"></i>
                <h3>Bayar &amp; unggah bukti</h3>
                <p>Transfer ke rekening yang ditampilkan, lalu unggah bukti transfer (JPG/PNG/PDF, maks 2 MB) dalam 2 jam.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="langkah-card h-100">
                <span class="langkah-nomor">3</span>
                <i class="bi bi-patch-check langkah-ikon"></i>
                <h3>Tunggu verifikasi</h3>
                <p>Admin memverifikasi pembayaran Anda. Status booking bisa dipantau kapan saja di halaman riwayat.</p>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
