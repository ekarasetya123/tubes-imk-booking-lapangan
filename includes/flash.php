<?php
/**
 * includes/flash.php — menampilkan pesan flash (Bootstrap alert yang bisa ditutup).
 */

$flash = get_flash();
if ($flash):
    $ikon = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        'info'    => 'bi-info-circle-fill',
    ][$flash['type']] ?? 'bi-info-circle-fill';
?>
<div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
    <i class="bi <?php echo e($ikon); ?> fs-5" aria-hidden="true"></i>
    <div><?php echo e($flash['message']); ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
<?php endif; ?>
