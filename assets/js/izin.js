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
  var gerakKurang = matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Tutup dialog dengan animasi keluar 150ms; Esc tetap langsung menutup (animasi hanya visual).
  var tutupDialog = function (d) {
    if (gerakKurang || d.classList.contains('iz-tutup')) { d.close(); return; }
    d.classList.add('iz-tutup');
    setTimeout(function () { d.classList.remove('iz-tutup'); d.close(); }, 150);
  };
  document.querySelectorAll('[data-tutup]').forEach(function (b) {
    b.addEventListener('click', function () { tutupDialog(b.closest('dialog')); });
  });
  document.querySelectorAll('dialog.iz-dialog').forEach(function (d) {
    d.addEventListener('cancel', function (e) { e.preventDefault(); tutupDialog(d); });
    d.addEventListener('click', function (e) { if (e.target === d) tutupDialog(d); }); // klik latar
  });

  // Tombol kirim: tampilkan "Memproses…" + putaran, dan cegah kirim ganda.
  document.querySelectorAll('.iz-page form, .iz-atur form, .iz-dialog form, .iz-aksi form').forEach(function (f) {
    if (f.method.toLowerCase() !== 'post') return;
    f.addEventListener('submit', function (e) {
      var b = e.submitter || f.querySelector('button[type=submit]');
      if (!b || !f.checkValidity()) return;
      setTimeout(function () { // setelah nilai tombol ikut terkirim
        b.setAttribute('aria-busy', 'true');
        b.disabled = true;
        b.innerHTML = '<span class="iz-putar" aria-hidden="true"></span>Memproses…';
      }, 0);
    });
  });

  // Kembali lewat tombol Back (bfcache): pulihkan tombol yang tadi dikunci.
  window.addEventListener('pageshow', function (e) { if (e.persisted) location.reload(); });

  // Angka dasbor menghitung naik (300ms); dilewati bila gerak dikurangi.
  if (!gerakKurang) {
    document.querySelectorAll('[data-angka]').forEach(function (el) {
      var target = Number(el.dataset.angka), mulai = null;
      if (!target) return;
      var langkah = function (t) {
        if (mulai === null) mulai = t;
        var p = Math.min(1, (t - mulai) / 300);
        el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(langkah);
      };
      el.textContent = '0';
      requestAnimationFrame(langkah);
    });
  }

  // Navigasi pengaturan menyorot bagian yang sedang terlihat.
  var nav = document.querySelector('.iz-atur-nav');
  if (nav && 'IntersectionObserver' in window) {
    var tautan = {};
    nav.querySelectorAll('a').forEach(function (a) { tautan[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (isi) {
      isi.forEach(function (x) {
        if (!x.isIntersecting) return;
        nav.querySelectorAll('a').forEach(function (a) { a.removeAttribute('aria-current'); });
        if (tautan[x.target.id]) tautan[x.target.id].setAttribute('aria-current', 'true');
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    Object.keys(tautan).forEach(function (id) { var el = document.getElementById(id); if (el) io.observe(el); });
  }

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

  // Tooltip grafik dasbor: hover/fokus kolom batang -> tampilkan data-tip di atasnya.
  document.querySelectorAll('.iz-grafik').forEach(function (g) {
    var tip = g.querySelector('.iz-tip');
    g.querySelectorAll('[data-tip]').forEach(function (k) {
      k.tabIndex = 0;
      var tampil = function () {
        var rg = g.getBoundingClientRect(), rk = k.getBoundingClientRect(), b = k.querySelector('.iz-batang').getBoundingClientRect();
        tip.textContent = k.dataset.tip;
        tip.style.left = (rk.left - rg.left + rk.width / 2) + 'px';
        tip.style.top = (Math.min(b.top, rk.bottom - 4) - rg.top) + 'px';
        tip.hidden = false;
      };
      k.addEventListener('mouseenter', tampil);
      k.addEventListener('focus', tampil);
      k.addEventListener('mouseleave', function () { tip.hidden = true; });
      k.addEventListener('blur', function () { tip.hidden = true; });
    });
  });
})();
