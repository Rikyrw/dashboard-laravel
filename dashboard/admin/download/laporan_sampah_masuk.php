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

// Fetch detail_setor joined via transaksi_setor date range
$dresp = supabase_request('GET', '/rest/v1/detail_setor?select=id_detail,id_transaksi,id_jenis,berat_kg,subtotal,transaksi_setor(tanggal_setor,id_nasabah)&transaksi_setor.tanggal_setor=gte.' . urlencode($start) . '&transaksi_setor.tanggal_setor=lte.' . urlencode($end), null, true);
$drows = ($dresp && isset($dresp['status']) && $dresp['status']>=200 && $dresp['status']<300) ? ($dresp['body'] ?? []) : [];

// Resolve jenis names
$jenis_ids = [];
foreach ($drows as $dr) if (!empty($dr['id_jenis'])) $jenis_ids[$dr['id_jenis']] = $dr['id_jenis'];
$jenis_map = [];
if (!empty($jenis_ids)) {
    $in = implode(',', array_map('intval', array_values($jenis_ids)));
    $jresp = supabase_request('GET', '/rest/v1/jenis_sampah?select=id_jenis,nama_jenis&id_jenis=in.(' . $in . ')', null, true);
    $jrows = ($jresp && isset($jresp['status']) && $jresp['status']>=200 && $jresp['status']<300) ? ($jresp['body'] ?? []) : [];
    foreach ($jrows as $j) $jenis_map[$j['id_jenis']] = $j['nama_jenis'];
}

$headers = ['ID Detail','ID Transaksi','ID Nasabah','Jenis','Berat (kg)','Subtotal','Tanggal Setor'];
$data = [];
foreach ($drows as $dr) {
    $trans = $dr['transaksi_setor'] ?? null;
    $tanggal = is_array($trans) ? ($trans['tanggal_setor'] ?? '') : '';
    $id_n = is_array($trans) ? ($trans['id_nasabah'] ?? '') : '';
    $data[] = [
        $dr['id_detail'] ?? '',
        $dr['id_transaksi'] ?? '',
        $id_n,
        ($jenis_map[$dr['id_jenis']] ?? $dr['id_jenis']),
        $dr['berat_kg'] ?? 0,
        $dr['subtotal'] ?? 0,
        $tanggal
    ];
}

// EXCEL EXPORT
if ($export_type === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Laporan_Sampah_Masuk_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $total_berat = 0;
    $total_subtotal = 0;
    $unique_nasabah = [];
    $jenis_counts = [];
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: 'Arial', sans-serif; margin: 20px; }
            h1 { color: #F57C00; text-align: center; margin-bottom: 5px; }
            .subtitle { text-align: center; color: #666; margin-bottom: 20px; }
            .summary { background-color: #fff3e0; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #FF9800; }
            .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 15px 0; }
            .summary-item { text-align: center; }
            .summary-value { font-size: 20px; font-weight: bold; color: #F57C00; }
            .summary-label { font-size: 12px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #FF9800; color: white; font-weight: bold; padding: 12px; border: 1px solid #ddd; text-align: left; }
            td { padding: 10px; border: 1px solid #ddd; }
            .total-row { background-color: #fff3e0; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1>LAPORAN SAMPAH MASUK</h1>
        <div class="subtitle">GreenPoint Waste Management System</div>
        
        <div class="summary">
            <strong>Periode:</strong> <?= ucfirst($period) ?> (<?= date('d/m/Y', strtotime($start)) ?> - <?= date('d/m/Y', strtotime($end)) ?>)<br>
            <strong>Tanggal Export:</strong> <?= date('d/m/Y H:i:s') ?>
        </div>
        
        <?php
        foreach ($data as $row) {
            $total_berat += floatval($row[4]);
            $total_subtotal += floatval($row[5]);
            if (!empty($row[2]) && !in_array($row[2], $unique_nasabah)) {
                $unique_nasabah[] = $row[2];
            }
            $jenis = $row[3];
            if (!isset($jenis_counts[$jenis])) $jenis_counts[$jenis] = 0;
            $jenis_counts[$jenis] += floatval($row[4]);
        }
        ?>
        
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value"><?= count($data) ?></div>
                <div class="summary-label">Total Transaksi</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?= count($unique_nasabah) ?></div>
                <div class="summary-label">Total Nasabah</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?= number_format($total_berat, 2, ',', '.') ?> kg</div>
                <div class="summary-label">Total Berat</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">Rp <?= number_format($total_subtotal, 0, ',', '.') ?></div>
                <div class="summary-label">Total Nilai</div>
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
                <?php foreach ($data as $row): ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                    <td><?= htmlspecialchars($cell) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4"><strong>TOTAL</strong></td>
                    <td><strong><?= number_format($total_berat, 2, ',', '.') ?> kg</strong></td>
                    <td><strong>Rp <?= number_format($total_subtotal, 0, ',', '.') ?></strong></td>
                    <td></td>
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

// PDF EXPORT
} else {
    header('Content-Type: text/html');
    header('Content-Disposition: attachment;filename="Laporan_Sampah_Masuk_' . date('Ymd_His') . '.html"');
    
    $total_berat = 0;
    $total_subtotal = 0;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Sampah Masuk - GreenPoint</title>
        <style>
            @page { size: A4 landscape; margin: 15mm; }
            body { font-family: 'Arial', sans-serif; margin: 0; padding: 20px; color: #333; }
            .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #FF9800; padding-bottom: 15px; }
            .header h1 { color: #F57C00; margin: 0; font-size: 22px; }
            .header .info { color: #666; margin-top: 8px; font-size: 13px; }
            .period-info { background-color: #fff3e0; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #FF9800; }
            .stats { display: flex; justify-content: space-between; background-color: #fff8e1; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .stat-item { text-align: center; }
            .stat-number { font-size: 18px; font-weight: bold; color: #F57C00; }
            .stat-text { font-size: 11px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
            th { background-color: #FF9800; color: white; font-weight: bold; padding: 9px; text-align: left; border: 1px solid #ddd; }
            td { padding: 7px; border: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #fff8e1; }
            .total-row { background-color: #fff3e0; font-weight: bold; }
            .footer { margin-top: 25px; text-align: center; font-size: 10px; color: #666; padding-top: 10px; border-top: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>LAPORAN SAMPAH MASUK</h1>
            <div class="info">
                GreenPoint Waste Management System
            </div>
        </div>
        
        <div class="period-info">
            <strong>Periode:</strong> <?= ucfirst($period) ?><br>
            <strong>Rentang Waktu:</strong> <?= date('d/m/Y', strtotime($start)) ?> - <?= date('d/m/Y', strtotime($end)) ?><br>
            <strong>Tanggal Cetak:</strong> <?= date('d/m/Y H:i:s') ?>
        </div>
        
        <?php
        $unique_nasabah = [];
        foreach ($data as $row) {
            $total_berat += floatval($row[4]);
            $total_subtotal += floatval($row[5]);
            if (!empty($row[2]) && !in_array($row[2], $unique_nasabah)) {
                $unique_nasabah[] = $row[2];
            }
        }
        ?>
        
        <div class="stats">
            <div class="stat-item">
                <div class="stat-number"><?= count($data) ?></div>
                <div class="stat-text">Total Data</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= count($unique_nasabah) ?></div>
                <div class="stat-text">Jumlah Nasabah</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($total_berat, 2, ',', '.') ?> kg</div>
                <div class="stat-text">Total Berat</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">Rp <?= number_format($total_subtotal, 0, ',', '.') ?></div>
                <div class="stat-text">Total Nilai</div>
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
                <?php foreach ($data as $row): ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                    <td><?= htmlspecialchars($cell) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4"><strong>TOTAL</strong></td>
                    <td><strong><?= number_format($total_berat, 2, ',', '.') ?> kg</strong></td>
                    <td><strong>Rp <?= number_format($total_subtotal, 0, ',', '.') ?></strong></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        
        <div class="footer">
            GreenPoint Waste Management System | Halaman 1/1
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}