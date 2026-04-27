<?php
session_start();
require_once './login/config.php';

if (!isset($_SESSION['id_nasabah'])) {
    header("Location: login/login.php");
    exit();
}

$user_id = $_SESSION['id_nasabah'];
$success = '';
$error = '';

// Ambil data user
$user_data = supabase('nasabah?select=*&id_nasabah=eq.' . $user_id);
$user = $user_data[0] ?? [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    
    try {
        $updateData = [
            'nama_nasabah' => $nama,
            'alamat' => $alamat,
            'no_hp' => $no_hp
        ];
        
        $result = supabase('nasabah?id_nasabah=eq.' . $user_id, 'PATCH', $updateData);
        
        if ($result) {
            $success = "Profil berhasil diperbarui!";
            // Update session
            $_SESSION['nama_nasabah'] = $nama;
            // Refresh data
            $user_data = supabase('nasabah?select=*&id_nasabah=eq.' . $user_id);
            $user = $user_data[0];
        } else {
            $error = "Gagal memperbarui profil.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Profil | GreenPoint</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">
    <style>
    .profile-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        color: white;
        font-family: inherit;
        font-size: 16px;
    }

    .profile-btn:hover {
        background: #ffffffff;
        color: #14532d;
        transform: translateY(-1px); /* Efek sedikit naik */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); /* Tambah bayangan */
    }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-green-600 text-white p-4">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-xl font-bold">Ubah Profil</h1>
                    <button class="profile-btn" onclick="window.location.href='profil.php'">
                        <i data-lucide="arrow-left" style="margin-right: 8px;"></i>
                        <span>Kembali ke Profil</span>
                    </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto p-4">
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow">
                <h2 class="text-2xl font-bold mb-6">Ubah Data Profil</h2>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Nama Lengkap</label>
                        <input type="text" name="nama" 
                               value="<?php echo htmlspecialchars($user['nama_nasabah'] ?? ''); ?>"
                               class="w-full p-3 border border-gray-300 rounded" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Email</label>
                        <input type="email" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                               class="w-full p-3 border border-gray-300 rounded bg-gray-100" readonly>
                        <p class="text-sm text-gray-500 mt-1">Email tidak dapat diubah</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Username</label>
                        <input type="text" 
                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                               class="w-full p-3 border border-gray-300 rounded bg-gray-100" readonly>
                        <p class="text-sm text-gray-500 mt-1">Username tidak dapat diubah</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Alamat</label>
                        <textarea name="alamat" rows="3"
                                  class="w-full p-3 border border-gray-300 rounded"><?php echo htmlspecialchars($user['alamat'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">No. Handphone</label>
                        <input type="text" name="no_hp" 
                               value="<?php echo htmlspecialchars($user['no_hp'] ?? ''); ?>"
                               class="w-full p-3 border border-gray-300 rounded">
                    </div>
                    
                    <div class="flex gap-4">
                        <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded hover:bg-green-700">
                            <i data-lucide="save" class="inline mr-2"></i> Simpan Perubahan
                        </button>
                        <a href="profil.php" class="bg-gray-500 text-white px-6 py-3 rounded hover:bg-gray-600">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white p-4 text-center">
            <p>© 2024 GreenPoint. Semua Hak Dilindungi.</p>
        </footer>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>