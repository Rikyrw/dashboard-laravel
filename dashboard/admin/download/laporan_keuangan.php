<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../server/supabase.php';
if (!checkAdminSession()) {
    http_response_code(403);
    echo 'Forbidden: admin login required';
    exit;
}

$export_type = $_GET['export'] ?? 'excel'; // Default ke excel
$period = $_GET['periode'] ?? 'month';
$now = new DateTime();
switch ($period) {
    case 'today':
        $start = (new DateTime('today'))->format('Y-m-d 00:00:00');
        $end = $now->format('Y-m-d H:i:s');
        break;
    case 'week':
        $start = (new DateTime('monday this week'))->format('Y-m-d 00:00:00');
        $end = $now->format('Y-m-d H:i:s');
        break;
    case 'year':
        $start = (new DateTime($now->format('Y') . '-01-01'))->format('Y-m-d 00:00:00');
        $end = $now->format('Y-m-d H:i:s');
        break;
    case 'month':
    default:
        $start = (new DateTime($now->format('Y-m-01')))->format('Y-m-d 00:00:00');
        $end = $now->format('Y-m-d H:i:s');
}

// Fetch data
$ts = supabase_request('GET', '/rest/v1/transaksi_setor?select=id_transaksi,total_berat,total_nilai,id_nasabah,tanggal_setor&tanggal_setor=gte.' . urlencode($start) . '&tanggal_setor=lte.' . urlencode($end), null, true);
$ts_rows = ($ts && isset($ts['status']) && $ts['status'] >=200 && $ts['status']<300) ? ($ts['body'] ?? []) : [];

$p = supabase_request('GET', '/rest/v1/penarikan?select=id_penukaran,id_nasabah,nominal,status,tanggal_pengajuan&tanggal_pengajuan=gte.' . urlencode($start) . '&tanggal_pengajuan=lte.' . urlencode($end), null, true);
$p_rows = ($p && isset($p['status']) && $p['status'] >=200 && $p['status']<300) ? ($p['body'] ?? []) : [];

// Prepare data
$data = [];
$headers = ['Jenis', 'ID', 'ID Nasabah', 'Nominal/Total', 'Tanggal', 'Status'];
foreach ($ts_rows as $r) {
    $data[] = ['Setor', $r['id_transaksi'] ?? '', $r['id_nasabah'] ?? '', $r['total_nilai'] ?? 0, $r['tanggal_setor'] ?? '', $r['status'] ?? ''];
}
foreach ($p_rows as $r) {
    $data[] = ['Penarikan', $r['id_penukaran'] ?? '', $r['id_nasabah'] ?? '', $r['nominal'] ?? 0, $r['tanggal_pengajuan'] ?? '', $r['status'] ?? ''];
}

// EXPORT EXCEL
if ($export_type === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Laporan_Keuangan_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $total_nominal = 0;
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Laporan Keuangan</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                            <x:Print>
                                <x:ValidPrinterInfo/>
                            </x:Print>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            body { font-family: 'Arial', sans-serif; margin: 20px; }
            h1 { color: #2E7D32; text-align: center; margin-bottom: 5px; }
            .subtitle { text-align: center; color: #666; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #4CAF50; color: white; font-weight: bold; padding: 12px; border: 1px solid #ddd; text-align: left; }
            td { padding: 10px; border: 1px solid #ddd; }
            .total-row { background-color: #f2f2f2; font-weight: bold; }
            .info-box { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #4CAF50; }
        </style>
    </head>
    <body>
        <h1>LAPORAN KEUANGAN</h1>
        <div class="subtitle">GreenPoint Waste Management System</div>
        
        <div class="info-box">
            <strong>Periode:</strong> <?= ucfirst($period) ?> (<?= date('d/m/Y', strtotime($start)) ?> - <?= date('d/m/Y', strtotime($end)) ?>)<br>
            <strong>Tanggal Export:</strong> <?= date('d/m/Y H:i:s') ?><br>
            <strong>Jumlah Data:</strong> <?= count($data) ?>
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
                    $total_nominal += floatval($row[3]);
                ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                    <td><?= htmlspecialchars($cell) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3"><strong>TOTAL</strong></td>
                    <td><strong>Rp <?= number_format($total_nominal, 0, ',', '.') ?></strong></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; text-align: center; color: #666; font-size: 12px;">
            <p>Generated by GreenPoint System | Halaman 1/1</p>
        </div>
    </body>
    </html>
    <?php
    exit;

// EXPORT PDF
} else {
    header('Content-Type: text/html');
    header('Content-Disposition: attachment;filename="Laporan_Keuangan_' . date('Ymd_His') . '.html"');
    
    $total_nominal = 0;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Keuangan - GreenPoint</title>
        <style>
            @page { size: A4 landscape; margin: 20mm; }
            body { font-family: 'Arial', sans-serif; margin: 0; padding: 20px; color: #333; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4CAF50; padding-bottom: 15px; }
            .header h1 { color: #2E7D32; margin: 0; font-size: 24px; }
            .header .info { color: #666; margin-top: 10px; font-size: 14px; }
            .period-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #4CAF50; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
            th { background-color: #4CAF50; color: white; font-weight: bold; padding: 10px; text-align: left; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .total-row { background-color: #e8f5e8; font-weight: bold; }
            .footer { margin-top: 30px; text-align: center; font-size: 11px; color: #666; padding-top: 10px; border-top: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>LAPORAN KEUANGAN</h1>
            <div class="info">
                GreenPoint Waste Management System
            </div>
        </div>
        
        <div class="period-info">
            <strong>Periode:</strong> <?= ucfirst($period) ?><br>
            <strong>Rentang Waktu:</strong> <?= date('d/m/Y H:i:s', strtotime($start)) ?> - <?= date('d/m/Y H:i:s', strtotime($end)) ?><br>
            <strong>Tanggal Cetak:</strong> <?= date('d/m/Y H:i:s') ?>
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
                    $total_nominal += floatval($row[3]);
                ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                    <td><?= htmlspecialchars($cell) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3"><strong>TOTAL</strong></td>
                    <td><strong>Rp <?= number_format($total_nominal, 0, ',', '.') ?></strong></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
        
        <div class="footer">
            <p>GreenPoint Waste Management System</p>
            <p>Halaman 1/1</p>
        </div>
        
        <script>
            window.onload = function() {
                // Auto print untuk PDF
                window.print();
                // Optional: auto close setelah print
                // setTimeout(function() { window.close(); }, 1000);
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}