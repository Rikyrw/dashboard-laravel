<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../server/supabase.php';
if (!checkAdminSession()) {
    http_response_code(403);
    echo 'Forbidden: admin login required';
    exit;
}

$export_type = $_GET['export'] ?? 'excel'; // Default ke excel

// Aggregate per month for past 12 months
$now = new DateTime();
$start = (new DateTime('-11 months'))->modify('first day of this month')->format('Y-m-01 00:00:00');
$end = $now->format('Y-m-d H:i:s');

// Fetch transaksi_setor grouped by month
$resp = supabase_request('GET', '/rest/v1/transaksi_setor?select=to_char(tanggal_setor,\'YYYY-MM\') as bulan,sum=total_nilai&tanggal_setor=gte.' . urlencode($start) . '&tanggal_setor=lte.' . urlencode($end) . '&group=bulan&order=bulan.desc', null, true);
$rows = ($resp && isset($resp['status']) && $resp['status']>=200 && $resp['status']<300) ? ($resp['body'] ?? []) : [];

$headers = ['Bulan','Total Pendapatan'];
$data = [];
foreach ($rows as $r) {
    $data[] = [$r['bulan'] ?? '', $r['sum'] ?? 0];
}

// EXCEL EXPORT
if ($export_type === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Laporan_Per_Bulan_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $grand_total = 0;
    $highest_month = ['month' => '', 'value' => 0];
    $lowest_month = ['month' => '', 'value' => PHP_FLOAT_MAX];
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: 'Arial', sans-serif; margin: 20px; }
            h1 { color: #7B1FA2; text-align: center; margin-bottom: 5px; }
            .subtitle { text-align: center; color: #666; margin-bottom: 20px; }
            .period-info { background-color: #f3e5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #9C27B0; }
            .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0; }
            .stat-box { text-align: center; padding: 15px; border-radius: 5px; }
            .stat-total { background-color: #e8f5e8; }
            .stat-high { background-color: #fff3e0; }
            .stat-low { background-color: #ffeef0; }
            .stat-value { font-size: 22px; font-weight: bold; }
            .stat-label { font-size: 12px; color: #666; margin-top: 5px; }
            table { width: 80%; margin: 30px auto; border-collapse: collapse; }
            th { background-color: #9C27B0; color: white; font-weight: bold; padding: 12px; border: 1px solid #ddd; text-align: center; }
            td { padding: 10px; border: 1px solid #ddd; text-align: center; }
            .month-row:hover { background-color: #f9f9f9; }
            .total-row { background-color: #f3e5f5; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1>LAPORAN PER BULAN</h1>
        <div class="subtitle">GreenPoint Waste Management System</div>
        
        <div class="period-info">
            <strong>Periode:</strong> 12 Bulan Terakhir<br>
            <strong>Rentang:</strong> <?= date('F Y', strtotime($start)) ?> - <?= date('F Y', strtotime($end)) ?><br>
            <strong>Tanggal Export:</strong> <?= date('d/m/Y H:i:s') ?>
        </div>
        
        <?php
        foreach ($data as $row) {
            $value = floatval($row[1]);
            $grand_total += $value;
            
            if ($value > $highest_month['value']) {
                $highest_month['value'] = $value;
                $highest_month['month'] = $row[0];
            }
            if ($value < $lowest_month['value'] && $value > 0) {
                $lowest_month['value'] = $value;
                $lowest_month['month'] = $row[0];
            }
        }
        ?>
        
        <div class="stats">
            <div class="stat-box stat-total">
                <div class="stat-value">Rp <?= number_format($grand_total, 0, ',', '.') ?></div>
                <div class="stat-label">TOTAL PENDAPATAN</div>
            </div>
            <div class="stat-box stat-high">
                <div class="stat-value"><?= $highest_month['month'] ? date('M Y', strtotime($highest_month['month'] . '-01')) : '-' ?></div>
                <div class="stat-label">BULAN TERTINGGI<br>Rp <?= number_format($highest_month['value'], 0, ',', '.') ?></div>
            </div>
            <div class="stat-box stat-low">
                <div class="stat-value"><?= $lowest_month['month'] ? date('M Y', strtotime($lowest_month['month'] . '-01')) : '-' ?></div>
                <div class="stat-label">BULAN TERENDAH<br>Rp <?= number_format($lowest_month['value'], 0, ',', '.') ?></div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>BULAN</th>
                    <th>TOTAL PENDAPATAN</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): 
                    $month_name = date('F Y', strtotime($row[0] . '-01'));
                ?>
                <tr class="month-row">
                    <td><?= htmlspecialchars($month_name) ?></td>
                    <td>Rp <?= number_format($row[1], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td><strong>GRAND TOTAL</strong></td>
                    <td><strong>Rp <?= number_format($grand_total, 0, ',', '.') ?></strong></td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; text-align: center; color: #666; font-size: 12px;">
            <p>Laporan agregasi bulanan pendapatan dari transaksi setor sampah nasabah</p>
            <p>Generated by GreenPoint System | Halaman 1/1</p>
        </div>
    </body>
    </html>
    <?php
    exit;

// PDF EXPORT
} else {
    header('Content-Type: text/html');
    header('Content-Disposition: attachment;filename="Laporan_Per_Bulan_' . date('Ymd_His') . '.html"');
    
    $grand_total = 0;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Per Bulan - GreenPoint</title>
        <style>
            @page { size: A4; margin: 20mm; }
            body { font-family: 'Arial', sans-serif; margin: 0; padding: 25px; color: #333; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #9C27B0; padding-bottom: 20px; }
            .header h1 { color: #7B1FA2; margin: 0; font-size: 24px; }
            .header .info { color: #666; margin-top: 10px; font-size: 14px; }
            .period-box { background-color: #f3e5f5; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center; }
            .total-box { background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 25px 0; text-align: center; font-size: 18px; font-weight: bold; }
            table { width: 80%; margin: 0 auto; border-collapse: collapse; margin-top: 25px; }
            th { background-color: #9C27B0; color: white; font-weight: bold; padding: 12px; text-align: center; border: 1px solid #ddd; }
            td { padding: 10px; border: 1px solid #ddd; text-align: center; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .total-row { background-color: #f3e5f5; font-weight: bold; }
            .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; padding-top: 15px; border-top: 1px solid #ddd; }
            .chart-note { text-align: center; margin-top: 30px; font-style: italic; color: #666; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>LAPORAN PER BULAN</h1>
            <div class="info">
                GreenPoint Waste Management System
            </div>
        </div>
        
        <div class="period-box">
            <strong>Periode Laporan:</strong> 12 Bulan Terakhir<br>
            <strong>Rentang Waktu:</strong> <?= date('F Y', strtotime($start)) ?> - <?= date('F Y', strtotime($end)) ?><br>
            <strong>Tanggal Cetak:</strong> <?= date('d/m/Y H:i:s') ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>BULAN</th>
                    <th>TOTAL PENDAPATAN</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $highest_value = 0;
                $highest_month = '';
                foreach ($data as $row): 
                    $grand_total += floatval($row[1]);
                    $value = floatval($row[1]);
                    $month_name = date('F Y', strtotime($row[0] . '-01'));
                    
                    if ($value > $highest_value) {
                        $highest_value = $value;
                        $highest_month = $month_name;
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($month_name) ?></td>
                    <td>Rp <?= number_format($row[1], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td><strong>GRAND TOTAL</strong></td>
                    <td><strong>Rp <?= number_format($grand_total, 0, ',', '.') ?></strong></td>
                </tr>
            </tbody>
        </table>
        
        <div class="total-box">
            TOTAL PENDAPATAN 12 BULAN: Rp <?= number_format($grand_total, 0, ',', '.') ?>
        </div>
        
        <div class="chart-note">
            <p>Bulan dengan pendapatan tertinggi: <strong><?= $highest_month ?></strong> (Rp <?= number_format($highest_value, 0, ',', '.') ?>)</p>
            <p>Laporan ini menunjukkan tren pendapatan bulanan dari transaksi setor sampah nasabah.</p>
        </div>
        
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