<?php
// server/add_jenis_sampah.php
// Receives POST from admin UI to add a new jenis_sampah row to Supabase
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../dashboard/admin/sampah.php');
  exit;
}

require_once __DIR__ . '/supabase.php';

$nama = trim($_POST['nama_jenis'] ?? '');
$harga = isset($_POST['harga_per_kg']) ? floatval($_POST['harga_per_kg']) : null;
$stok = isset($_POST['stok_kg']) ? floatval($_POST['stok_kg']) : 0.0;
$status = trim($_POST['status'] ?? 'aktif');

if ($nama === '') {
  $err = urlencode('Nama jenis sampah wajib diisi');
  header('Location: ../dashboard/admin/sampah.php?error=' . $err);
  exit;
}

$payload = [
  'nama_jenis' => $nama,
  'harga_per_kg' => $harga,
  'stok_kg' => $stok,
  'status' => $status
];

// Use safe wrapper if available
if (function_exists('supabase_safe_request')) {
  $res = supabase_safe_request('POST', '/rest/v1/jenis_sampah', $payload, true);
  if ($res['ok']) {
    header('Location: ../dashboard/admin/sampah.php?msg=' . urlencode('Jenis sampah berhasil ditambahkan'));
    exit;
  } else {
    $err = urlencode('Gagal menambahkan: ' . ($res['message'] ?? 'Unknown'));
    header('Location: ../dashboard/admin/sampah.php?error=' . $err);
    exit;
  }
} else {
  $r = supabase_request('POST', '/rest/v1/jenis_sampah', $payload, true);
  if ($r && isset($r['status']) && $r['status'] >=200 && $r['status'] < 300) {
    header('Location: ../dashboard/admin/sampah.php?msg=' . urlencode('Jenis sampah berhasil ditambahkan'));
    exit;
  } else {
    $err = urlencode('Gagal menambahkan jenis sampah');
    header('Location: ../dashboard/admin/sampah.php?error=' . $err);
    exit;
  }
}

?>
