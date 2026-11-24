<?php
/**
 * includes/footer.php — bagian bawah setiap halaman.
 */
$BASE = BASE_URL;
?>
</div><!-- /.container -->
</main>
<footer class="footer-lapanganku mt-auto">
    <div class="container py-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="brand-badge brand-badge-sm" aria-hidden="true"><i class="bi bi-grid-1x2-fill"></i></span>
            <span class="fw-semibold">Lapangan<strong>Ku</strong></span>
            <span class="text-footer small">— booking lapangan futsal &amp; badminton jadi gampang.</span>
        </div>
        <span class="text-footer small">© <?php echo date('Y'); ?> LapanganKu · Tugas Besar IMK</span>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $BASE; ?>/../assets/js/app.js"></script>
</body>
</html>
