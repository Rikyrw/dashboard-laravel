<aside class="sidebar" role="complementary" aria-label="Sidebar navigation">
  <!-- Brand -->
  <div class="brand">
    <img src="assets/img/simple tree.png" alt="GreenPoint Logo" />
    <h1>GreenPoint</h1>
  </div>

  <!-- Navigation -->
  <nav class="nav" aria-label="Main menu">
    <a href="dashboard.php" class="<?= ($activePage == 'dashboard') ? 'active' : '' ?>">
      <i class="i lucide-layout-dashboard"></i><span>Dashboard</span>
    </a>
    <a href="nasabah.php" class="<?= ($activePage == 'nasabah') ? 'active' : '' ?>">
      <i class="i lucide-users"></i><span>Daftar Nasabah</span>
    </a>
    <a href="transaksi.php" class="<?= ($activePage == 'transaksi') ? 'active' : '' ?>">
      <i class="i lucide-repeat"></i><span>Transaksi</span>
    </a>
    <a href="sampah.php" class="<?= ($activePage == 'sampah') ? 'active' : '' ?>">
      <i class="i lucide-trash-2"></i><span>Daftar Sampah</span>
    </a>
    <a href="laporan.php" class="<?= ($activePage == 'laporan') ? 'active' : '' ?>">
      <i class="i lucide-file-chart-column"></i><span>Laporan</span>
    </a>
    
    <?php 
    // Hanya tampilkan menu Pengaturan Admin untuk Super Admin
    if (isset($_SESSION['admin_role']) && strtolower($_SESSION['admin_role']) === 'superadmin'): 
    ?>
    <a href="pengaturan.php" class="<?= ($activePage == 'pengaturan') ? 'active' : '' ?>">
      <i class="i lucide-settings"></i><span>Pengaturan Admin</span>
    </a>
    <?php endif; ?>
  </nav>

  <!-- User Footer -->
  <div class="user" role="contentinfo">
    <div class="avatar-dropdown">
      <div class="avatar"><?= isset($_SESSION['admin_role']) ? strtoupper(substr($_SESSION['admin_role'], 0, 2)) : 'AD' ?></div>
      <div class="user-info">
        <span class="role"><?= isset($_SESSION['admin_role']) ? ucfirst($_SESSION['admin_role']) : 'Admin' ?></span>
        <span class="name"><?= isset($_SESSION['admin_nama']) ? $_SESSION['admin_nama'] : 'Dhimas Ananta' ?></span>
      </div>
      <button class="logout-btn" onclick="logout()" title="Logout">
        <i class="lucide-log-out"></i>
      </button>
    </div>
    
    <!-- Dropdown menu (hidden by default) -->
    <div class="user-dropdown" id="userDropdown">
      <div class="dropdown-item">
        <i class="lucide-user"></i>
        <span><?= isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin@greenpoint.com' ?></span>
      </div>
      <div class="dropdown-item">
        <i class="lucide-shield"></i>
        <span>Role: <?= isset($_SESSION['admin_role']) ? ucfirst($_SESSION['admin_role']) : 'Admin' ?></span>
      </div>
      <div class="dropdown-divider"></div>
      <a href="logout.php" class="dropdown-item logout-item">
        <i class="lucide-log-out"></i>
        <span>Keluar</span>
      </a>
    </div>
  </div>
</aside>

<style>
.user {
  position: relative;
  margin-top: auto;
}

.avatar-dropdown {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
}

.avatar-dropdown:hover {
  background: rgba(255, 255, 255, 0.1);
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
  margin-right: 10px;
  flex-shrink: 0;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-info .role {
  display: block;
  font-size: 11px;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 2px;
}

.user-info .name {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: white;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.logout-btn {
  background: none;
  border: none;
  color: rgba(255, 255, 255, 0.7);
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: color 0.2s;
  flex-shrink: 0;
}

.logout-btn:hover {
  color: white;
  background: rgba(255, 255, 255, 0.1);
}

/* Dropdown menu */
.user-dropdown {
  position: absolute;
  bottom: 100%;
  left: 0;
  right: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  margin-bottom: 10px;
  display: none;
  z-index: 1000;
  overflow: hidden;
}

.user:hover .user-dropdown {
  display: block;
}

.dropdown-item {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  color: #374151;
  text-decoration: none;
  transition: background 0.2s;
  border: none;
  width: 100%;
  text-align: left;
  background: none;
  cursor: pointer;
  font-size: 14px;
}

.dropdown-item:hover {
  background: #f3f4f6;
}

.dropdown-item i {
  margin-right: 10px;
  width: 16px;
  color: #6b7280;
}

.dropdown-divider {
  height: 1px;
  background: #e5e7eb;
  margin: 4px 0;
}

.logout-item {
  color: #dc2626;
}

.logout-item i {
  color: #dc2626;
}

.logout-item:hover {
  background: #fee2e2;
}

/* Responsive */
@media (max-width: 768px) {
  .user-dropdown {
    position: fixed;
    bottom: 80px;
    left: 20px;
    right: 20px;
    width: auto;
  }
}
</style>

<script>
function logout() {
  if (confirm('Apakah Anda yakin ingin keluar?')) {
    window.location.href = 'logout.php';
  }
}

// Toggle dropdown on avatar click
document.querySelector('.avatar-dropdown').addEventListener('click', function(e) {
  e.stopPropagation();
  const dropdown = document.getElementById('userDropdown');
  dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
});

// Close dropdown when clicking outside
document.addEventListener('click', function() {
  document.getElementById('userDropdown').style.display = 'none';
});

// Prevent dropdown from closing when clicking inside
document.getElementById('userDropdown').addEventListener('click', function(e) {
  e.stopPropagation();
});
</script>