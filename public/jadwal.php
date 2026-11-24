<?php
/**
 * public/jadwal.php — pilih tanggal & lapangan, lihat grid slot per jam.
 * Slot: hijau = tersedia, abu/merah = terisi, biru = dipilih.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
auto_batalkan_lewat_batas_bayar();

$lapanganAktif = db()->query(
    "SELECT * FROM lapangan WHERE status='aktif' ORDER BY jenis, nama"
)->fetchAll();

// Pilihan dari query string
$lapanganId = isset($_GET['lapangan']) ? (int)$_GET['lapangan'] : 0;
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
// Validasi tanggal: format benar, tidak lampau, maks 30 hari ke depan
$ts = strtotime($tanggal);
$minTanggal = date('Y-m-d');
$maksTanggal = date('Y-m-d', strtotime('+30 days'));
if (!$ts || $tanggal < $minTanggal || $tanggal > $maksTanggal) {
    $tanggal = date('Y-m-d');
}

$lapangan = null;
foreach ($lapanganAktif as $l) {
    if ((int)$l['id'] === $lapanganId) { $lapangan = $l; break; }
}
if (!$lapangan && $lapanganAktif) {
    $lapangan = $lapanganAktif[0];
    $lapanganId = (int)$lapangan['id'];
}

// Ambil booking aktif pada tanggal & lapangan terpilih
$slotTerisi = [];
if ($lapangan) {
    $stmt = db()->prepare(
        "SELECT jam_mulai, jam_selesai, status FROM booking
         WHERE lapangan_id = ? AND tanggal = ?
           AND status IN ('menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi')
         ORDER BY jam_mulai"
    );
    $stmt->execute([$lapanganId, $tanggal]);
    foreach ($stmt->fetchAll() as $b) {
        $mulai = (int)substr($b['jam_mulai'], 0, 2);
        $selesai = (int)substr($b['jam_selesai'], 0, 2);
        for ($j = $mulai; $j < $selesai; $j++) {
            $slotTerisi[$j] = $b['status'];
        }
    }
}

// Bangun daftar slot jam dari jam buka sampai jam tutup
$slotJam = [];
if ($lapangan) {
    $buka = (int)substr($lapangan['jam_buka'], 0, 2);
    $tutup = (int)substr($lapangan['jam_tutup'], 0, 2);
    for ($j = $buka; $j < $tutup; $j++) {
        $slotJam[] = $j;
    }
}

$title = 'Jadwal Lapangan';
$active = 'jadwal';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php">Beranda</a></li>
        <li class="breadcrumb-item active" aria-current="page">Jadwal</li>
    </ol>
</nav>

<h1 class="section-title mb-1">Jadwal Ketersediaan</h1>
<p class="text-muted">Pilih lapangan dan tanggal, lalu klik slot hijau untuk memilih jam main (maks. 3 jam berurutan).</p>

<form method="get" action="jadwal.php" class="row g-2 align-items-end mb-4 filter-jadwal" aria-label="Filter jadwal">
    <div class="col-md-5">
        <label for="lapangan" class="form-label">Lapangan</label>
        <select id="lapangan" name="lapangan" class="form-select" onchange="this.form.submit()">
            <?php foreach ($lapanganAktif as $l): ?>
            <option value="<?php echo (int)$l['id']; ?>" <?php echo (int)$l['id'] === $lapanganId ? 'selected' : ''; ?>>
                <?php echo e($l['nama']); ?> (<?php echo rupiah($l['harga_per_jam']); ?>/jam)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label for="tanggal" class="form-label">Tanggal</label>
        <input type="date" id="tanggal" name="tanggal" class="form-control"
               value="<?php echo e($tanggal); ?>" min="<?php echo $minTanggal; ?>" max="<?php echo $maksTanggal; ?>"
               onchange="this.form.submit()">
    </div>
    <div class="col-md-3">
        <p class="mb-0 small text-muted"><i class="bi bi-info-circle me-1"></i>Maksimal booking 30 hari ke depan.</p>
    </div>
</form>

<?php if ($lapangan): ?>
<section aria-labelledby="judul-grid">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 id="judul-grid" class="h5 mb-0">
            <?php echo e($lapangan['nama']); ?> — <?php echo format_tanggal($tanggal); ?>
        </h2>
        <div class="legenda d-flex flex-wrap gap-3 small">
            <span><span class="kotak-warna kotak-tersedia" aria-hidden="true"></span> Tersedia</span>
            <span><span class="kotak-warna kotak-terisi" aria-hidden="true"></span> Terisi</span>
            <span><span class="kotak-warna kotak-dipilih" aria-hidden="true"></span> Dipilih</span>
        </div>
    </div>

    <form method="post" action="<?php echo BASE_URL; ?>/../penyewa/booking_baru.php" id="form-slot" data-harga="<?php echo e($lapangan['harga_per_jam']); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="lapangan_id" value="<?php echo (int)$lapangan['id']; ?>">
        <input type="hidden" name="tanggal" value="<?php echo e($tanggal); ?>">
        <div class="slot-grid" role="group" aria-label="Pilih slot jam untuk booking">
            <?php foreach ($slotJam as $j):
                $label = sprintf('%02d:00–%02d:00', $j, $j + 1);
                $terisi = isset($slotTerisi[$j]);
                // Slot jam yang sudah lewat hari ini tidak bisa dipilih
                $sudahLewat = ($tanggal === date('Y-m-d')) && ($j <= (int)date('H'));
            ?>
            <button type="button"
                    class="slot <?php echo $terisi ? 'slot-terisi' : 'slot-tersedia'; ?> <?php echo $sudahLewat && !$terisi ? 'slot-lewat' : ''; ?>"
                    data-jam="<?php echo $j; ?>"
                    <?php echo ($terisi || $sudahLewat) ? 'disabled' : ''; ?>
                    aria-pressed="false"
                    title="<?php echo $terisi ? 'Sudah dipesan' : ($sudahLewat ? 'Jam sudah lewat' : $label); ?>">
                <span class="slot-jam"><?php echo $label; ?></span>
                <span class="slot-status">
                    <?php if ($terisi): ?>
                        <i class="bi bi-x-circle-fill" aria-hidden="true"></i> Terisi
                    <?php elseif ($sudahLewat): ?>
                        <i class="bi bi-clock-history" aria-hidden="true"></i> Lewat
                    <?php else: ?>
                        <i class="bi bi-check-circle" aria-hidden="true"></i> Tersedia
                    <?php endif; ?>
                </span>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="ringkasan-pilihan mt-3 d-none" id="ringkasan-pilihan" aria-live="polite">
            <div class="card ringkasan-card">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <p class="mb-1 fw-semibold">Ringkasan pilihan Anda</p>
                        <p class="mb-0 text-muted" id="ringkasan-teks"></p>
                    </div>
                    <div class="text-end">
                        <p class="mb-0 text-muted small">Total harga</p>
                        <p class="mb-0 harga fs-5" id="ringkasan-harga">Rp 0</p>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-aksi btn-lg w-100 mt-3" id="btn-booking" disabled>
                <i class="bi bi-arrow-right-circle me-2"></i>Booking Sekarang
            </button>
        </div>
        <input type="hidden" name="jam_dipilih" id="jam-dipilih" value="">
    </form>
</section>
<?php else: ?>
<div class="alert alert-warning">Belum ada lapangan aktif yang bisa dijadwalkan.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
