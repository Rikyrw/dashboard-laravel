<?php
session_start();
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../server/supabase.php';

// Cek apakah user adalah admin yang valid
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$id_transaksi = $_GET['id'] ?? null;
if (!$id_transaksi) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// Ambil data transaksi
$transaksi_data = supabase_request('GET', "/rest/v1/transaksi_setor?id_transaksi=eq.$id_transaksi&select=*", null, true);
if (!$transaksi_data || !isset($transaksi_data['body'][0])) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

$transaksi = $transaksi_data['body'][0];

// Ambil data nasabah
$id_nasabah = $transaksi['id_nasabah'];
$nasabah_data = supabase_request('GET', "/rest/v1/nasabah?id_nasabah=eq.$id_nasabah&select=nama_nasabah", null, true);
$nama_nasabah = $nasabah_data && isset($nasabah_data['body'][0]) ? $nasabah_data['body'][0]['nama_nasabah'] : "ID: $id_nasabah";

// Ambil detail transaksi
$detail_data = supabase_request('GET', "/rest/v1/detail_setor?id_transaksi=eq.$id_transaksi&select=*", null, true);
$items = [];
if ($detail_data && isset($detail_data['body'])) {
    // Ambil nama jenis untuk setiap item
    foreach ($detail_data['body'] as $detail) {
        $id_jenis = $detail['id_jenis'];
        $jenis_data = supabase_request('GET', "/rest/v1/jenis_sampah?id_jenis=eq.$id_jenis&select=nama_jenis", null, true);
        $nama_jenis = $jenis_data && isset($jenis_data['body'][0]) ? $jenis_data['body'][0]['nama_jenis'] : "Jenis #$id_jenis";
        
        $items[] = [
            'nama_jenis' => $nama_jenis,
            'berat_kg' => floatval($detail['berat_kg']),
            'harga_per_kg' => floatval($detail['harga_per_kg']),
            'subtotal' => floatval($detail['subtotal'])
        ];
    }
}

// Siapkan response
$response = [
    'id_transaksi' => $id_transaksi,
    'nama_nasabah' => $nama_nasabah,
    'total_berat' => floatval($transaksi['total_berat']),
    'total_nilai' => floatval($transaksi['total_nilai']),
    'tanggal' => date('d M Y H:i', strtotime($transaksi['tanggal_setor'])),
    'status' => $transaksi['status'],
    'items' => $items
];

header('Content-Type: application/json');
echo json_encode($response);