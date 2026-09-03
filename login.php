<?php

require __DIR__ . '/src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$pesanError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        $pesanError = 'Nama pengguna dan kata sandi wajib diisi.';
    } else {
        $hasil = Auth::login($username, $password);
        if ($hasil['success']) {
            header('Location: index.php');
            exit;
        }
        $pesanError = $hasil['message'];
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk — AURA</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-screen">
  <form class="login-card" method="post" action="login.php">
    <div class="logo-ar" lang="ar" dir="rtl">اورا</div>
    <h1>AURA</h1>
    <p class="login-sub">Aplikasi Untuk suRAt<br>Sekretariat &middot; Bagian Kepegawaian</p>

    <?php if ($pesanError !== ''): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars((string) $pesanError); ?></div>
    <?php endif; ?>

    <?php echo Csrf::field(); ?>
    <div class="field">
      <label for="username">Nama pengguna</label>
      <input id="username" name="username" type="text" autocomplete="username" required autofocus>
    </div>
    <div class="field">
      <label for="password">Kata sandi</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Masuk</button>
    <p class="login-hint">Hanya dapat diakses dari jaringan kantor.</p>
  </form>
</div>
<script src="assets/js/ambient-glow.js"></script>
</body>
</html>
