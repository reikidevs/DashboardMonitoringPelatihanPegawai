<?php
require_once '../config/config.php';
$conn = getConnection();

$filterYear = $_GET['year'] ?? date('Y');
$type = $_GET['type'] ?? 'rekap';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="rekap_pelatihan_' . $filterYear . '_' . date('Ymd_His') . '.xls"');
header('Cache-Control: max-age=0');

// Get data
if ($type == 'detail') {
    // Export detail monitoring
    $data = $conn->query("
        SELECT pg.nama as pegawai, pg.nip, pg.jabatan, p.nama as pelatihan, 
               k.nama as kategori, l.nama as lingkup, m.tahun, m.pelaksanaan, 
               m.jumlah_jp, m.no_sertifikat
        FROM monitoring_pelatihan m
        LEFT JOIN pegawai pg ON m.pegawai_id = pg.id
        LEFT JOIN pelatihan p ON m.pelatihan_id = p.id
        LEFT JOIN kategori_pelatihan k ON p.kategori_id = k.id
        LEFT JOIN lingkup_pelatihan l ON p.lingkup_id = l.id
        " . ($filterYear ? "WHERE m.tahun = $filterYear" : "") . "
        ORDER BY pg.nama, m.tahun DESC
    ");
    
    echo "<table border='1'>";
    echo "<tr style='background:#1a365d; color:white; font-weight:bold;'>";
    echo "<th>No</th><th>Nama Pegawai</th><th>NIP</th><th>Jabatan</th><th>Pelatihan</th>";
    echo "<th>Kategori</th><th>Lingkup</th><th>Tahun</th><th>Pelaksanaan</th><th>JP</th><th>No Sertifikat</th>";
    echo "</tr>";
    
    $no = 1;
    while($row = $data->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . $row['pegawai'] . "</td>";
        echo "<td>" . $row['nip'] . "</td>";
        echo "<td>" . $row['jabatan'] . "</td>";
        echo "<td>" . $row['pelatihan'] . "</td>";
        echo "<td>" . $row['kategori'] . "</td>";
        echo "<td>" . $row['lingkup'] . "</td>";
        echo "<td>" . $row['tahun'] . "</td>";
        echo "<td>" . ($row['pelaksanaan'] ? date('d/m/Y', strtotime($row['pelaksanaan'])) : '-') . "</td>";
        echo "<td>" . $row['jumlah_jp'] . "</td>";
        echo "<td>" . ($row['no_sertifikat'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    // Export rekap per pegawai
    $data = $conn->query("
        SELECT 
            pg.id, pg.nama, pg.nip, pg.jabatan,
            (SELECT COUNT(*) FROM kewajiban_pelatihan kp WHERE kp.pegawai_id = pg.id) as total_kewajiban,
            (SELECT COUNT(*) FROM kewajiban_pelatihan kp 
                INNER JOIN monitoring_pelatihan m ON kp.pegawai_id = m.pegawai_id AND kp.pelatihan_id = m.pelatihan_id
                WHERE kp.pegawai_id = pg.id " . ($filterYear ? "AND m.tahun = $filterYear" : "") . "
            ) as kewajiban_selesai,
            (SELECT COUNT(DISTINCT m.pelatihan_id) FROM monitoring_pelatihan m WHERE m.pegawai_id = pg.id " . ($filterYear ? "AND m.tahun = $filterYear" : "") . ") as total_pelatihan,
            (SELECT COALESCE(SUM(m.jumlah_jp), 0) FROM monitoring_pelatihan m WHERE m.pegawai_id = pg.id " . ($filterYear ? "AND m.tahun = $filterYear" : "") . ") as total_jp,
            (SELECT COUNT(*) FROM monitoring_pelatihan m 
                INNER JOIN pelatihan p ON m.pelatihan_id = p.id 
                INNER JOIN kategori_pelatihan k ON p.kategori_id = k.id 
                WHERE m.pegawai_id = pg.id AND k.nama = 'Mutlak' " . ($filterYear ? "AND m.tahun = $filterYear" : "") . ") as mutlak,
            (SELECT COUNT(*) FROM monitoring_pelatihan m 
                INNER JOIN pelatihan p ON m.pelatihan_id = p.id 
                INNER JOIN kategori_pelatihan k ON p.kategori_id = k.id 
                WHERE m.pegawai_id = pg.id AND k.nama = 'Penting' " . ($filterYear ? "AND m.tahun = $filterYear" : "") . ") as penting,
            (SELECT COUNT(*) FROM monitoring_pelatihan m 
                INNER JOIN pelatihan p ON m.pelatihan_id = p.id 
                INNER JOIN kategori_pelatihan k ON p.kategori_id = k.id 
                WHERE m.pegawai_id = pg.id AND k.nama = 'Perlu' " . ($filterYear ? "AND m.tahun = $filterYear" : "") . ") as perlu
        FROM pegawai pg
        ORDER BY pg.nama
    ");
    
    echo "<table border='1'>";
    echo "<tr><td colspan='11' style='font-size:16px; font-weight:bold; text-align:center; background:#1a365d; color:white;'>REKAP PELATIHAN PEGAWAI TAHUN " . $filterYear . "</td></tr>";
    echo "<tr><td colspan='11' style='text-align:center;'>Exported: " . date('d/m/Y H:i:s') . "</td></tr>";
    echo "<tr></tr>";
    echo "<tr style='background:#1a365d; color:white; font-weight:bold;'>";
    echo "<th>No</th><th>Nama Pegawai</th><th>NIP</th><th>Jabatan</th>";
    echo "<th>Kewajiban Selesai</th><th>Total Pelatihan</th><th>Total JP</th>";
    echo "<th>Mutlak</th><th>Penting</th><th>Perlu</th><th>Progres (%)</th>";
    echo "</tr>";
    
    $no = 1;
    $totalKewajiban = 0;
    $totalSelesai = 0;
    $totalPelatihan = 0;
    $totalJP = 0;
    
    while($row = $data->fetch_assoc()) {
        $persen = $row['total_kewajiban'] > 0 ? round(($row['kewajiban_selesai'] / $row['total_kewajiban']) * 100) : ($row['total_pelatihan'] > 0 ? 100 : 0);
        
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . $row['nama'] . "</td>";
        echo "<td>" . $row['nip'] . "</td>";
        echo "<td>" . $row['jabatan'] . "</td>";
        echo "<td style='text-align:center;'>" . $row['kewajiban_selesai'] . "/" . $row['total_kewajiban'] . "</td>";
        echo "<td style='text-align:center;'>" . $row['total_pelatihan'] . "</td>";
        echo "<td style='text-align:center;'>" . $row['total_jp'] . "</td>";
        echo "<td style='text-align:center;'>" . $row['mutlak'] . "</td>";
        echo "<td style='text-align:center;'>" . $row['penting'] . "</td>";
        echo "<td style='text-align:center;'>" . $row['perlu'] . "</td>";
        echo "<td style='text-align:center; font-weight:bold; color:" . ($persen >= 80 ? 'green' : ($persen >= 50 ? 'orange' : 'red')) . ";'>" . $persen . "%</td>";
        echo "</tr>";
        
        $totalKewajiban += $row['total_kewajiban'];
        $totalSelesai += $row['kewajiban_selesai'];
        $totalPelatihan += $row['total_pelatihan'];
        $totalJP += $row['total_jp'];
    }
    
    echo "<tr style='background:#e2e8f0; font-weight:bold;'>";
    echo "<td colspan='4' style='text-align:right;'>TOTAL:</td>";
    echo "<td style='text-align:center;'>" . $totalSelesai . "/" . $totalKewajiban . "</td>";
    echo "<td style='text-align:center;'>" . $totalPelatihan . "</td>";
    echo "<td style='text-align:center;'>" . $totalJP . "</td>";
    echo "<td colspan='4'></td>";
    echo "</tr>";
    echo "</table>";
}

$conn->close();
?>
