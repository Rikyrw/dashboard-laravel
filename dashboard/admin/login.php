<?php
// login.php - VERSION DIPERBAIKI DENGAN ROLE SELECTION
session_start();

// Debug mode - set false untuk production
define('DEBUG', true);

// Include supabase helper
$supabasePath = __DIR__ . '/../../server/supabase.php';
if (file_exists($supabasePath)) {
    include_once $supabasePath;
    
    if (DEBUG) {
        error_log("✅ supabase.php loaded successfully");
    }
} else {
    if (DEBUG) {
        error_log("❌ supabase.php NOT FOUND at: " . $supabasePath);
    }
    die("Sistem autentikasi tidak tersedia. Hubungi administrator.");
}

// Redirect jika sudah login
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $selected_role = trim($_POST['role'] ?? 'admin'); // TAMBAH INI

    if (DEBUG) {
        error_log("========================================");
        error_log("LOGIN ATTEMPT");
        error_log("Email: " . $email);
        error_log("Password provided: " . (!empty($password) ? "YES" : "NO"));
        error_log("Role selected: " . $selected_role);
    }

    // Validasi input
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi';
        if (DEBUG) error_log("Validation failed: empty fields");
    } else {
        // Cek apakah fungsi supabase_request tersedia
        if (!function_exists('supabase_request')) {
            $error = 'Sistem autentikasi tidak berfungsi.';
            if (DEBUG) error_log("❌ supabase_request function NOT FOUND");
        } else {
            // PERBAIKAN: Query Supabase dengan format yang benar
            // Coba beberapa format karena Supabase bisa sensitif
            $queries = [
                "/rest/v1/admin?email=eq." . urlencode($email),
                "/rest/v1/admin?email=eq." . urlencode("'" . $email . "'"),
                "/rest/v1/admin?email=ilike." . urlencode("'" . $email . "'")
            ];
            
            $result = null;
            foreach ($queries as $query) {
                if (DEBUG) error_log("Trying query: " . $query);
                $result = supabase_request('GET', $query, null, true);
                if ($result && isset($result['status']) && $result['status'] === 200) {
                    break;
                }
                if (DEBUG) error_log("Query failed with status: " . ($result['status'] ?? 'NO STATUS'));
            }
            
            if (DEBUG) {
                error_log("Supabase Final Response Status: " . ($result['status'] ?? 'NO STATUS'));
                error_log("Supabase Response Body: " . json_encode($result['body'] ?? []));
            }
            
            if ($result && isset($result['status'])) {
                if ($result['status'] >= 200 && $result['status'] < 300) {
                    $body = $result['body'] ?? [];
                    
                    if (is_array($body) && !empty($body)) {
                        $admin = $body[0];
                        
                        if (DEBUG) {
                            error_log("✅ Admin found!");
                            error_log("Admin Data: " . print_r($admin, true));
                            error_log("Stored Password: " . ($admin['password'] ?? 'NOT FOUND'));
                            error_log("Password length: " . strlen($admin['password'] ?? ''));
                            error_log("Role in DB: " . ($admin['role'] ?? 'NOT SET'));
                            error_log("Selected Role: " . $selected_role);
                        }
                        
                        $storedPassword = $admin['password'] ?? '';
                        $loginSuccess = false;
                        
                        // METODE 1: Password HASH (rekomendasi)
                        if (password_verify($password, $storedPassword)) {
                            if (DEBUG) error_log("✅ Password verified via password_verify()");
                            $loginSuccess = true;
                        }
                        // METODE 2: Password PLAINTEXT (fallback untuk development)
                        elseif ($password === $storedPassword) {
                            if (DEBUG) error_log("⚠️ Password matched as plaintext (insecure!)");
                            $loginSuccess = true;
                            
                            // Auto-upgrade ke hashed password
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $updateData = ['password' => $hashedPassword];
                            supabase_request('PATCH', "/rest/v1/admin?id_admin=eq." . $admin['id_admin'], $updateData, true);
                            
                            if (DEBUG) error_log("✅ Password upgraded to hash");
                        }
                        // METODE 3: Hash kustom MD5
                        elseif (md5($password) === $storedPassword) {
                            if (DEBUG) error_log("✅ Password verified via MD5");
                            $loginSuccess = true;
                        }
                        else {
                            if (DEBUG) error_log("❌ Password verification FAILED");
                            $error = 'Password salah';
                            $loginSuccess = false;
                        }
                        
                        if ($loginSuccess) {
                            // PERBAIKAN: Normalize role untuk konsistensi
                            $adminRole = $admin['role'] ?? 'operator';
                            $adminRole = strtolower(trim($adminRole));
                            $selectedRole = strtolower(trim($selected_role));
                            
                            // VALIDASI ROLE: Cek apakah role yang dipilih sesuai dengan role di database
                            if ($adminRole !== $selectedRole) {
                                $error = 'Anda tidak memiliki akses sebagai ' . ucfirst($selectedRole) . '. Role Anda: ' . ucfirst($adminRole);
                                if (DEBUG) error_log("❌ Role mismatch: DB=$adminRole, Selected=$selectedRole");
                                $loginSuccess = false;
                            } else {
                                // SET SESSION dengan nama kolom yang SESUAI
                                $_SESSION['admin_logged_in'] = true;
                                $_SESSION['admin_id'] = $admin['id_admin'];
                                $_SESSION['admin_nama'] = $admin['nama_lengkap'];
                                $_SESSION['admin_email'] = $admin['email'];
                                $_SESSION['admin_role'] = $adminRole; // Role yang sudah dinormalisasi
                                $_SESSION['admin_username'] = $admin['username'] ?? '';
                                $_SESSION['is_admin'] = true;
                                
                                // Set CSRF token untuk keamanan
                                if (empty($_SESSION['csrf_token'])) {
                                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                                }
                                
                                if (DEBUG) {
                                    error_log("✅ SESSION SET:");
                                    error_log("  - admin_id: " . $_SESSION['admin_id']);
                                    error_log("  - admin_nama: " . $_SESSION['admin_nama']);
                                    error_log("  - admin_role: " . $_SESSION['admin_role']);
                                    error_log("  - admin_email: " . $_SESSION['admin_email']);
                                    error_log("  Redirecting to dashboard...");
                                }
                                
                                // Redirect
                                header('Location: dashboard.php');
                                exit;
                            }
                        }
                    } else {
                        $error = 'Email tidak terdaftar sebagai admin';
                        if (DEBUG) error_log("❌ No admin found with email: " . $email);
                    }
                } else {
                    $error = 'Error dari server database (HTTP ' . ($result['status'] ?? '0') . ')';
                    if (DEBUG) error_log("❌ HTTP Error: " . ($result['status'] ?? 'NO STATUS'));
                    
                    // Jika debug, tampilkan error detail
                    if (DEBUG && isset($result['body'])) {
                        error_log("Error details: " . json_encode($result['body']));
                    }
                }
            } else {
                $error = 'Tidak bisa terhubung ke server database';
                if (DEBUG) error_log("❌ No response from Supabase");
            }
        }
    }
}

// Jika sampai di sini, berarti login gagal
if (DEBUG) {
    error_log("❌ Login FAILED - Showing login form");
    error_log("========================================");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin • GreenPoint</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-container { background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); width: 100%; max-width: 440px; overflow: hidden; }
        .login-header { background: #059669; padding: 32px; text-align: center; color: white; }
        .login-header h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .login-header p { opacity: 0.9; font-size: 14px; }
        .login-form { padding: 40px; }
        .form-group { margin-bottom: 24px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 16px; border: 2px solid #d1d5db; border-radius: 8px; font-size: 16px; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #059669; }
        .btn-login { width: 100%; padding: 14px; background: #059669; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-login:hover { background: #047857; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .alert-success { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
        .brand { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 24px; }
        .brand img { width: 40px; height: 40px; }
        .brand h2 { font-size: 24px; color: #059669; font-weight: 700; }
        .debug-info { background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; margin-top: 20px; font-size: 12px; color: #6b7280; }
        .debug-info h4 { margin-bottom: 8px; color: #374151; }
        .test-credentials { background: #dbeafe; border: 1px solid #93c5fd; border-radius: 8px; padding: 12px; margin-top: 15px; }
        .role-info { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 12px; margin-top: 15px; font-size: 13px; }
        .role-info h4 { margin: 0 0 8px 0; color: #0369a1; }
        .role-info ul { margin: 0; padding-left: 20px; }
        .select-wrapper { position: relative; width: 100%; }
        .select-wrapper select { width: 100%; padding: 12px 16px; border: 2px solid #d1d5db; border-radius: 8px; font-size: 16px; background-color: white; appearance: none; cursor: pointer; }
        .select-wrapper::after { content: '⌄'; position: absolute; right: 16px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #6b7280; font-size: 20px; }
        .password-group { position: relative; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="brand">
                <h2>GreenPoint Admin</h2>
            </div>
            <h1><i class="lucide-lock"></i> Admin Login</h1>
            <p>Masuk untuk mengelola sistem bank sampah</p>
        </div>
        
        <div class="login-form">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="lucide-alert-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email"><i class="lucide-mail"></i> Email</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="lucide-key"></i> Password</label>
                    <div class="password-group">
                        <input type="password" id="password" name="password" required 
                               placeholder="" 
                               value="">
                        <button type="button" id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px;">👁️</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="role"><i class="lucide-shield"></i> Login Sebagai</label>
                    <div class="select-wrapper">
                        <select id="role" name="role" required>
                            <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : 'selected'; ?>>Admin Sistem</option>
                            <option value="superadmin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'superadmin') ? 'selected' : ''; ?>>Super Admin</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="lucide-log-in"></i> Masuk ke Dashboard
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // JavaScript untuk debugging
        console.log("=== LOGIN PAGE DEBUG ===");
        console.log("PHP Session Status: <?php echo session_status(); ?>");
        console.log("PHP Session ID: <?php echo session_id(); ?>");
        
        // Auto-focus pada email field
        document.getElementById('email').focus();
        
        // Show/hide password
        const passwordInput = document.getElementById('password');
        const toggleButton = document.getElementById('togglePassword');
        
        toggleButton.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
        });
        
        // Auto-select role berdasarkan email
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value.toLowerCase();
            const roleSelect = document.getElementById('role');
            
            if (email.includes('superadmin')) {
                roleSelect.value = 'superadmin';
            } else if (email.includes('admin')) {
                roleSelect.value = 'admin';
            }
        });
    </script>
</body>
</html>