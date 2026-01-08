<?php
/**
 * Auto Sync Script
 * File ini akan dipanggil otomatis untuk sinkronisasi data dari Google Sheets
 */

require_once '../config/config.php';
$conn = getConnection();

// Get last sync time
$lastSyncResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'last_sync'");
$lastSync = $lastSyncResult && $lastSyncResult->num_rows > 0 ? $lastSyncResult->fetch_assoc()['setting_value'] : null;

// Get sync interval (default 5 menit)
$intervalResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'sync_interval_minutes'");
$syncInterval = $intervalResult && $intervalResult->num_rows > 0 ? (int)$intervalResult->fetch_assoc()['setting_value'] : 5;

// Check if need to sync
$needSync = false;
if (!$lastSync) {
    $needSync = true; // Belum pernah sync
} else {
    $lastSyncTime = strtotime($lastSync);
    $now = time();
    $minutesSinceLastSync = ($now - $lastSyncTime) / 60;
    
    if ($minutesSinceLastSync >= $syncInterval) {
        $needSync = true;
    }
}

$response = [
    'success' => false,
    'message' => '',
    'imported' => 0,
    'skipped' => 0,
    'needSync' => $needSync,
    'lastSync' => $lastSync,
    'minutesSinceLastSync' => isset($minutesSinceLastSync) ? round($minutesSinceLastSync, 1) : null
];

if (!$needSync) {
    $response['message'] = 'Sync tidak diperlukan. Terakhir sync: ' . date('d/m/Y H:i', strtotime($lastSync));
    echo json_encode($response);
    exit;
}

// Get spreadsheet settings
$settingsResult = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('gsheet_id', 'gsheet_name')");
$settings = [];
while($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$spreadsheetId = $settings['gsheet_id'] ?? '1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk';
$sheetName = $settings['gsheet_name'] ?? 'Form Responses 1';

if (empty($spreadsheetId)) {
    $response['message'] = 'Spreadsheet ID belum diatur!';
    echo json_encode($response);
    exit;
}

// Fetch data from Google Sheets
$url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0'
    ]
]);

$csvData = @file_get_contents($url, false, $context);

if ($csvData === false) {
    $response['message'] = 'Gagal mengambil data dari Google Sheets';
    echo json_encode($response);
    exit;
}

// Parse CSV
$lines = array_map('str_getcsv', explode("\n", $csvData));
$header = array_shift($lines); // Remove header

$imported = 0;
$skipped = 0;

foreach ($lines as $data) {
    if (count($data) < 3 || empty(trim($data[1] ?? ''))) continue;
    
    $timestamp = trim($data[0] ?? '');
    $nama = trim($data[1] ?? '');
    $pelatihan_nama = trim($data[2] ?? '');
    $tanggal_pelatihan = trim($data[3] ?? '');
    $keterangan = trim($data[4] ?? '');
    $link_sertifikat = trim($data[5] ?? '');
    
    // Extract tahun
    $tahun = date('Y');
    if (!empty($tanggal_pelatihan)) {
        $date = date_create_from_format('d/m/Y', $tanggal_pelatihan) 
            ?: date_create_from_format('m/d/Y', $tanggal_pelatihan)
            ?: date_create_from_format('Y-m-d', $tanggal_pelatihan)
            ?: date_create($tanggal_pelatihan);
        if ($date) $tahun = (int)$date->format('Y');
    }
    
    if (empty($nama) || empty($pelatihan_nama)) continue;
    
    // Check if already synced
    $checkHash = md5($timestamp . $nama . $pelatihan_nama);
    $existing = $conn->query("SELECT id FROM monitoring_pelatihan WHERE sync_hash = '$checkHash'")->fetch_assoc();
    if ($existing) {
        $skipped++;
        continue;
    }
    
    // Find or create pegawai
    $namaSafe = $conn->real_escape_string($nama);
    $pegawai = $conn->query("SELECT id FROM pegawai WHERE nama = '$namaSafe' LIMIT 1")->fetch_assoc();
    if (!$pegawai) {
        $autoNip = 'AUTO-' . time() . '-' . rand(100, 999);
        $stmt = $conn->prepare("INSERT INTO pegawai (nama, nip) VALUES (?, ?)");
        $stmt->bind_param("ss", $nama, $autoNip);
        $stmt->execute();
        $pegawai_id = $conn->insert_id;
    } else {
        $pegawai_id = $pegawai['id'];
    }
    
    // Find or create pelatihan
    $pelSafe = $conn->real_escape_string($pelatihan_nama);
    $pel = $conn->query("SELECT id FROM pelatihan WHERE nama = '$pelSafe' LIMIT 1")->fetch_assoc();
    if (!$pel) {
        $stmt = $conn->prepare("INSERT INTO pelatihan (nama) VALUES (?)");
        $stmt->bind_param("s", $pelatihan_nama);
        $stmt->execute();
        $pelatihan_id = $conn->insert_id;
    } else {
        $pelatihan_id = $pel['id'];
    }
    
    // Parse tanggal pelatihan
    $pelaksanaan = null;
    if (!empty($tanggal_pelatihan)) {
        $date = date_create_from_format('d/m/Y', $tanggal_pelatihan) 
            ?: date_create_from_format('m/d/Y', $tanggal_pelatihan)
            ?: date_create_from_format('Y-m-d', $tanggal_pelatihan)
            ?: date_create($tanggal_pelatihan);
        if ($date) $pelaksanaan = $date->format('Y-m-d');
    }
    
    // Gabungkan keterangan dengan link sertifikat
    $catatan = $keterangan;
    if (!empty($link_sertifikat)) {
        $catatan = $keterangan . ($keterangan ? ' | ' : '') . 'Sertifikat: ' . $link_sertifikat;
    }
    
    // Insert monitoring with sync_hash
    $stmt = $conn->prepare("INSERT INTO monitoring_pelatihan (pegawai_id, pelatihan_id, tahun, pelaksanaan, no_sertifikat, jumlah_jp, sync_hash) VALUES (?, ?, ?, ?, ?, 0, ?)");
    $stmt->bind_param("iiisss", $pegawai_id, $pelatihan_id, $tahun, $pelaksanaan, $catatan, $checkHash);
    
    if ($stmt->execute()) {
        $imported++;
    }
}

// Update last sync time
$now = date('Y-m-d H:i:s');
$key = 'last_sync';
$stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
$stmt->bind_param("sss", $key, $now, $now);
$stmt->execute();

$response['success'] = true;
$response['imported'] = $imported;
$response['skipped'] = $skipped;
$response['message'] = "Auto-sync selesai! $imported data baru, $skipped data sudah ada.";
$response['lastSync'] = $now;

echo json_encode($response);
$conn->close();
?>
