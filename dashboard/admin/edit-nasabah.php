<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$baseDir = __DIR__;
include __DIR__ . '/config.php';
// include Supabase helper if available
if (file_exists(__DIR__ . '/../../server/supabase.php')) {
  include_once __DIR__ . '/../../server/supabase.php';
}

$admin_verified_via_firebase = false;
if (file_exists(__DIR__ . '/../../server/supabase.php')) {
  include_once __DIR__ . '/../../server/supabase.php';
  if (function_exists('supabase_is_admin_request')) {
    $fb = supabase_is_admin_request();
    if (is_array($fb) && !empty($fb['ok'])) $admin_verified_via_firebase = true;
  }
}

// require admin session otherwise
$isAdminSession = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['is_admin']);
if (!$isAdminSession && !$admin_verified_via_firebase) {
  header('Location: /dashboard/admin/login.php');
  exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // POST update
  $token = $_POST['csrf_token'] ?? '';
  if (!$admin_verified_via_firebase && (!hash_equals($_SESSION['csrf_token'], $token))) {
    $flash = 'Token keamanan tidak valid.';
    } else {
    $nama = $_POST['nama_nasabah'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $nohp = $_POST['no_hp'] ?? '';
    $saldo = isset($_POST['saldo']) ? floatval($_POST['saldo']) : 0;
    $status = $_POST['status_akun'] ?? 'menunggu';
    if (function_exists('supabase_request')) {
      $patch = [
        'nama_nasabah' => $nama,
        'alamat' => $alamat ?: null,
        'no_hp' => $nohp,
        'saldo' => $saldo,
        'status_akun' => $status
      ];
      $r = supabase_request('PATCH', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id), $patch, true);
      if ($r && isset($r['status']) && $r['status'] >= 200 && $r['status'] < 300) {
        $flash = 'Data nasabah berhasil diperbarui.';
      } else {
        $flash = 'Gagal memperbarui data nasabah.';
      }
    } else {
      $flash = 'Supabase helper tidak tersedia di server.';
    }
  }
}

// Load nasabah
$nasabah = null;
if ($id > 0) {
  if (function_exists('supabase_request')) {
    $r = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id) . '&select=id_nasabah,nama_nasabah,alamat,no_hp,saldo,status_akun', null, true);
    if ($r && isset($r['status']) && $r['status'] >= 200 && $r['status'] < 300) {
      $rows = $r['body'] ?? [];
      if (is_array($rows) && count($rows) > 0) $nasabah = $rows[0];
    }
  }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Edit Nasabah</title>
  <link rel="stylesheet" href="./assets/css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="app">
    <?php $activePage = 'nasabah'; include __DIR__ . '/sidebar.php'; ?>
    <main class="main">
      <h1>Edit Nasabah</h1>
      <?php if (!empty($flash)): ?>
        <div style="margin:12px 0; color:#065f46; font-weight:600;"><?= htmlspecialchars($flash) ?></div>
      <?php endif; ?>

      <?php if (!$nasabah): ?>
        <div class="card">Nasabah tidak ditemukan.</div>
      <?php else: ?>
        <section class="card" style="max-width:720px;">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-group">
              <label>Nama</label>
              <input name="nama_nasabah" value="<?= htmlspecialchars($nasabah['nama_nasabah']) ?>" required />
            </div>
            <div class="form-group">
              <label>Alamat</label>
              <input name="alamat" value="<?= htmlspecialchars($nasabah['alamat']) ?>" />
            </div>
            <div class="form-group">
              <label>No. HP</label>
              <input name="no_hp" value="<?= htmlspecialchars($nasabah['no_hp']) ?>" />
            </div>
            <div class="form-group">
              <label>Saldo (Rp)</label>
              <input type="number" step="0.01" name="saldo" value="<?= htmlspecialchars($nasabah['saldo']) ?>" />
            </div>
            <div class="form-group">
              <label>Status</label>
              <div class="select-wrapper">
                <select name="status_akun">
                  <option value="aktif" <?= $nasabah['status_akun']==='aktif' ? 'selected' : '' ?>>Aktif</option>
                  <option value="menunggu" <?= $nasabah['status_akun']==='menunggu' ? 'selected' : '' ?>>Menunggu</option>
                  <option value="nonaktif" <?= $nasabah['status_akun']==='nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
              </div>
            </div>
            <div style="margin-top:12px;">
              <button class="btn btn-primary">Simpan Perubahan</button>
              <a class="btn btn-outline-secondary" href="/dashboard/admin/nasabah.php">Kembali</a>
            </div>
          </form>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
