<?php
// server/login_action.php
// Handle nasabah login via Supabase REST (server-side). Sets PHP session on success.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Allow JSON debug checks by GET if ?_debug=1
    $isDebug = (isset($_GET['_debug']) && $_GET['_debug'] === '1') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isDebug) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Method must be POST']);
        exit;
    }
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/supabase.php';
session_start();

// Debug mode: return JSON instead of redirects when ?_debug=1 or Accept: application/json
$isDebug = (isset($_GET['_debug']) && $_GET['_debug'] === '1') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
function send_json($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    if ($isDebug) send_json(['ok' => false, 'error' => 'Email dan password diperlukan.'], 400);
    $msg = urlencode('Email dan password diperlukan.');
    header('Location: /login.php?error=' . $msg);
    exit;
}

// Query nasabah by email and only aktif accounts
$queryPath = '/rest/v1/nasabah?select=*&email=eq.' . urlencode($email) . '&status_akun=eq.aktif';
$res = supabase_request('GET', $queryPath, null, true);
if (!$res || !isset($res['status']) || $res['status'] < 200 || $res['status'] >= 300) {
    if ($isDebug) send_json(['ok' => false, 'error' => 'Login gagal. Tidak dapat menghubungi backend.'], 502);
    $msg = urlencode('Login gagal. Periksa kredensial Anda.');
    header('Location: /login.php?error=' . $msg);
    exit;
}

$rows = $res['body'] ?? [];
if (!is_array($rows) || count($rows) === 0) {
    if ($isDebug) send_json(['ok' => false, 'error' => 'Email atau password salah.'], 401);
    $msg = urlencode('Email atau password salah.');
    header('Location: /login.php?error=' . $msg);
    exit;
}

$user = $rows[0];
$hash = $user['password'] ?? '';
if (!password_verify($password, $hash)) {
    if ($isDebug) send_json(['ok' => false, 'error' => 'Email atau password salah.'], 401);
    $msg = urlencode('Email atau password salah.');
    header('Location: /login.php?error=' . $msg);
    exit;
}

// Successful login: set session
$_SESSION['id_nasabah'] = $user['id_nasabah'] ?? $user['id'] ?? null;
$_SESSION['nama_nasabah'] = $user['nama_nasabah'] ?? '';
$_SESSION['email'] = $user['email'] ?? '';
$_SESSION['saldo'] = $user['saldo'] ?? 0;

if ($isDebug) {
    // Return limited user info for debugging (do not include password hash)
    send_json(['ok' => true, 'redirect' => '/dashboard/nasabah/index.php', 'user' => [
        'id_nasabah' => $_SESSION['id_nasabah'],
        'nama_nasabah' => $_SESSION['nama_nasabah'],
        'email' => $_SESSION['email'],
        'saldo' => $_SESSION['saldo']
    ]]);
}

// Redirect to nasabah dashboard
header('Location: /dashboard/nasabah/index.php');
exit;

?>
