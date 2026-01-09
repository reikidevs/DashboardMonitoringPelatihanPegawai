<?php
/**
 * Test Google Sheets Connection
 * File ini untuk testing koneksi ke Google Sheets
 */

$spreadsheetId = '1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk';
$sheetName = 'Form Responses 1';

echo "<h2>Test Koneksi Google Sheets</h2>";
echo "<p><strong>Spreadsheet ID:</strong> $spreadsheetId</p>";
echo "<p><strong>Sheet Name:</strong> $sheetName</p>";
echo "<hr>";

// Test URL
$url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
echo "<p><strong>URL:</strong> <a href='$url' target='_blank'>$url</a></p>";
echo "<hr>";

// Fetch data
echo "<h3>Mengambil Data...</h3>";
$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'user_agent' => 'Mozilla/5.0'
    ]
]);

$csvData = @file_get_contents($url, false, $context);

if ($csvData === false) {
    echo "<p style='color:red;'><strong>❌ GAGAL!</strong> Tidak bisa mengambil data dari Google Sheets.</p>";
    echo "<p>Pastikan:</p>";
    echo "<ol>";
    echo "<li>Spreadsheet sudah di-publish ke web (File → Share → Publish to web)</li>";
    echo "<li>Koneksi internet aktif</li>";
    echo "<li>Spreadsheet ID benar</li>";
    echo "</ol>";
} else {
    echo "<p style='color:green;'><strong>✅ BERHASIL!</strong> Data berhasil diambil dari Google Sheets.</p>";
    
    // Parse CSV
    $lines = array_map('str_getcsv', explode("\n", $csvData));
    $totalRows = count($lines);
    
    echo "<p><strong>Total baris:</strong> $totalRows (termasuk header)</p>";
    echo "<hr>";
    
    // Show preview
    echo "<h3>Preview Data (5 baris pertama):</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; font-size:12px;'>";
    
    $preview = array_slice($lines, 0, 6); // Header + 5 rows
    foreach($preview as $i => $row) {
        echo "<tr" . ($i == 0 ? " style='background:#f0f0f0; font-weight:bold;'" : "") . ">";
        foreach($row as $cell) {
            echo "<td>" . htmlspecialchars($cell) . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Show column mapping
    if (count($lines) > 0) {
        echo "<hr>";
        echo "<h3>Mapping Kolom:</h3>";
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; font-size:12px;'>";
        echo "<tr style='background:#f0f0f0; font-weight:bold;'>";
        echo "<td>Index</td><td>Nama Kolom</td><td>Mapping Database</td>";
        echo "</tr>";
        
        $header = $lines[0];
        $mapping = [
            'Timestamp' => 'Auto-generated (untuk sync_hash)',
            'Nama Pegawai' => 'pegawai.nama',
            'Pelatihan yang sudah diikuti' => 'pelatihan.nama',
            'Tanggal Pelatihan' => 'monitoring_pelatihan.pelaksanaan',
            'Keterangan' => 'monitoring_pelatihan.no_sertifikat',
            'Upload Sertifikat' => 'monitoring_pelatihan.no_sertifikat (digabung dengan keterangan)'
        ];
        
        foreach($header as $i => $col) {
            echo "<tr>";
            echo "<td>$i</td>";
            echo "<td><strong>" . htmlspecialchars($col) . "</strong></td>";
            echo "<td>" . ($mapping[$col] ?? '<em>Tidak digunakan</em>') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Test data parsing
    if (count($lines) > 1) {
        echo "<hr>";
        echo "<h3>Test Parsing Data (Baris ke-2):</h3>";
        $testRow = $lines[1];
        
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; font-size:12px;'>";
        echo "<tr style='background:#f0f0f0; font-weight:bold;'><td>Field</td><td>Value</td></tr>";
        
        echo "<tr><td>Timestamp</td><td>" . htmlspecialchars($testRow[0] ?? '') . "</td></tr>";
        echo "<tr><td>Nama Pegawai</td><td>" . htmlspecialchars($testRow[1] ?? '') . "</td></tr>";
        echo "<tr><td>Pelatihan yang sudah diikuti</td><td>" . htmlspecialchars($testRow[2] ?? '') . "</td></tr>";
        echo "<tr><td>Tanggal Pelatihan</td><td>" . htmlspecialchars($testRow[3] ?? '') . "</td></tr>";
        echo "<tr><td>Keterangan</td><td>" . htmlspecialchars($testRow[4] ?? '') . "</td></tr>";
        echo "<tr><td>Upload Sertifikat</td><td>" . htmlspecialchars($testRow[5] ?? '') . "</td></tr>";
        
        echo "</table>";
        
        // Test date parsing
        $tanggal = trim($testRow[3] ?? '');
        if (!empty($tanggal)) {
            echo "<hr>";
            echo "<h3>Test Parsing Tanggal:</h3>";
            echo "<p><strong>Input:</strong> $tanggal</p>";
            
            $date = date_create_from_format('d/m/Y', $tanggal) 
                ?: date_create_from_format('m/d/Y', $tanggal)
                ?: date_create_from_format('Y-m-d', $tanggal)
                ?: date_create($tanggal);
            
            if ($date) {
                echo "<p style='color:green;'><strong>✅ Berhasil di-parse!</strong></p>";
                echo "<p><strong>Format database:</strong> " . $date->format('Y-m-d') . "</p>";
                echo "<p><strong>Tahun:</strong> " . $date->format('Y') . "</p>";
            } else {
                echo "<p style='color:red;'><strong>❌ Gagal parse tanggal!</strong></p>";
            }
        }
        
        // Test hash
        $timestamp = trim($testRow[0] ?? '');
        $nama = trim($testRow[1] ?? '');
        $pelatihan = trim($testRow[2] ?? '');
        $hash = md5($timestamp . $nama . $pelatihan);
        
        echo "<hr>";
        echo "<h3>Test Sync Hash:</h3>";
        echo "<p><strong>Hash:</strong> $hash</p>";
        echo "<p><em>Hash ini digunakan untuk deteksi duplikat</em></p>";
    }
}

echo "<hr>";
echo "<p><a href='../sync_gsheet.php'>← Kembali ke Halaman Sync</a></p>";
?>
