<?php
/**
 * Script untuk update database - menambahkan kolom sync_hash
 * Jalankan file ini sekali saja: http://localhost:3000/database/run_update_database.php
 */

require_once '../config/config.php';
$conn = getConnection();

echo "<h2>Update Database untuk Sync Google Sheets</h2>";
echo "<hr>";

// 1. Make NIP column nullable
echo "<p>➡️ Mengubah kolom 'nip' menjadi opsional (nullable)...</p>";
$sql = "ALTER TABLE pegawai MODIFY COLUMN nip VARCHAR(50) NULL";
if ($conn->query($sql)) {
    echo "<p style='color:green;'>✅ Kolom 'nip' berhasil diubah menjadi opsional!</p>";
} else {
    if (strpos($conn->error, 'Duplicate') === false) {
        echo "<p style='color:orange;'>⚠️ " . $conn->error . "</p>";
    }
}

echo "<hr>";

// 2. Check if sync_hash column already exists
$checkColumn = $conn->query("SHOW COLUMNS FROM monitoring_pelatihan LIKE 'sync_hash'");
if ($checkColumn->num_rows > 0) {
    echo "<p style='color:orange;'>⚠️ Kolom 'sync_hash' sudah ada di database.</p>";
} else {
    echo "<p>➡️ Menambahkan kolom 'sync_hash' ke tabel monitoring_pelatihan...</p>";
    
    $sql = "ALTER TABLE monitoring_pelatihan 
            ADD COLUMN sync_hash VARCHAR(64) DEFAULT NULL AFTER jumlah_jp,
            ADD INDEX idx_sync_hash (sync_hash)";
    
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>✅ Kolom 'sync_hash' berhasil ditambahkan!</p>";
    } else {
        echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
    }
}

echo "<hr>";

// 3. Create jadwal_peserta table
echo "<p>➡️ Membuat tabel 'jadwal_peserta' jika belum ada...</p>";
$sql = "CREATE TABLE IF NOT EXISTS jadwal_peserta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jadwal_id INT NOT NULL,
    pegawai_id INT NOT NULL,
    status ENUM('Terdaftar', 'Hadir', 'Tidak Hadir', 'Batal') DEFAULT 'Terdaftar',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal_pelatihan(id) ON DELETE CASCADE,
    FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE,
    UNIQUE KEY unique_peserta (jadwal_id, pegawai_id)
)";
if ($conn->query($sql)) {
    echo "<p style='color:green;'>✅ Tabel 'jadwal_peserta' berhasil dibuat/sudah ada!</p>";
} else {
    if (strpos($conn->error, 'already exists') === false && strpos($conn->error, 'Duplicate') === false) {
        echo "<p style='color:orange;'>⚠️ " . $conn->error . "</p>";
    } else {
        echo "<p style='color:green;'>✅ Tabel 'jadwal_peserta' sudah ada!</p>";
    }
}

echo "<hr>";

// 4. Check if settings table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'settings'");
if ($checkTable->num_rows > 0) {
    echo "<p style='color:orange;'>⚠️ Tabel 'settings' sudah ada.</p>";
} else {
    echo "<p>➡️ Membuat tabel 'settings'...</p>";
    
    $sql = "CREATE TABLE settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>✅ Tabel 'settings' berhasil dibuat!</p>";
    } else {
        echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
    }
}

echo "<hr>";

// Insert default settings
echo "<p>➡️ Menyimpan pengaturan default...</p>";

$stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");

// Spreadsheet ID
$key = 'gsheet_id';
$val = '1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk';
$stmt->bind_param("sss", $key, $val, $val);
$stmt->execute();
echo "<p style='color:green;'>✅ Spreadsheet ID disimpan: $val</p>";

// Sheet Name
$key = 'gsheet_name';
$val = 'Form Responses 1';
$stmt->bind_param("sss", $key, $val, $val);
$stmt->execute();
echo "<p style='color:green;'>✅ Sheet Name disimpan: $val</p>";

// Auto-Sync Interval
$key = 'sync_interval_minutes';
$val = '5';
$stmt->bind_param("sss", $key, $val, $val);
$stmt->execute();
echo "<p style='color:green;'>✅ Sync Interval disimpan: $val menit</p>";

// Auto-Sync Enabled
$key = 'auto_sync_enabled';
$val = '1';
$stmt->bind_param("sss", $key, $val, $val);
$stmt->execute();
echo "<p style='color:green;'>✅ Auto-Sync diaktifkan</p>";

echo "<hr>";
echo "<h3 style='color:green;'>✅ Update Database Selesai!</h3>";
echo "<p>Sekarang Anda bisa menggunakan fitur sinkronisasi Google Sheets dan Auto-Sync.</p>";
echo "<p><a href='../pages/sync_gsheet.php' style='display:inline-block; padding:10px 20px; background:#059669; color:white; text-decoration:none; border-radius:5px;'>Buka Halaman Sync →</a></p>";
echo "<p><a href='../pages/settings_sync.php' style='display:inline-block; padding:10px 20px; background:#6366f1; color:white; text-decoration:none; border-radius:5px; margin-top:10px;'>Pengaturan Auto-Sync →</a></p>";
echo "<p><a href='../' style='display:inline-block; padding:10px 20px; background:#005BAC; color:white; text-decoration:none; border-radius:5px; margin-top:10px;'>Kembali ke Beranda →</a></p>";

$conn->close();
?>
