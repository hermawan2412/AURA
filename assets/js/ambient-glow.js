(function () {
  'use strict';

  // Cahaya ambient yang mengikuti kursor pada kartu, tombol, dan kotak input
  // (lihat .has-glow di assets/css/style.css). JS cuma menghitung posisi kursor
  // relatif ke pusat elemen -- warna & animasinya murni CSS (box-shadow).
  var SELECTOR = '.letter-card, .login-card, .btn, .field input, .field select, .field textarea';

  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  function onMouseMove(e) {
    var el = e.currentTarget;
    var rect = el.getBoundingClientRect();
    var dx = e.clientX - (rect.left + rect.width / 2);
    var dy = e.clientY - (rect.top + rect.height / 2);
    var maxGeser = 16;
    var gx = Math.max(-maxGeser, Math.min(maxGeser, dx / 6));
    var gy = Math.max(-maxGeser, Math.min(maxGeser, dy / 6));
    el.style.setProperty('--glow-x', gx.toFixed(1) + 'px');
    el.style.setProperty('--glow-y', gy.toFixed(1) + 'px');
  }

  document.addEventListener('DOMContentLoaded', function () {
    var elemen = document.querySelectorAll(SELECTOR);
    for (var i = 0; i < elemen.length; i++) {
      elemen[i].classList.add('has-glow');
      elemen[i].addEventListener('mousemove', onMouseMove);
    }
  });
})();
