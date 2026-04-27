<?php
session_start();
include 'config.php';

// Fungsi yang SAMA PERSIS dengan Android
function hashPasswordLikeAndroid($password, $salt) {
    $saltBinary = base64_decode($salt);
    $hashed = hash('sha256', $saltBinary . $password, true);
    return base64_encode($hashed);
}

function generateSalt() {
    $salt = random_bytes(16);
    return base64_encode($salt);
}

if (isset($_POST['register'])) {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    $alamat = $_POST['alamat'];
    $no_hp = $_POST['no_hp'];

    try {
        // VALIDASI 1: Konfirmasi password
        if ($password !== $konfirmasi_password) {
            $error = "Password dan konfirmasi password tidak sama!";
        } 
        // VALIDASI 2: Panjang password
        elseif (strlen($password) > 8) {
            $error = "Password maksimal 8 karakter!";
        }
        // VALIDASI 3: Check existing user
        else {
            $existing = supabase('nasabah?select=id_nasabah&or=(email.eq.' . $email . ',username.eq.' . $username . ')');
            
            if ($existing && count($existing) > 0) {
                $error = "Email atau username sudah terdaftar!";
            } else {
                // Generate salt
                $salt = generateSalt();
                
                // Hash password seperti Android
                $password_hash = hashPasswordLikeAndroid($password, $salt);
                
                // Hash konfirmasi password (sama dengan password)
                $konfirmasi_hash = hashPasswordLikeAndroid($konfirmasi_password, $salt);
                
                // DEBUG (opsional, bisa dihapus di production)
                echo "<div style='background: #e8f5e8; padding: 10px; margin: 10px 0; border: 1px solid #4caf50;'>";
                echo "<strong>🔍 DEBUG REGISTRATION:</strong><br>";
                echo "Password: " . htmlspecialchars($password) . "<br>";
                echo "Konfirmasi Password: " . htmlspecialchars($konfirmasi_password) . "<br>";
                echo "Salt (Base64): " . htmlspecialchars($salt) . "<br>";
                echo "Hashed Password: " . htmlspecialchars($password_hash) . "<br>";
                echo "Hashed Konfirmasi: " . htmlspecialchars($konfirmasi_hash) . "<br>";
                echo "Match: " . ($password_hash === $konfirmasi_hash ? '✅' : '❌') . "<br>";
                echo "</div>";

                $newUser = [
                    'nama_nasabah' => $nama,
                    'username' => $username,
                    'email' => $email,
                    'password' => $password_hash,
                    'konfirmasi_password' => $konfirmasi_hash, // Tambah ini
                    'salt' => $salt,
                    'alamat' => $alamat,
                    'no_hp' => $no_hp,
                    'tanggal_daftar' => date('Y-m-d'),
                    'status_akun' => 'aktif',
                    'saldo' => 0,
                    'metode_kontak' => 'whatsapp'
                ];
                
                $result = supabase('nasabah', 'POST', $newUser);
                
                if ($result && isset($result[0]['id_nasabah'])) {
                    $success = "Pendaftaran berhasil! Silakan login.";
                    
                    // Redirect otomatis setelah 3 detik (opsional)
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 3000);
                    </script>";
                } else {
                    $error = "Gagal mendaftar. Coba lagi.";
                }
            }
        }
    } catch (Exception $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="flex justify-center items-center min-h-screen bg-gray-100">
  <div class="bg-white p-8 rounded-xl shadow-md w-full max-w-md" data-aos="fade-up">
    <h2 class="text-2xl font-bold text-center text-green-600 mb-6">Daftar Nasabah</h2>

    <?php if (isset($error)) : ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?= htmlspecialchars($error); ?>
      </div>
    <?php elseif (isset($success)) : ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?= htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-4">
        <input type="text" name="nama" placeholder="Nama Lengkap" required 
               class="w-full p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
      </div>
      
      <div class="mb-4">
        <input type="text" name="username" placeholder="Username" required 
               class="w-full p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
      </div>
      
      <div class="mb-4">
        <input type="email" name="email" placeholder="Email" required 
               class="w-full p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
      </div>
      
      <div class="mb-4">
        <input type="password" name="password" placeholder="Password (max 8 karakter)" 
               maxlength="8" required 
               class="w-full p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500"
               id="password">
        <div class="text-sm text-gray-500 mt-1">
          Maksimal 8 karakter
        </div>
      </div>
      
      <div class="mb-4">
        <input type="password" name="konfirmasi_password" placeholder="Konfirmasi Password" 
               maxlength="8" required 
               class="w-full p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500"
               id="konfirmasi_password">
        <div class="text-sm mt-1">
          <span id="password-match" class="hidden">
            <span class="text-green-600">✓ Password cocok</span>
          </span>
          <span id="password-mismatch" class="hidden">
            <span class="text-red-600">✗ Password tidak cocok</span>
          </span>
        </div>
      </div>
      
      <div class="mb-4">
        <textarea name="alamat" placeholder="Alamat" 
                  class="w-full p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
      </div>
      
      <div class="mb-6">
        <input type="text" name="no_hp" placeholder="Nomor HP" required 
               class="w-full p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
      </div>
      
      <button type="submit" name="register" 
              class="w-full bg-green-600 text-white py-3 rounded hover:bg-green-700 transition duration-300 font-semibold">
        Daftar
      </button>
    </form>

    <p class="mt-4 text-center text-gray-600">
      Sudah punya akun? <a href="login.php" class="text-green-600 hover:underline font-medium">Login di sini</a>
    </p>
  </div>
</div>

<script>
// Validasi real-time konfirmasi password
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const konfirmasi = document.getElementById('konfirmasi_password');
    const match = document.getElementById('password-match');
    const mismatch = document.getElementById('password-mismatch');
    
    function validatePassword() {
        if (password.value === '' || konfirmasi.value === '') {
            match.classList.add('hidden');
            mismatch.classList.add('hidden');
            return;
        }
        
        if (password.value === konfirmasi.value) {
            match.classList.remove('hidden');
            mismatch.classList.add('hidden');
        } else {
            match.classList.add('hidden');
            mismatch.classList.remove('hidden');
        }
    }
    
    password.addEventListener('input', validatePassword);
    konfirmasi.addEventListener('input', validatePassword);
});
</script>

<?php include 'includes/footer.php'; ?>