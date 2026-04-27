<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../server/supabase.php';
if (!checkAdminSession()) {
    http_response_code(403);
    echo 'Forbidden: admin login required';
    exit;
}

$export_type = $_GET['export'] ?? 'csv';

$resp = supabase_request('GET', '/rest/v1/nasabah?select=id_nasabah,nama_nasabah,email,no_hp,saldo,tanggal_daftar,status_akun', null, true);
$rows = ($resp && isset($resp['status']) && $resp['status']>=200 && $resp['status']<300) ? ($resp['body'] ?? []) : [];

$headers = ['ID Nasabah','Nama','Email','No HP','Saldo','Tanggal Daftar','Status'];
$data = [];
foreach ($rows as $r) {
    $data[] = [
        $r['id_nasabah'] ?? '',
        $r['nama_nasabah'] ?? '',
        $r['email'] ?? '',
        $r['no_hp'] ?? '',
        $r['saldo'] ?? 0,
        $r['tanggal_daftar'] ?? '',
        $r['status_akun'] ?? ''
    ];
}

// EXCEL EXPORT
if ($export_type === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="laporan_data_nasabah_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $total_saldo = 0;
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; font-family: Arial; }
            th { background-color: #2196F3; color: white; font-weight: bold; padding: 10px; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            .title { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 20px; color: #333; }
            .subtitle { text-align: center; color: #666; margin-bottom: 15px; }
            .total-row { background-color: #f2f2f2; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="title">LAPORAN DATA NASABAH</div>
        <div class="subtitle">
            Total Nasabah: <?= count($data) ?><br>
            Tanggal Export: <?= date('d/m/Y H:i:s') ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                    <th><?= htmlspecialchars($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): 
                    $total_saldo += floatval($row[4]);
                ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                    <td><?= htmlspecialchars($cell) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4"><strong>TOTAL SALDO</strong></td>
                    <td><strong>Rp <?= number_format($total_saldo, 0, ',', '.') ?></strong></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;

// PDF EXPORT
} elseif ($export_type === 'pdf') {
    header('Content-Type: text/html');
    header('Content-Disposition: attachment;filename="laporan_data_nasabah_' . date('Ymd_His') . '.html"');
    
    $total_saldo = 0;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Data Nasabah - GreenPoint</title>
        <style>
            @page { size: A4 landscape; margin: 15mm; }
            body { font-family: 'Arial', sans-serif; margin: 0; padding: 20px; color: #333; }
            .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #2196F3; padding-bottom: 15px; }
            .header h1 { color: #1976D2; margin: 0; font-size: 22px; }
            .header .info { color: #666; margin-top: 8px; font-size: 13px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
            th { background-color: #2196F3; color: white; font-weight: bold; padding: 9px; text-align: left; border: 1px solid #ddd; }
            td { padding: 7px; border: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f8f9fa; }
            .total-row { background-color: #e3f2fd; font-weight: bold; }
            .footer { margin-top: 25px; text-align: center; font-size: 10px; color: #666; padding-top: 10px; border-top: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>LAPORAN DATA NASABAH</h1>
            <div class="info">
                Total Nasabah: <?= count($data) ?> | Tanggal Cetak: <?= date('d/m/Y H:i:s') ?>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                    <th><?= htmlspecialchars($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): 
                    $total_saldo += floatval($row[4]);
                ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                    <td><?= htmlspecialchars($cell) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4"><strong>TOTAL SALDO</strong></td>
                    <td><strong>Rp <?= number_format($total_saldo, 0, ',', '.') ?></strong></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
        
        <div class="footer">
            GreenPoint Waste Management System | Halaman 1/1
        </div>
        
        <script>window.onload = function() { /* window.print(); */ };</script>
    </body>
    </html>
    <?php
    exit;

// CSV EXPORT
} else {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laporan_data_nasabah_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, $headers);
    foreach ($data as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}