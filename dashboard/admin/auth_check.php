<?php
// auth_check.php - VERSION KOMPLIT DIPERBAIKI DENGAN ROLE MANAGEMENT
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function untuk normalize role
function normalizeRole($role) {
    if (empty($role)) return 'operator';
    
    $role = strtolower(trim($role));
    
    // Mapping untuk konsistensi
    $roleMap = [
        'super admin' => 'superadmin',
        'superadmin' => 'superadmin',
        'admin' => 'admin',
        'administrator' => 'admin',
        'operator' => 'operator',
        'staff' => 'operator'
    ];
    
    return $roleMap[$role] ?? $role;
}

// Cek session admin
function checkAdminSession() {
    // Debug mode
    $debug = false;
    
    if ($debug) {
        error_log("=== CHECK ADMIN SESSION ===");
        error_log("Session ID: " . session_id());
        error_log("Session Data: " . json_encode($_SESSION));
    }
    
    // Cek kondisi login
    $isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    $hasAdminId = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
    $hasAdminRole = isset($_SESSION['admin_role']) && !empty($_SESSION['admin_role']);
    
    if ($debug) {
        error_log("isLoggedIn: " . ($isLoggedIn ? 'YES' : 'NO'));
        error_log("hasAdminId: " . ($hasAdminId ? 'YES' : 'NO'));
        error_log("hasAdminRole: " . ($hasAdminRole ? 'YES' : 'NO'));
    }
    
    return $isLoggedIn && $hasAdminId && $hasAdminRole;
}

// Redirect jika bukan admin
function requireAdmin() {
    if (!checkAdminSession()) {
        // Simpan URL saat ini untuk redirect setelah login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        
        // Set pesan error
        $_SESSION['login_error'] = 'Silakan login terlebih dahulu';
        
        // Redirect ke login
        header('Location: login.php');
        exit;
    }
}

// Cek role dengan normalize
function isSuperAdmin() {
    if (!isset($_SESSION['admin_role'])) return false;
    
    $role = normalizeRole($_SESSION['admin_role']);
    return ($role === 'superadmin');
}

function isAdmin() {
    if (!isset($_SESSION['admin_role'])) return false;
    
    $role = normalizeRole($_SESSION['admin_role']);
    return in_array($role, ['superadmin', 'admin']);
}

function isOperator() {
    if (!isset($_SESSION['admin_role'])) return false;
    
    $role = normalizeRole($_SESSION['admin_role']);
    return in_array($role, ['superadmin', 'admin', 'operator']);
}

// Restrict to Super Admin only
function requireSuperAdmin() {
    requireAdmin();
    if (!isSuperAdmin()) {
        // Tampilkan error atau redirect
        echo '<div class="alert alert-error" style="margin: 20px; padding: 15px;">
                <h3>⚠️ Akses Ditolak</h3>
                <p>Hanya Super Admin yang dapat mengakses halaman ini.</p>
                <p>Role Anda: <strong>' . htmlspecialchars($_SESSION['admin_role'] ?? 'Tidak diketahui') . '</strong></p>
                <div style="margin-top: 15px;">
                    <a href="dashboard.php" class="btn btn-primary" style="display: inline-block; padding: 8px 16px; background: #059669; color: white; text-decoration: none; border-radius: 5px;">Kembali ke Dashboard</a>
                </div>
              </div>';
        exit;
    }
}

// Get admin info
function getAdminInfo() {
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'nama' => $_SESSION['admin_nama'] ?? 'Admin',
        'email' => $_SESSION['admin_email'] ?? '',
        'role' => normalizeRole($_SESSION['admin_role'] ?? 'operator'),
        'username' => $_SESSION['admin_username'] ?? '',
        'original_role' => $_SESSION['admin_role'] ?? 'operator'
    ];
}

// Redirect berdasarkan role (untuk halaman khusus)
function redirectBasedOnRole() {
    if (!isset($_SESSION['admin_role'])) {
        return 'dashboard.php';
    }
    
    $role = normalizeRole($_SESSION['admin_role']);
    
    // Atur redirect berdasarkan role jika diperlukan
    $redirectMap = [
        'superadmin' => 'dashboard.php',
        'admin' => 'dashboard.php',
        'operator' => 'dashboard.php'
    ];
    
    return $redirectMap[$role] ?? 'dashboard.php';
}

// Dapatkan halaman yang boleh diakses berdasarkan role
function getPagesByRole($role) {
    $role = normalizeRole($role);
    
    $commonPages = ['dashboard.php', 'nasabah.php', 'transaksi.php', 'sampah.php', 'laporan.php'];
    
    $pagesByRole = [
        'superadmin' => array_merge($commonPages, ['pengaturan.php', 'edit-nasabah.php', 'hapus-nasabah.php', 'riwayat-nasabah.php']),
        'admin' => array_merge($commonPages, ['edit-nasabah.php', 'riwayat-nasabah.php']),
        'operator' => array_merge(['dashboard.php', 'nasabah.php', 'transaksi.php', 'sampah.php']),
    ];
    
    return $pagesByRole[$role] ?? $commonPages;
}

// Cek apakah halaman saat ini diizinkan untuk role
function isPageAllowed($pageName) {
    $role = $_SESSION['admin_role'] ?? '';
    $allowedPages = getPagesByRole($role);
    return in_array($pageName, $allowedPages);
}

// Log activity
function logAdminActivity($activity) {
    $admin = getAdminInfo();
    $log = date('Y-m-d H:i:s') . " - Admin: {$admin['nama']} ({$admin['email']}) - Role: {$admin['role']} - " . $activity;
    error_log($log);
}

// Generate CSRF token
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>