<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "sampah_bau";

$conn = null;

if (function_exists('mysqli_connect')) {
  $conn = @mysqli_connect($host, $user, $pass, $db);
}

if (!$conn) {
  error_log("Koneksi MySQL gagal atau tidak tersedia. Sistem dapat tetap berjalan dengan Supabase.");
}
?>
