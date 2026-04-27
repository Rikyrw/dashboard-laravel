<?php
session_start();
require_once __DIR__ . '/../../../base_url.php';
include 'config.php';

// Fungsi verifikasi yang SAMA dengan Android
function verifyPasswordLikeAndroid($inputPassword, $storedHash, $salt)
{
    // Decode salt dari Base64
    $saltBinary = base64_decode($salt);

    // Hash seperti Android
    $hashedInput = hash('sha256', $saltBinary . $inputPassword, true);
    $hashedInputBase64 = base64_encode($hashedInput);

    return $hashedInputBase64 === $storedHash;

    // Di bagian verifyPasswordLikeAndroid, tambahkan pengecekan konfirmasi
    if ($loginSuccess) {
        // Optional: Verifikasi konfirmasi password juga cocok
        $inputKonfirmasiHash = hashPasswordLikeAndroid($password, $user['salt']);
        if ($inputKonfirmasiHash === $user['konfirmasi_password']) {
            echo "Konfirmasi password: ✅ VALID<br>";
        } else {
            echo "Konfirmasi password: ❌ MISMATCH<br>";
            // Bisa tambahkan log atau alert di sini
        }
    }
}

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $query = "nasabah?select=*&or=(username.eq." . urlencode($username) . ",email.eq." . urlencode($username) . ")&status_akun=eq.aktif";
        $user = supabase($query);

        if ($user && count($user) > 0) {
            $user = $user[0];

            echo "<div style='background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffc107;'>";
            echo "<strong>🔍 DEBUG LOGIN:</strong><br>";
            echo "Username/Email: " . htmlspecialchars($username) . "<br>";
            echo "User found: " . $user['username'] . " (ID: " . $user['id_nasabah'] . ")<br>";
            echo "Salt in DB: " . ($user['salt'] ?? 'NULL') . "<br>";
            echo "Hash in DB: " . substr($user['password'], 0, 30) . "...<br>";

            $loginSuccess = false;

            // Coba verifikasi dengan metode Android
            if (isset($user['salt']) && !empty($user['salt'])) {
                $loginSuccess = verifyPasswordLikeAndroid($password, $user['password'], $user['salt']);
                echo "Android-style verification: " . ($loginSuccess ? '✅ SUCCESS' : '❌ FAILED') . "<br>";

                // Debug: hitung hash untuk perbandingan
                $saltBinary = base64_decode($user['salt']);
                $calculatedHash = hash('sha256', $saltBinary . $password, true);
                $calculatedBase64 = base64_encode($calculatedHash);
                echo "Calculated hash: " . substr($calculatedBase64, 0, 30) . "...<br>";
            }

            // Fallback untuk user lama
            if (!$loginSuccess) {
                // Coba metode lama (password_hash)
                if (password_verify($password, $user['password'])) {
                    $loginSuccess = true;
                    echo "PHP password_verify: ✅ SUCCESS<br>";
                } else {
                    // Cek plain text (hanya untuk debugging)
                    if ($password === $user['password']) {
                        $loginSuccess = true;
                        echo "Plain text match: ✅ SUCCESS<br>";
                    }
                }
            }

            echo "</div>";

            if ($loginSuccess) {
                $_SESSION['id_nasabah'] = $user['id_nasabah'];
                $_SESSION['nama_nasabah'] = $user['nama_nasabah'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['saldo'] = $user['saldo'];

                header("Location: ../dashboard.php");
                exit();
            } else {
                $error = "Username/email atau password salah!";
            }
        } else {
            $error = "Username/email atau password salah!";
        }
    } catch (Exception $e) {
        $error = "Terjadi kesalahan sistem: " . $e->getMessage();
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="flex justify-center items-center min-h-screen bg-gray-100">
    <div class="bg-white p-8 rounded-xl shadow-md w-full max-w-md" data-aos="fade-up">
        <h2 class="text-2xl font-bold text-center text-green-600 mb-6">Login Nasabah</h2>

        <?php if (isset($error)) : ?>
            <p class="text-red-500 text-center mb-4"><?= htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <!-- Form dengan action relative -->
        <form method="POST" action="login.php">
            <input type="text" name="username" placeholder="Username atau Email" required
                class="w-full mb-4 p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
            <input type="password" name="password" placeholder="Password" required
                class="w-full mb-4 p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500">

            <!-- Button login yang lebih eksplisit -->
            <button type="submit" name="login"
                class="w-full bg-green-600 text-white py-3 rounded hover:bg-green-700 transition duration-300 font-semibold">
                Login ke Dashboard
            </button>
        </form>

        <p class="mt-4 text-center text-gray-600">
            Belum punya akun? <a href="<?= $BASE_URL ?>dashboard/nasabah/login/register.php" class="text-green-600 hover:underline font-medium">Daftar di sini</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>