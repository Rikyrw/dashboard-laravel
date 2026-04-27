<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/config.php';

$admin_verified_via_firebase = false;
if (file_exists(__DIR__ . '/../../server/supabase.php')) {
  include_once __DIR__ . '/../../server/supabase.php';
  if (function_exists('supabase_is_admin_request')) {
    $fb = supabase_is_admin_request();
    if (is_array($fb) && !empty($fb['ok'])) $admin_verified_via_firebase = true;
  }
}

$isAdminSession = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['is_admin']);
if (!$isAdminSession && !$admin_verified_via_firebase) {
  header('Location: /dashboard/admin/login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /dashboard/admin/nasabah.php');
  exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$csrf = $_POST['csrf_token'] ?? '';
if (!$admin_verified_via_firebase && (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf))) {
  $_SESSION['flash_nasabah'] = 'Token CSRF tidak valid.';
  header('Location: /dashboard/admin/nasabah.php');
  exit;
}

if ($id <= 0) {
  $_SESSION['flash_nasabah'] = 'ID nasabah tidak valid.';
  header('Location: /dashboard/admin/nasabah.php');
  exit;
}

// Prefer soft-delete: set status_akun='deleted' and deleted_at if column exists
// Prefer soft-delete by attempting to set deleted_at; if the column or permission is not available,
// fall back to setting `status_akun = 'deleted'`.
if (function_exists('supabase_request')) {
  $now = date('Y-m-d H:i:s');
  // First try to PATCH deleted_at + status_akun
  $patch = ['status_akun' => 'deleted', 'deleted_at' => $now];
  $res = supabase_request('PATCH', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id), $patch, true);
  if ($res && isset($res['status']) && $res['status'] >= 200 && $res['status'] < 300) {
    $_SESSION['flash_nasabah'] = 'Nasabah berhasil dihapus (soft-delete).';
  } else {
    // Try fallback: only update status_akun
    $res2 = supabase_request('PATCH', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id), ['status_akun' => 'deleted'], true);
    if ($res2 && isset($res2['status']) && $res2['status'] >= 200 && $res2['status'] < 300) {
      $_SESSION['flash_nasabah'] = 'Nasabah berhasil dihapus (ditandai).';
    } else {
      $_SESSION['flash_nasabah'] = 'Gagal menghapus nasabah.';
    }
  }
} else {
  $_SESSION['flash_nasabah'] = 'Supabase helper tidak tersedia di server.';
}

header('Location: /dashboard/admin/nasabah.php');
exit;
