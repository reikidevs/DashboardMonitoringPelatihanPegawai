<?php
/**
 * Database Setup Script
 * Jalankan sekali untuk membuat tabel-tabel yang diperlukan
 * URL: /database/setup.php
 */

// Load environment variables tanpa koneksi database
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!getenv($key)) {
            putenv("$key=$value");
        }
    }
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'monitor_pelatihan_pegawai');
define('DB_USER', getenv('DB_USERNAME') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');

// Security check - hanya bisa dijalankan sekali atau dengan token
$setupToken = getenv('SETUP_TOKEN') ?: 'setup123';
$providedToken = $_GET['token'] ?? '';

// Cek apakah sudah pernah setup
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);

if ($conn->connect_error) {
    die("<h2>❌ Koneksi Database Gagal</h2><p>" . $conn->connect_error . "</p>");
}

// Cek apakah tabel sudah ada
$tableCheck = $conn->query("SHOW TABLES LIKE 'pegawai'");
$isFirstSetup = $tableCheck->num_rows === 0;

if (!$isFirstSetup && $providedToken !== $setupToken) {
    die("<h2>⚠️ Database sudah di-setup</h2>
         <p>Jika ingin menjalankan ulang, tambahkan parameter: <code>?token=$setupToken</code></p>
         <p><a href='../'>← Kembali ke Dashboard</a></p>");
}

echo "<h1>🔧 Database Setup</h1>";
echo "<p>Host: " . DB_HOST . ":" . DB_PORT . "</p>";
echo "<p>Database: " . DB_NAME . "</p>";
echo "<hr>";

$queries = [
    // Tabel Pegawai
    "CREATE TABLE IF NOT EXISTS pegawai (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(255) NOT NULL,
        nip VARCHAR(50) NULL,
        jabatan VARCHAR(255),
        email VARCHAR(100),
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_nama (nama),
        INDEX idx_nip (nip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" => "Tabel pegawai",

    // Tabel Kategori Pelatihan
    "CREATE TABLE IF NOT EXISTS kategori_pelatihan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" => "Tabel kategori_pelatihan",

    // Tabel Lingkup Pelatihan
    "CREATE TABLE IF NOT EXISTS lingkup_pelatihan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" => "Tabel lingkup_pelatihan",

    // Tabel Pelatihan
    "CREATE TABLE IF NOT EXISTS pelatihan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(255) NOT NULL,
        lingkup_id INT,
        kategori_id INT,
        tipe ENUM('Daring', 'Luring', 'Hybrid') DEFAULT 'Daring',
        jumlah_jp INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_nama (nama),
        INDEX idx_kategori (kategori_id),
        INDEX idx_lingkup (lingkup_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" => "Tabel pelatihan",

    // Tabel Jadwal Pelatihan
    "CREATE TABLE IF NOT EXISTS jadwal_pelatihan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pelatihan_id INT NOT NULL,
        tanggal_mulai DATE,
        tanggal_selesai DATE,
        rencana_peserta INT DEFAULT 0,
        biaya DECIMAL(15,2) DEFAULT 0,
        status ENUM('Not Started', 'In-Progress', 'Completed', 'Cancelled') DEFAULT 'Not Started',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pelatihan (pelatihan_id),
        INDEX idx_status (status),
        INDEX idx_tanggal (tanggal_mulai)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" => "Tabel jadwal_pelatihan",

    // Tabel Jadwal Peserta
    "CREATE TABLE IF NOT EXISTS jadwal_peserta (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jadwal_id INT NOT NULL,
        pegawai_id INT NOT NULL,
        status ENUM('Terdaftar', 'Hadir', 'Tidak Hadir', 'Batal') DEFAULT 'Terdaftar',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_peserta (jadwal_id, pegawai_id),
        INDEX idx_jadwal (jadwal_id),
        INDEX idx_pegawai (pegawai_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" => "Tabel jadwal_peserta",

    // Tabel Monitoring Pelatihan
    "CREATE TABLE IF NOT EXISTS monitoring_pelatihan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pegawai_id INT NOT NULL,
        pelatihan_id INT NOT NULL,
        tahun YEAR,
        pelaksanaan DATE,
        no_sertifikat VARCHAR(255),
        jumlah_jp INT DEFAULT 0,
        sync_hash VARCHAR(64) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pegawai (pegawai_id),
        INDEX idx_pelatihan (pelatihan_id),
        INDEX idx_tahun (tahun),
        INDEX idx_sync_hash (sync_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" => "Tabel monitoring_pelatihan",

    // Tabel Kewajiban Pelatihan
    "CREATE TABLE IF NOT EXISTS kewajiban_pelatihan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pegawai_id INT NOT NULL,
        pelatihan_id INT NOT NULL,
        tahun_target YEAR,
        keterangan TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_kewajiban (pegawai_id, pelatihan_id),
        INDEX idx_pegawai (pegawai_id),
        INDEX idx_pelatihan (pelatihan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" => "Tabel kewajiban_pelatihan",

    // Tabel Settings
    "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" => "Tabel settings",
];

// Execute queries
$success = 0;
$failed = 0;

foreach ($queries as $sql => $description) {
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>✅ $description</p>";
        $success++;
    } else {
        echo "<p style='color:red;'>❌ $description: " . $conn->error . "</p>";
        $failed++;
    }
}

echo "<hr>";

// Insert default data
echo "<h2>📦 Insert Data Default</h2>";

// Kategori Pelatihan
$conn->query("INSERT IGNORE INTO kategori_pelatihan (id, nama) VALUES 
    (1, 'Mutlak'), (2, 'Penting'), (3, 'Perlu'), (4, 'Pelatihan IDEAS')");
echo "<p style='color:green;'>✅ Data kategori_pelatihan</p>";

// Lingkup Pelatihan
$conn->query("INSERT IGNORE INTO lingkup_pelatihan (id, nama) VALUES 
    (1, 'TI'), (2, 'Komoditi Obat'), (3, 'Komoditi Pangan'), 
    (4, 'Komoditi OBA-SK'), (5, 'Komoditi Kosmetik'), (6, 'Keuangan'), (7, 'Lainnya')");
echo "<p style='color:green;'>✅ Data lingkup_pelatihan</p>";

// Default Settings
$defaultSettings = [
    ['gsheet_id', '1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk'],
    ['gsheet_name', 'Form Responses 1'],
    ['sync_interval_minutes', '5'],
    ['auto_sync_enabled', '1'],
];

$stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
foreach ($defaultSettings as $setting) {
    $stmt->bind_param("ss", $setting[0], $setting[1]);
    $stmt->execute();
}
echo "<p style='color:green;'>✅ Data settings</p>";

echo "<hr>";
echo "<h2 style='color:green;'>🎉 Setup Selesai!</h2>";
echo "<p>Berhasil: $success | Gagal: $failed</p>";
echo "<p><a href='../' style='display:inline-block; padding:10px 20px; background:#005BAC; color:white; text-decoration:none; border-radius:5px;'>Buka Dashboard →</a></p>";

$conn->close();
?>
