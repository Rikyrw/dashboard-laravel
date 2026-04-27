<?php
// admin_action.php - VERSION DIPERBAIKI
session_start();
include 'auth_check.php';

// Hanya Super Admin yang bisa mengelola admin lain
requireSuperAdmin();

header('Content-Type: application/json');

if (file_exists(__DIR__ . '/../../server/supabase.php')) {
    include_once __DIR__ . '/../../server/supabase.php';
}

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Aksi tidak valid'];

// Validasi CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    $response['message'] = 'Token keamanan tidak valid';
    echo json_encode($response);
    exit;
}

if (!function_exists('supabase_request')) {
    $response['message'] = 'Supabase helper tidak tersedia';
    echo json_encode($response);
    exit;
}

switch ($action) {
    case 'add':
        // Tambah admin baru
        $data = [
            'username' => $_POST['username'] ?? '',
            'nama_lengkap' => $_POST['nama_lengkap'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT),
            'no_hp' => $_POST['no_hp'] ?? '',
            'alamat' => $_POST['alamat'] ?? '',
            'role' => $_POST['role'] ?? 'operator',
            'status' => 'aktif'
        ];

        $result = supabase_request('POST', '/rest/v1/admin', $data, true);
        
        if ($result && isset($result['status']) && $result['status'] >= 200 && $result['status'] < 300) {
            $response = ['status' => 'success', 'message' => 'Admin berhasil ditambahkan'];
        } else {
            $errorMsg = isset($result['body']) ? json_encode($result['body']) : 'Unknown error';
            $response = ['status' => 'error', 'message' => 'Gagal menambahkan admin: ' . $errorMsg];
        }
        break;

    case 'edit':
        // Edit admin
        $id = $_POST['id_admin'] ?? 0;
        $data = [
            'username' => $_POST['username'] ?? '',
            'nama_lengkap' => $_POST['nama_lengkap'] ?? '',
            'email' => $_POST['email'] ?? '',
            'no_hp' => $_POST['no_hp'] ?? '',
            'alamat' => $_POST['alamat'] ?? '',
            'role' => $_POST['role'] ?? 'operator',
            'status' => $_POST['status'] ?? 'aktif'
        ];

        // Jika ada password baru
        if (!empty($_POST['password'])) {
            $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $result = supabase_request('PATCH', '/rest/v1/admin?id_admin=eq.' . $id, $data, true);
        
        if ($result && isset($result['status']) && $result['status'] >= 200 && $result['status'] < 300) {
            $response = ['status' => 'success', 'message' => 'Admin berhasil diperbarui'];
        } else {
            $errorMsg = isset($result['body']) ? json_encode($result['body']) : 'Unknown error';
            $response = ['status' => 'error', 'message' => 'Gagal memperbarui admin: ' . $errorMsg];
        }
        break;

    case 'delete':
        // Hapus admin (soft delete)
        $id = $_POST['id_admin'] ?? 0;
        
        // Jangan izinkan menghapus diri sendiri
        $admin_info = getAdminInfo();
        if ($id == $admin_info['id']) {
            $response = ['status' => 'error', 'message' => 'Tidak bisa menghapus akun sendiri'];
            break;
        }

        $data = ['status' => 'nonaktif'];
        $result = supabase_request('PATCH', '/rest/v1/admin?id_admin=eq.' . $id, $data, true);
        
        if ($result && isset($result['status']) && $result['status'] >= 200 && $result['status'] < 300) {
            $response = ['status' => 'success', 'message' => 'Admin berhasil dinonaktifkan'];
        } else {
            $errorMsg = isset($result['body']) ? json_encode($result['body']) : 'Unknown error';
            $response = ['status' => 'error', 'message' => 'Gagal menonaktifkan admin: ' . $errorMsg];
        }
        break;

    default:
        $response = ['status' => 'error', 'message' => 'Aksi tidak dikenali'];
}

echo json_encode($response);
?>