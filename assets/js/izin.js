// Interaksi kecil modul izin. Tanpa pustaka; semua bekerja juga tanpa JS (tautan & form biasa).
(function () {
  // Baris tabel bisa diklik seluruhnya; tautan di sel pertama tetap jadi target utama (keyboard).
  document.querySelectorAll('tr[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) {
      if (e.target.closest('a, button, input, select, textarea')) return;
      window.location = tr.dataset.href;
    });
  });

  // Pencarian cepat di tabel daftar (sisi klien). "/" memfokuskan kolom cari.
  document.querySelectorAll('[data-cari-tabel]').forEach(function (input) {
    var tabel = document.getElementById(input.dataset.cariTabel);
    var info = document.getElementById(input.dataset.cariInfo);
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      var n = 0;
      tabel.querySelectorAll('tbody tr[data-href]').forEach(function (tr) {
        var cocok = !q || tr.textContent.toLowerCase().indexOf(q) !== -1;
        tr.hidden = !cocok;
        if (cocok) n++;
      });
      if (info) info.textContent = q ? n + ' cocok' : info.dataset.awal;
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === '/' && !e.target.closest('input, textarea, select')) { e.preventDefault(); input.focus(); }
    });
  });

  // Tombol pembuka dialog keputusan (<dialog>); fokus ke isian pertama.
  document.querySelectorAll('[data-buka]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.getElementById(b.dataset.buka);
      d.showModal();
      var f = d.querySelector('textarea, input:not([type=hidden])');
      if (f) f.focus();
    });
  });
  document.querySelectorAll('[data-tutup]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('dialog').close(); });
  });

  // Form pengajuan: tampilkan isian sesuai pilihan + peringatan bila ditinggal saat sudah diisi.
  var f = document.getElementById('form-izin');
  if (f) {
    var kotor = false;
    var atur = function () {
      var sekarang = f.querySelector('#mulai-sekarang').checked;
      var keluar = f.querySelector('input[name=jenis]:checked').value === 'keluar_sementara';
      var manual = f.querySelector('#blok-waktu-manual');
      var kembali = f.querySelector('#blok-jam-kembali');
      if (manual.hidden !== sekarang) { manual.hidden = sekarang; if (!sekarang) manual.classList.add('iz-muncul-ulang'); }
      if (kembali.hidden !== !keluar) { kembali.hidden = !keluar; if (keluar) kembali.classList.add('iz-muncul-ulang'); }
      f.querySelector('#jam_kembali').required = keluar;
      f.querySelector('#jam_mulai').required = !sekarang;
    };
    f.addEventListener('change', function () { kotor = true; atur(); });
    f.addEventListener('input', function () { kotor = true; });
    f.addEventListener('submit', function () { kotor = false; });
    window.addEventListener('beforeunload', function (e) { if (kotor) { e.preventDefault(); e.returnValue = ''; } });
    atur();
  }
})();
