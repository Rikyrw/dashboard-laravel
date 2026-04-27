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
  error_log("Koneksi MySQL dashboard admin gagal atau tidak tersedia. Fitur Supabase tetap dapat digunakan.");
}
?>
