<?php
/**
 * public/jadwal.php — pilih tanggal & lapangan, lihat grid slot per jam.
 * Slot: hijau = tersedia, abu/merah = terisi, biru = dipilih.
 * Poles UI/UX: strip hari horizontal, chip lapangan, legenda slot,
 * panel total harga sticky (desktop) / bottom sheet (HP).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
auto_batalkan_lewat_batas_bayar();

// Daftar 14 hari ke depan untuk strip hari
$hariStrip = [];
for ($i = 0; $i < 14; $i++) {
    $t = date('Y-m-d', strtotime("+$i days"));
    $hariStrip[] = [
        'tanggal' => $t,
        'nama'    => date('l', strtotime($t)),
        'angka'   => date('d', strtotime($t)),
        'bulan'   => date('M', strtotime($t)),
        'ini'     => $t === date('Y-m-d'),
    ];
}

// Nama hari Bahasa Indonesia
$namaHariId = [
    'Monday'    => 'Senin', 'Tuesday'   => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis', 'Friday'    => 'Jumat', 'Saturday'    => 'Sabtu',
    'Sunday'    => 'Minggu',
];
foreach ($hariStrip as &$h) {
    $h['nama_id'] = $namaHariId[$h['nama']] ?? $h['nama'];
}
unset($h);

// Pilihan dari query string
$lapanganId = isset($_GET['lapangan']) ? (int)$_GET['lapangan'] : 0;
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
// Validasi tanggal: format benar, tidak lampau, maks 30 hari ke depan
$ts = strtotime($tanggal);
$minTanggal = date('Y-m-d');
$maksTanggal = date('Y-m-d', strtotime('+30 days'));
if (!$ts || $tanggal < $minTanggal || $tanggal > $maksTanggal) {
    $tanggal = date('Y-m-d');
    $ts = strtotime($tanggal);
}

// Default ke lapangan aktif pertama jika tidak ada atau tidak valid
$lapanganAktif = db()->query(
    "SELECT * FROM lapangan WHERE status='aktif' ORDER BY jenis, nama"
)->fetchAll();

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
<p class="text-muted mb-4">Pilih tanggal dan lapangan, lalu klik slot hijau untuk memilih jam main (maksimal 3 jam berurutan). Total harga dihitung langsung.</p>

<!-- FILTER: tanggal & lapangan -->
<form method="get" action="jadwal.php" class="row g-2 align-items-end mb-4 filtri-jadwal" aria-label="Filter jadwal">
    <div class="col-md-5">
        <label for="lapangan" class="form-label required">Lapangan</label>
        <select id="lapangan" name="lapangan" class="form-select" onchange="this.form.submit()">
            <?php foreach ($lapanganAktif as $l): ?>
            <option value="<?php echo (int)$l['id']; ?>" <?php echo (int)$l['id'] === $lapanganId ? 'selected' : ''; ?>>
                <?php echo e($l['nama']); ?> (<?php echo rupiah($l['harga_per_jam']); ?>/jam)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label for="tanggal" class="form-label required">Tanggal</label>
        <input type="date" id="tanggal" name="tanggal" class="form-control"
               value="<?php echo e($tanggal); ?>" min="<?php echo $minTanggal; ?>" max="<?php echo $maksTanggal; ?>"
               onchange="this.form.submit()">
    </div>
    <div class="col-md-3">
        <p class="mb-0 small text-muted"><i class="bi bi-info-circle me-1"></i>Maksimal booking 30 hari ke depan.</p>
    </div>
</form>

<?php if ($lapangan): ?>

<!-- STRIP HARI (pilih tanggal cepat) -->
<div class="hari-strip mb-4" role="group" aria-label="Pilih tanggal">
    <?php foreach ($hariStrip as $h): ?>
    <a href="jadwal.php?lapangan=<?php echo $lapanganId; ?>&tanggal=<?php echo $h['tanggal']; ?>"
       class="hari-chip <?php echo $h['ini'] ? 'aktif' : ''; ?> <?php echo $h['tanggal'] === $tanggal ? 'aktif' : ''; ?>"
       title="<?php echo e($h['nama_id']); ?>, <?php echo e($h['tanggal']); ?>">
        <span class="hari-angka"><?php echo $h['angka']; ?></span>
        <span class="hari-nama"><?php echo e($h['nama_id']); ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- CHIP LAPANGAN -->
<div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Pilih lapangan">
    <?php foreach ($lapanganAktif as $l): ?>
    <a href="jadwal.php?lapangan=<?php echo (int)$l['id']; ?>&tanggal=<?php echo e($tanggal); ?>"
       class="chip-lapangan <?php echo (int)$l['id'] === $lapanganId ? 'aktif' : ''; ?>" title="<?php echo e($l['nama']); ?>">
        <span class="kotak-warna kotak-tersedia" aria-hidden="true"></span>
        <?php echo e($l['nama']); ?>
    </a>
    <?php endforeach; ?>
</div>

<section aria-labelledby="judul-grid">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 id="judul-grid" class="h5 mb-0">
            <?php echo e($lapangan['nama']); ?> — <?php echo format_tanggal($tanggal); ?>
        </h2>
        <div class="legenda d-flex flex-wrap gap-3 small">
            <span><span class="kotak-warna kotak-tersedia" aria-hidden="true"></span> Tersedia</span>
            <span><span class="kotak-warna kotak-terisi" aria-hidden="true"></span> Terisi</span>
            <span><span class="kotak-warna kotak-dipilih" aria-hidden="true"></span> Dipilih</span>
            <span class="text-muted"><span class="kotak-warna kotak-terisi" style="background:#e7a79a" aria-hidden="true"></span> Lewat</span>
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
    </form>

    <!-- RINGKASAN PILIHAN (sticky desktop / bottom sheet HP) -->
    <div class="ringkasan-pilihan mt-3 panel-total-sticky" id="ringkasan-pilihan" aria-live="polite">
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
            <button type="submit" form="form-slot" class="btn btn-aksi btn-lg w-100 mt-3" id="btn-booking" disabled>
                <i class="bi bi-arrow-right-circle me-2"></i>Booking Sekarang
            </button>
            <input type="hidden" name="jam_dipilih" id="jam-dipilih" value="">
        </div>
    </div>
</section>

<?php else: ?>
<div class="alert alert-warning">Belum ada lapangan aktif yang bisa dijadwalkan.</div>
<?php endif;

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
/* Tambahan interaksi strip hari & chip lapangan (navigasi tanpa reload jika diinginkan) */
document.addEventListener('DOMContentLoaded', function () {
    // Sorot hari-chip yang aktif berdasarkan URL
    document.querySelectorAll('.hari-chip').forEach(function (chip) {
        if (chip.classList.contains('aktif')) {
            chip.setAttribute('aria-current', 'page');
        }
    });
    document.querySelectorAll('.chip-lapangan').forEach(function (chip) {
        if (chip.classList.contains('aktif')) {
            chip.setAttribute('aria-current', 'location');
        }
    });
});
</script>

