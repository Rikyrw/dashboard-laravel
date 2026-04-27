<div class="sidebar">
    <div class="brand">
        <img src="assets/img/simple tree.png" style="width:40px;">
        <h1>GreenPoint</h1>
    </div>

    <div class="nav">
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':''; ?>">
            <i class="icon" data-lucide="layout-dashboard"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="transaksi.php" class="<?php echo basename($_SERVER['PHP_SELF'])=='transaksi.php'?'active':''; ?>">
            <i class="icon" data-lucide="receipt"></i>
            <span>Transaksi PPOB</span>
        </a>

        <a href="riwayatstor.php" class="<?php echo (isset($activePage) && $activePage == 'riwayatstor') || basename($_SERVER['PHP_SELF'])=='riwayatstor.php' ? 'active' : ''; ?>">
            <i class="icon" data-lucide="history"></i>
            <span>Riwayat Setor</span>
        </a>

        <a href="profil.php" class="<?php echo basename($_SERVER['PHP_SELF'])=='profil.php'?'active':''; ?>">
            <i class="icon" data-lucide="user"></i>
            <span>Profil Saya</span>
        </a>
    </div>

    <!-- Button Logout dengan konfirmasi -->
    <a href="javascript:void(0);" class="logout-btn" onclick="confirmLogout()">
        <i class="icon" data-lucide="log-out"></i>
        <span>Logout</span>
    </a>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
    lucide.createIcons();
    
    function confirmLogout() {
        if (confirm('Apakah Anda yakin ingin logout?')) {
            window.location.href = 'logout.php';
        }
    }
</script>