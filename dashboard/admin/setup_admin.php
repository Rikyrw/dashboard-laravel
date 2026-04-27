<?php
// setup_admin.php - VERSION DIPERBAIKI
session_start();

// Include supabase helper
if (file_exists(__DIR__ . '/../../server/supabase.php')) {
    include_once __DIR__ . '/../../server/supabase.php';
}

$message = '';
$debug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? strtolower(str_replace(' ', '', $nama)));

    if (empty($email) || empty($password) || empty($nama)) {
        $message = '<div style="color: red; padding: 10px; background: #fee2e2; border-radius: 5px;">❌ Semua field harus diisi!</div>';
    } else {
        if (function_exists('supabase_request')) {
            // PERBAIKAN: Cek apakah email sudah ada dengan format query yang benar
            $emailExists = false;
            $usernameExists = false;

            // Cek email dengan beberapa format
            $emailQueries = [
                "/rest/v1/admin?email=eq." . urlencode($email),
                "/rest/v1/admin?email=eq." . urlencode("'" . $email . "'"),
                "/rest/v1/admin?email=ilike." . urlencode("'" . $email . "'")
            ];

            foreach ($emailQueries as $query) {
                $checkEmail = supabase_request('GET', $query, null, true);
                if ($checkEmail && isset($checkEmail['status']) && $checkEmail['status'] === 200) {
                    $existingEmail = $checkEmail['body'] ?? [];
                    if (count($existingEmail) > 0) {
                        $emailExists = true;
                        break;
                    }
                }
            }

            if ($emailExists) {
                $message = '<div style="color: orange; padding: 10px; background: #fef3c7; border-radius: 5px;">⚠️ Email sudah digunakan!</div>';
            }

            // Cek username dengan beberapa format
            $usernameQueries = [
                "/rest/v1/admin?username=eq." . urlencode($username),
                "/rest/v1/admin?username=eq." . urlencode("'" . $username . "'"),
                "/rest/v1/admin?username=ilike." . urlencode("'" . $username . "'")
            ];

            foreach ($usernameQueries as $query) {
                $checkUsername = supabase_request('GET', $query, null, true);
                if ($checkUsername && isset($checkUsername['status']) && $checkUsername['status'] === 200) {
                    $existingUsername = $checkUsername['body'] ?? [];
                    if (count($existingUsername) > 0) {
                        $usernameExists = true;
                        break;
                    }
                }
            }

            if ($usernameExists) {
                $message = '<div style="color: orange; padding: 10px; background: #fef3c7; border-radius: 5px;">⚠️ Username sudah digunakan!</div>';
            }

            if (empty($message)) {
                // PERBAIKAN: Normalize role untuk konsistensi
                $role = $_POST['role'] ?? 'operator';
                $role = strtolower(trim($role));

                // 2. Buat admin baru - SESUAI dengan struktur tabel Anda
                $adminData = [
                    'username' => $username,
                    'nama_lengkap' => $nama, // NAMA KOLOM: nama_lengkap
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'no_hp' => $_POST['no_hp'] ?? '081234567890',
                    'alamat' => $_POST['alamat'] ?? '',
                    'role' => $role, // Role yang sudah dinormalisasi
                    'status' => 'aktif'
                ];

                $debug .= "Data to insert: " . json_encode($adminData) . "<br>";

                $result = supabase_request('POST', '/rest/v1/admin', $adminData, true);

                $debug .= "Insert status: " . ($result['status'] ?? 'N/A') . "<br>";
                $debug .= "Insert response: " . json_encode($result['body'] ?? []) . "<br>";

                if ($result && isset($result['status']) && $result['status'] >= 200 && $result['status'] < 300) {
                    $message = '<div style="color: green; padding: 15px; background: #d1fae5; border-radius: 5px; border: 1px solid #10b981;">
                        <h3 style="margin: 0 0 10px 0;">✅ Admin berhasil dibuat!</h3>
                        <p><strong>Username:</strong> ' . htmlspecialchars($username) . '</p>
                        <p><strong>Nama:</strong> ' . htmlspecialchars($nama) . '</p>
                        <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                        <p><strong>Password:</strong> ' . htmlspecialchars($password) . '</p>
                        <p><strong>Role:</strong> ' . htmlspecialchars($role) . '</p>
                        <div style="margin-top: 15px;">
                            <a href="login.php" style="display: inline-block; padding: 10px 20px; background: #059669; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                                🚀 Klik di sini untuk Login
                            </a>
                        </div>
                    </div>';
                } else {
                    $errorMsg = isset($result['body']) && is_array($result['body']) ? json_encode($result['body']) : 'Unknown error';
                    $message = '<div style="color: red; padding: 10px; background: #fee2e2; border-radius: 5px;">
                        ❌ Gagal membuat admin!<br>
                        Error: ' . htmlspecialchars($errorMsg) . '<br>
                        Status: ' . ($result['status'] ?? 'N/A') . '
                    </div>';
                }
            }
        } else {
            $message = '<div style="color: red; padding: 10px; background: #fee2e2; border-radius: 5px;">❌ Supabase helper tidak tersedia!</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin • GreenPoint</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 30px;
        }

        h1 {
            color: #059669;
            margin-bottom: 10px;
            text-align: center;
        }

        .subtitle {
            color: #6b7280;
            text-align: center;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #059669;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }

        button:hover {
            background: #047857;
        }

        .info-box {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .debug-box {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px;
            margin-top: 15px;
            font-size: 12px;
            color: #6b7280;
            display: none;
        }

        .toggle-debug {
            color: #6b7280;
            font-size: 12px;
            cursor: pointer;
            margin-top: 10px;
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="setup-container">
        <h1>🔧 Setup Admin Pertama</h1>
        <p class="subtitle">Buat akun admin untuk GreenPoint System</p>

        <?php echo $message; ?>

        <div class="info-box">
            <strong>📋 Informasi Tabel Admin:</strong><br>
            ✅ Tabel: <code>public.admin</code><br>
            ✅ Kolom: <code>id_admin, username, password, nama_lengkap, email, alamat, no_hp, role, status</code><br>
            ✅ Role: <code>operator</code> (default), <code>admin</code>, atau <code>superadmin</code><br>
            ✅ Email harus unik
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" placeholder="admin" required value="admin">
            </div>

            <div class="form-group">
                <label>Nama Lengkap *</label>
                <input type="text" name="nama" placeholder="Nama Admin" required value="Administrator">
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" placeholder="admin@greenpoint.com" required value="admin@greenpoint.com">
            </div>

            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" placeholder="Password" required value="admin123">
            </div>

            <div class="form-group">
                <label>No. HP</label>
                <input type="text" name="no_hp" placeholder="081234567890" value="081234567890">
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="superadmin">Super Admin</option>
                    <option value="admin" selected>Admin</option>
                    <option value="operator">Operator</option>
                </select>
            </div>

            <div class="form-group">
                <label>Alamat (opsional)</label>
                <textarea name="alamat" rows="2" placeholder="Alamat admin"></textarea>
            </div>

            <button type="submit">🚀 Buat Admin</button>
        </form>

        <?php if (!empty($debug)): ?>
            <div class="toggle-debug" onclick="this.nextElementSibling.style.display='block'; this.style.display='none';">📊 Tampilkan Debug Info</div>
            <div class="debug-box">
                <strong>Debug Info:</strong><br>
                <?php echo $debug; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 20px; text-align: center;">
            <a href="login.php" style="color: #059669; text-decoration: none;">← Kembali ke Login</a> |
            <a href="test_connection.php" style="color: #059669; text-decoration: none;">Test Koneksi</a>
        </div>
    </div>

    <script>
        // Auto-generate username from nama
        document.querySelector('input[name="nama"]').addEventListener('input', function(e) {
            const nama = e.target.value.toLowerCase().replace(/\s+/g, '');
            document.querySelector('input[name="username"]').value = nama || 'admin';
        });

        // Show password
        const passwordInput = document.querySelector('input[name="password"]');
        const showPassBtn = document.createElement('button');
        showPassBtn.type = 'button';
        showPassBtn.textContent = '👁️';
        showPassBtn.style.cssText = 'position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px;';

        const passwordGroup = passwordInput.parentNode;
        passwordGroup.style.position = 'relative';
        passwordGroup.appendChild(showPassBtn);

        showPassBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
        });
    </script>
</body>

</html>