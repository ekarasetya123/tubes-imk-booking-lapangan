/* ============================================================
   LapanganKu — Interaksi JavaScript vanilla
   - Toggle password (mata)
   - Pemilihan slot jam di jadwal.php
   - Submisi form dengan indikator loading
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- Toggle visibility password ---------- */
  document.querySelectorAll('button[id^="toggle-password"], button[id^="toggle-pw-"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = this.id.replace('toggle-password', 'password').replace('toggle-pw-', 'password-');
      var input = document.getElementById(targetId);
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        this.innerHTML = '<i class="bi bi-eye-slash" aria-hidden="true"></i>';
        this.setAttribute('aria-label', 'Sembunyikan kata sandi');
      } else {
        input.type = 'password';
        this.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
        this.setAttribute('aria-label', 'Tampilkan kata sandi');
      }
    });
  });

  /* ---------- Pemilihan slot jam di halaman jadwal ---------- */
  var formSlot = document.getElementById('form-slot');
  var btnBooking = document.getElementById('btn-booking');
  var jamInput = document.getElementById('jam-dipilih');
  var ringkasan = document.getElementById('ringkasan-pilihan');
  var teksRingkasan = document.getElementById('ringkasan-teks');
  var hargaRingkasan = document.getElementById('ringkasan-harga');
  var hargaPerJam = formSlot ? parseFloat(formSlot.getAttribute('data-harga') || 0) : 0;
  var dipilih = [];

  if (formSlot) {
    formSlot.querySelectorAll('.slot').forEach(function (slot) {
      if (slot.hasAttribute('disabled')) return;
      slot.addEventListener('click', function () {
        var jam = parseInt(this.getAttribute('data-jam'), 10);
        var label = this.querySelector('.slot-jam').textContent.trim();

        // Tambah / hapus dari daftar dipilih
        var idx = dipilih.indexOf(jam);
        if (idx >= 0) {
          dipilih.splice(idx, 1);
        } else if (dipilih.length < 3) {
          dipilih.push(jam);
          dipilih.sort(function (a, b) { return a - b; });
        } else {
          return; // maksimal 3 jam
        }

        // Update UI demarkasi
        formSlot.querySelectorAll('.slot').forEach(function (s) {
          var j = parseInt(s.getAttribute('data-jam'), 10);
          if (j === jam) {
            s.classList.toggle('slot-dipilih', dipilih.includes(jam));
            s.setAttribute('aria-pressed', dipilih.includes(jam));
          }
        });

        // Simpan input tersembunyi
        jamInput.value = dipilih.join(',');

        // Update ringkasan & tombol booking
        if (dipilih.length > 0) {
          var mulai = dipilih[0];
          var selesai = dipilih[dipilih.length - 1] + 1;
          var durasi = dipilih.length;
          var total = hargaPerJam * durasi;

          teksRingkasan.innerHTML =
            '<span class="fw-semibold">' + durasi + ' jam berurutan</span> (' +
            String(mulai).padStart(2, '0') + ':00–' + String(selesai).padStart(2, '0') + ')' +
            ' — Rp ' + total.toLocaleString('id-ID');

          hargaRingkasan.textContent = 'Rp ' + total.toLocaleString('id-ID');
          ringkasan.classList.remove('d-none');
          btnBooking.disabled = false;
        } else {
          ringkasan.classList.add('d-none');
          btnBooking.disabled = true;
        }
      });
    });
  }

  /* ---------- Indikator loading saat submit form ---------- */
  document.querySelectorAll('form button[type="submit"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = this.closest('form');
      if (!form) return;
      // Jika form masih invalid (validasi bawaan HTML5), biarkan browser sendiri
      if (!form.checkValidity()) return;
      var teks = btn.querySelector('.btn-teks');
      var spinner = btn.querySelector('.spinner-border');
      if (teks) teks.classList.add('d-none');
      if (spinner) spinner.classList.remove('d-none');
      // Nonaktifkan TAMBAHAN setelah browser mulai mengirim form, agar tombol submit itself
      // tetap ikut terkirim (elemen disabled tidak ikut dalam POST). Elemen disabled tidak
      // perlu ikut terkirim karena PHP mengecek REQUEST_METHOD, bukan nama tombol.
      setTimeout(function () { btn.disabled = true; }, 0);
    });
  });

});
