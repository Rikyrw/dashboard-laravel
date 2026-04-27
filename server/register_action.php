<?php
// server/register_action.php
// Handle nasabah registration via Supabase REST (server-side).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Allow JSON debug checks by GET if ?_debug=1
    $isDebug = (isset($_GET['_debug']) && $_GET['_debug'] === '1') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isDebug) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Method must be POST']);
        exit;
    }
    header('Location: /register.php');
    exit;
}

require_once __DIR__ . '/supabase.php';

$nama = trim($_POST['nama'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$alamat = trim($_POST['alamat'] ?? '');
$no_hp = trim($_POST['no_hp'] ?? '');

// Debug detection and helper
$isDebug = (isset($_GET['_debug']) && $_GET['_debug'] === '1') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
function send_json($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// Basic validation
if (!$nama || !$email || !$password || !$no_hp) {
    if ($isDebug) send_json(['ok' => false, 'error' => 'Nama, email, password, dan nomor HP wajib diisi.'], 400);
    $msg = urlencode('Nama, email, password, dan nomor HP wajib diisi.');
    header('Location: /register.php?error=' . $msg);
    exit;
}
if ($password !== $password_confirm) {
    if ($isDebug) send_json(['ok' => false, 'error' => 'Password dan konfirmasi tidak cocok.'], 400);
    $msg = urlencode('Password dan konfirmasi tidak cocok.');
    header('Location: /register.php?error=' . $msg);
    exit;
}
if (strlen($password) < 6) {
    if ($isDebug) send_json(['ok' => false, 'error' => 'Password minimal 6 karakter.'], 400);
    $msg = urlencode('Password minimal 6 karakter.');
    header('Location: /register.php?error=' . $msg);
    exit;
}

// Check existing email
$check = supabase_request('GET', '/rest/v1/nasabah?select=id_nasabah&email=eq.' . urlencode($email), null, true);
if ($check && isset($check['status']) && $check['status'] >= 200 && $check['status'] < 300) {
    $rows = $check['body'];
    if (is_array($rows) && count($rows) > 0) {
        if ($isDebug) send_json(['ok' => false, 'error' => 'Email sudah terdaftar.'], 409);
        $msg = urlencode('Email sudah terdaftar.');
        header('Location: /register.php?error=' . $msg);
        exit;
    }
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$new = [
    'nama_nasabah' => $nama,
    'email' => $email,
    'password' => $password_hash,
    'alamat' => $alamat ?: null,
    'no_hp' => $no_hp,
    'tanggal_daftar' => date('Y-m-d H:i:s'),
    'status_akun' => 'menunggu',
    'saldo' => 0
];

$res = supabase_request('POST', '/rest/v1/nasabah', $new, true);
if ($res && isset($res['status']) && $res['status'] >= 200 && $res['status'] < 300) {
    if ($isDebug) send_json(['ok' => true, 'message' => 'Pendaftaran berhasil'], 201);
    header('Location: /login.php?registered=1');
    exit;
} else {
    $err = $res['error'] ?? 'Gagal mendaftar';
    if ($isDebug) send_json(['ok' => false, 'error' => 'Pendaftaran gagal. ' . ($err ?: '')], 500);
    $msg = urlencode('Pendaftaran gagal. ' . ($err ?: ''));
    header('Location: /register.php?error=' . $msg);
    exit;
}

?>
