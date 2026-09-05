// Toggle tema: Auto -> Terang -> Gelap -> Auto. 'Auto' berarti data-theme
// dilepas dari <html>, biar CSS @media (prefers-color-scheme) yang nentuin
// (ikut OS). Pilihan manual disimpan localStorage, DAN dicek lagi lewat
// inline <script> di <head> (lihat views/layout_atas.php & login.php)
// SEBELUM file ini sempat kemuat - itu yang nyegah kedipan tema salah pas
// halaman dibuka.
(function () {
  var KEY = 'aura-theme';
  var btn = document.querySelector('.theme-toggle');
  if (!btn) return;

  var LABEL = { auto: 'Tema: Otomatis (ikut sistem)', light: 'Tema: Terang', dark: 'Tema: Gelap' };
  var URUTAN = ['auto', 'light', 'dark'];

  function modeSekarang() {
    var v = null;
    try { v = localStorage.getItem(KEY); } catch (e) {}
    return (v === 'light' || v === 'dark') ? v : 'auto';
  }

  function terapkan(mode) {
    if (mode === 'light' || mode === 'dark') {
      document.documentElement.setAttribute('data-theme', mode);
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    btn.setAttribute('data-mode', mode);
    btn.title = LABEL[mode];
  }

  terapkan(modeSekarang()); // koreksi tombol (default render PHP selalu "auto")

  btn.addEventListener('click', function () {
    var mode = URUTAN[(URUTAN.indexOf(modeSekarang()) + 1) % URUTAN.length];
    try { localStorage.setItem(KEY, mode); } catch (e) {}
    terapkan(mode);
  });
})();
