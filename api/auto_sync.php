<?php
/**
 * Auto Sync Script
 * Sinkronisasi data dari Google Sheets ke sistem
 * - Jika pelatihan cocok dengan jadwal → update status peserta & jadwal
 * - Otomatis sync ke monitoring untuk pelatihan yang selesai
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
    $needSync = true;
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
    'jadwal_updated' => 0,
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

$spreadsheetId = $settings['gsheet_id'] ?? '';
$sheetName = $settings['gsheet_name'] ?? 'Form Responses 1';

if (empty($spreadsheetId)) {
    $response['message'] = 'Spreadsheet ID belum diatur!';
    echo json_encode($response);
    exit;
}

// Fetch data from Google Sheets
$url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);

$context = stream_context_create([
    'http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0']
]);

$csvData = @file_get_contents($url, false, $context);

if ($csvData === false) {
    $response['message'] = 'Gagal mengambil data dari Google Sheets';
    echo json_encode($response);
    exit;
}

// Parse CSV
$lines = array_map('str_getcsv', explode("\n", $csvData));
$header = array_shift($lines);

$imported = 0;
$skipped = 0;
$jadwalUpdated = 0;

// Fungsi untuk sync peserta ke monitoring
function syncPesertaToMonitoring($conn, $jadwal_id) {
    $jadwal = $conn->query("SELECT j.*, p.jumlah_jp FROM jadwal_pelatihan j 
        LEFT JOIN pelatihan p ON j.pelatihan_id = p.id WHERE j.id = $jadwal_id")->fetch_assoc();
    
    if (!$jadwal || $jadwal['status'] !== 'Completed') return 0;
    
    $peserta = $conn->query("SELECT * FROM jadwal_peserta WHERE jadwal_id = $jadwal_id AND status = 'Hadir'");
    $synced = 0;
    
    while ($p = $peserta->fetch_assoc()) {
        $exists = $conn->query("SELECT id FROM monitoring_pelatihan 
            WHERE jadwal_id = $jadwal_id AND pegawai_id = {$p['pegawai_id']}")->num_rows;
        
        if (!$exists) {
            $tahun = $jadwal['tanggal_selesai'] ? date('Y', strtotime($jadwal['tanggal_selesai'])) : date('Y');
            $tanggal_mulai = $jadwal['tanggal_mulai'];
            $tanggal_selesai = $jadwal['tanggal_selesai'];
            $jp = $jadwal['jumlah_jp'] ?? 0;
            
            $stmt = $conn->prepare("INSERT INTO monitoring_pelatihan 
                (pegawai_id, pelatihan_id, jadwal_id, tahun, tanggal_mulai, tanggal_selesai, jumlah_jp) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiissi", $p['pegawai_id'], $jadwal['pelatihan_id'], $jadwal_id, $tahun, $tanggal_mulai, $tanggal_selesai, $jp);
            if ($stmt->execute()) $synced++;
        }
    }
    return $synced;
}

// Fungsi untuk cek dan update status jadwal
function checkAndCompleteJadwal($conn, $jadwal_id) {
    $jadwal = $conn->query("SELECT * FROM jadwal_pelatihan WHERE id = $jadwal_id")->fetch_assoc();
    if (!$jadwal || $jadwal['status'] === 'Completed' || $jadwal['status'] === 'Cancelled') return false;
    
    // Cek apakah tanggal selesai sudah lewat
    $tanggalLewat = $jadwal['tanggal_selesai'] && strtotime($jadwal['tanggal_selesai']) < strtotime('today');
    
    // Cek apakah ada peserta yang hadir
    $hadirCount = $conn->query("SELECT COUNT(*) as c FROM jadwal_peserta WHERE jadwal_id = $jadwal_id AND status = 'Hadir'")->fetch_assoc()['c'];
    
    // Auto complete jika tanggal sudah lewat DAN ada peserta hadir
    if ($tanggalLewat && $hadirCount > 0) {
        $conn->query("UPDATE jadwal_pelatihan SET status = 'Completed' WHERE id = $jadwal_id");
        syncPesertaToMonitoring($conn, $jadwal_id);
        return true;
    }
    
    return false;
}

foreach ($lines as $data) {
    if (count($data) < 3 || empty(trim($data[1] ?? ''))) continue;
    
    $timestamp = trim($data[0] ?? '');
    $nama = trim($data[1] ?? '');
    $pelatihan_nama = trim($data[2] ?? '');
    $tanggal_pelatihan = trim($data[3] ?? '');
    $keterangan = trim($data[4] ?? '');
    $link_sertifikat = trim($data[5] ?? '');
    
    if (empty($nama) || empty($pelatihan_nama)) continue;
    
    // Check if already synced
    $checkHash = md5($timestamp . $nama . $pelatihan_nama);
    $existing = $conn->query("SELECT id FROM monitoring_pelatihan WHERE sync_hash = '$checkHash'")->fetch_assoc();
    if ($existing) {
        $skipped++;
        continue;
    }
    
    // Parse tanggal
    $pelaksanaan = null;
    $tahun = date('Y');
    if (!empty($tanggal_pelatihan)) {
        $date = date_create_from_format('d/m/Y', $tanggal_pelatihan) 
            ?: date_create_from_format('m/d/Y', $tanggal_pelatihan)
            ?: date_create_from_format('Y-m-d', $tanggal_pelatihan)
            ?: date_create($tanggal_pelatihan);
        if ($date) {
            $pelaksanaan = $date->format('Y-m-d');
            $tahun = (int)$date->format('Y');
        }
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
    
    // Find pelatihan (gunakan LIKE untuk matching lebih fleksibel)
    $pelSafe = $conn->real_escape_string($pelatihan_nama);
    $pel = $conn->query("SELECT id FROM pelatihan WHERE nama = '$pelSafe' OR nama LIKE '%$pelSafe%' LIMIT 1")->fetch_assoc();
    
    if (!$pel) {
        // Create new pelatihan jika tidak ada
        $stmt = $conn->prepare("INSERT INTO pelatihan (nama) VALUES (?)");
        $stmt->bind_param("s", $pelatihan_nama);
        $stmt->execute();
        $pelatihan_id = $conn->insert_id;
    } else {
        $pelatihan_id = $pel['id'];
    }
    
    // === INTEGRASI DENGAN JADWAL ===
    // Cari jadwal yang cocok dengan pelatihan dan tanggal
    $jadwal_id = null;
    $jadwalQuery = "SELECT j.id FROM jadwal_pelatihan j 
        WHERE j.pelatihan_id = $pelatihan_id 
        AND j.status != 'Cancelled'";
    
    // Jika ada tanggal pelaksanaan, cari jadwal yang tanggalnya cocok
    if ($pelaksanaan) {
        $jadwalQuery .= " AND ('$pelaksanaan' BETWEEN j.tanggal_mulai AND j.tanggal_selesai 
            OR j.tanggal_mulai = '$pelaksanaan' OR j.tanggal_selesai = '$pelaksanaan')";
    }
    
    $jadwalQuery .= " ORDER BY j.tanggal_mulai DESC LIMIT 1";
    $jadwalResult = $conn->query($jadwalQuery);
    
    if ($jadwalResult && $jadwalResult->num_rows > 0) {
        $jadwal_id = $jadwalResult->fetch_assoc()['id'];
        
        // Cek apakah pegawai sudah terdaftar di jadwal
        $pesertaExists = $conn->query("SELECT id, status FROM jadwal_peserta 
            WHERE jadwal_id = $jadwal_id AND pegawai_id = $pegawai_id")->fetch_assoc();
        
        if ($pesertaExists) {
            // Update status jadi Hadir jika belum
            if ($pesertaExists['status'] !== 'Hadir') {
                $conn->query("UPDATE jadwal_peserta SET status = 'Hadir' WHERE id = {$pesertaExists['id']}");
            }
        } else {
            // Tambahkan sebagai peserta dengan status Hadir
            $stmt = $conn->prepare("INSERT INTO jadwal_peserta (jadwal_id, pegawai_id, status) VALUES (?, ?, 'Hadir')");
            $stmt->bind_param("ii", $jadwal_id, $pegawai_id);
            $stmt->execute();
        }
        
        // Cek dan update status jadwal jika perlu
        if (checkAndCompleteJadwal($conn, $jadwal_id)) {
            $jadwalUpdated++;
        }
    }
    
    // Gabungkan keterangan dengan link sertifikat
    $catatan = $keterangan;
    if (!empty($link_sertifikat)) {
        $catatan = $keterangan . ($keterangan ? ' | ' : '') . $link_sertifikat;
    }
    
    // Insert ke monitoring (dengan jadwal_id jika ada)
    if ($jadwal_id) {
        // Cek apakah sudah ada di monitoring dari jadwal
        $monExists = $conn->query("SELECT id FROM monitoring_pelatihan 
            WHERE jadwal_id = $jadwal_id AND pegawai_id = $pegawai_id")->num_rows;
        
        if ($monExists) {
            // Update sertifikat jika ada
            if (!empty($catatan)) {
                $conn->query("UPDATE monitoring_pelatihan SET no_sertifikat = '$catatan', sync_hash = '$checkHash' 
                    WHERE jadwal_id = $jadwal_id AND pegawai_id = $pegawai_id");
            }
            $skipped++;
            continue;
        }
    }
    
    // Insert monitoring baru
    $stmt = $conn->prepare("INSERT INTO monitoring_pelatihan 
        (pegawai_id, pelatihan_id, jadwal_id, tahun, pelaksanaan, no_sertifikat, jumlah_jp, sync_hash) 
        VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
    $stmt->bind_param("iiiisss", $pegawai_id, $pelatihan_id, $jadwal_id, $tahun, $pelaksanaan, $catatan, $checkHash);
    
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
$response['jadwal_updated'] = $jadwalUpdated;
$response['message'] = "Sync selesai! $imported data baru, $skipped sudah ada" . ($jadwalUpdated > 0 ? ", $jadwalUpdated jadwal di-complete" : "");
$response['lastSync'] = $now;

echo json_encode($response);
$conn->close();
