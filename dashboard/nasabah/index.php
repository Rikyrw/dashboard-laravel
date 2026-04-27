<?php
session_start();
// Redirect ke dashboard jika sudah login, ke login jika belum
if (isset($_SESSION['id_nasabah'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login/login.php");
}
exit;
?>