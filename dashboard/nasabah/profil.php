<?php
session_start();
require './login/config.php';

// Check authentication
if (!isset($_SESSION['id_nasabah'])) {
    header("Location: ./login/login.php");
    exit();
}

// Ambil data user terbaru dari REST API
try {
    $user_data = supabase('nasabah?select=*&id_nasabah=eq.' . $_SESSION['id_nasabah']);
    
    if ($user_data && count($user_data) > 0) {
        $user = $user_data[0];
        $_SESSION['nama_nasabah'] = $user['nama_nasabah'];
        $_SESSION['saldo'] = $user['saldo'] ?? 0;
        $_SESSION['username'] = $user['username'] ?? '';
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['alamat'] = $user['alamat'] ?? '';
        $_SESSION['no_hp'] = $user['no_hp'] ?? '';
    } else {
        session_destroy();
        header("Location: ../login/login.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Generate initials dari nama
function getInitials($name) {
    $initials = '';
    $words = explode(' ', $name);
    foreach ($words as $word) {
        if (trim($word) !== '') {
            $initials .= strtoupper(substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    // Jika hanya 1 huruf, tambahkan huruf pertama lagi
    if (strlen($initials) == 1) {
        $initials .= strtoupper(substr($words[0], 1, 1));
    }
    return $initials;
}

$user_initials = getInitials($user['nama_nasabah'] ?? 'User');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Profil Saya | GreenPoint</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">
    <style>
        /* Tambahan style untuk avatar inisial */
        .avatar-initials {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #059669, #10b981);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 48px;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3);
            border: 4px solid white;
        }
        
        .profile-left {
            text-align: center;
        }
        
        .profile-content {
            display: flex;
            gap: 40px;
            max-width: 1000px;
            margin: 0 auto;
            align-items: flex-start;
        }
        
        @media (max-width: 768px) {
            .profile-content {
                flex-direction: column;
            }
        }
        
        .profile-left {
            flex: 1;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .profile-form {
            flex: 2;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .saldo-box {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 2px solid #bbf7d0;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .saldo-box span:first-child {
            font-size: 16px;
            color: #059669;
            font-weight: 500;
        }
        
        .saldo-box span:last-child {
            font-size: 24px;
            font-weight: 700;
            color: #059669;
        }
        
        .btn-transaksi {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-transaksi:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.3);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            background: #f9fafb;
        }
        
        .form-group input:read-only {
            background: #f3f4f6;
            cursor: not-allowed;
        }
        
        .form-actions {
            margin-top: 30px;
        }
        
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #059669;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #047857;
            transform: translateY(-2px);
        }
        
        .page-header {
            background: white;
            padding: 20px 0;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 30px;
        }
        
        .header-content {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header-content h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .subtle {
            color: #6b7280;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .profile-left h2 {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .subtitle {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="app">
        <?php
            $activePage = 'profil';
            include "sidebar.php";
        ?>

    <main class="main">
        <div class="page-header">
            <div class="header-content">
                <h2><i data-lucide="user"></i> Profil Saya</h2>
                <p class="subtle">Kelola informasi profil Anda</p>
            </div>
        </div>

        <section class="profile-content">
            <div class="profile-left">
                <div class="avatar-initials">
                    <?php echo htmlspecialchars($user_initials); ?>
                </div>
                <h2><?php echo htmlspecialchars($user['nama_nasabah'] ?? 'User'); ?></h2>
                <div class="subtitle"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                <div class="saldo-box" role="region" aria-label="Saldo akun">
                    <span>Saldo</span>
                    <span>Rp <?php echo number_format($user['saldo'] ?? 0, 0, ',', '.'); ?></span>
                </div>
                <a href="setor.php" class="btn-transaksi">
                    <i class="icon" data-lucide="plus-circle"></i>
                    Transaksi Setor
                </a>
            </div>

            <form class="profile-form" method="post" action="" aria-label="Form profil pengguna">
                <div class="form-group">
                    <label for="name">
                        <i class="icon" data-lucide="user"></i>
                        Nama Lengkap
                    </label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['nama_nasabah'] ?? ''); ?>" readonly />
                </div>

                <div class="form-group">
                    <label for="username">
                        <i class="icon" data-lucide="at-sign"></i>
                        Username
                    </label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly />
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="icon" data-lucide="mail"></i>
                        Email
                    </label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly />
                </div>

                <div class="form-group">
                    <label for="alamat">
                        <i class="icon" data-lucide="map-pin"></i>
                        Alamat
                    </label>
                    <input type="text" id="alamat" name="alamat" value="<?php echo htmlspecialchars($user['alamat'] ?? 'Belum diisi'); ?>" readonly />
                </div>

                <div class="form-group">
                    <label for="no_hp">
                        <i class="icon" data-lucide="phone"></i>
                        No. Handphone
                    </label>
                    <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($user['no_hp'] ?? 'Belum diisi'); ?>" readonly />
                </div>
                
                <div class="form-group">
                    <label for="tanggal_daftar">
                        <i class="icon" data-lucide="calendar"></i>
                        Tanggal Daftar
                    </label>
                    <input type="text" id="tanggal_daftar" name="tanggal_daftar" 
                           value="<?php 
                               if (isset($user['tanggal_daftar'])) {
                                   echo date('d F Y', strtotime($user['tanggal_daftar']));
                               } else {
                                   echo 'N/A';
                               }
                           ?>" 
                           readonly />
                </div>

                <div class="form-actions">
                    <a href="ubahprofil.php" class="btn-primary">
                        <i class="icon" data-lucide="edit"></i>
                        Ubah Profil
                    </a>
                </div>
            </form>
        </section>
    </main>
  </div>

  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script>
    // Inisialisasi icon Lucide
    lucide.createIcons();
  </script>
</body>
</html>