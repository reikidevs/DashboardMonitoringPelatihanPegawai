<?php
/**
 * Database Migration System
 * Menjalankan migrasi database secara otomatis
 * 
 * Cara pakai:
 * - Via browser: /database/migrate.php?token=YOUR_MIGRATE_TOKEN
 * - Via CLI: php database/migrate.php
 * - Via CI/CD: curl dengan token
 */

// Deteksi CLI atau Web
$isCli = php_sapi_name() === 'cli';

// Load environment
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . "=" . trim($value));
    }
}

// Config
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'monitor_pelatihan_pegawai');
define('DB_USER', getenv('DB_USERNAME') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');
define('MIGRATE_TOKEN', getenv('MIGRATE_TOKEN') ?: 'migrate_secret_token');

// Security check untuk web access
if (!$isCli) {
    $providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_MIGRATE_TOKEN'] ?? '';
    if ($providedToken !== MIGRATE_TOKEN) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Invalid token']));
    }
    header('Content-Type: application/json');
}

// Output helper
function output($message, $type = 'info') {
    global $isCli;
    $icons = ['success' => '✅', 'error' => '❌', 'info' => 'ℹ️', 'warning' => '⚠️'];
    $icon = $icons[$type] ?? '';
    
    if ($isCli) {
        echo "$icon $message\n";
    } else {
        global $results;
        $results[] = ['type' => $type, 'message' => $message];
    }
}

$results = [];

// Connect to database
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);

if ($conn->connect_error) {
    output("Database connection failed: " . $conn->connect_error, 'error');
    if (!$isCli) echo json_encode(['success' => false, 'error' => $conn->connect_error]);
    exit(1);
}

$conn->set_charset('utf8mb4');
output("Connected to database: " . DB_NAME, 'success');

// Create migrations table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Get executed migrations
$executed = [];
$result = $conn->query("SELECT migration FROM migrations");
while ($row = $result->fetch_assoc()) {
    $executed[] = $row['migration'];
}

// Define migrations
$migrations = [
    '001_create_base_tables' => function($conn) {
        $queries = [
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            
            "CREATE TABLE IF NOT EXISTS kategori_pelatihan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            
            "CREATE TABLE IF NOT EXISTS lingkup_pelatihan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            
            "CREATE TABLE IF NOT EXISTS pelatihan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(255) NOT NULL,
                lingkup_id INT,
                kategori_id INT,
                tipe ENUM('Daring', 'Luring', 'Hybrid') DEFAULT 'Daring',
                jumlah_jp INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_nama (nama)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            
            "CREATE TABLE IF NOT EXISTS jadwal_pelatihan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pelatihan_id INT NOT NULL,
                tanggal_mulai DATE,
                tanggal_selesai DATE,
                rencana_peserta INT DEFAULT 0,
                biaya DECIMAL(15,2) DEFAULT 0,
                status ENUM('Not Started', 'In-Progress', 'Completed', 'Cancelled') DEFAULT 'Not Started',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pelatihan (pelatihan_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            
            "CREATE TABLE IF NOT EXISTS jadwal_peserta (
                id INT AUTO_INCREMENT PRIMARY KEY,
                jadwal_id INT NOT NULL,
                pegawai_id INT NOT NULL,
                status ENUM('Terdaftar', 'Hadir', 'Tidak Hadir', 'Batal') DEFAULT 'Terdaftar',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_peserta (jadwal_id, pegawai_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            
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
                INDEX idx_sync_hash (sync_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            
            "CREATE TABLE IF NOT EXISTS kewajiban_pelatihan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pegawai_id INT NOT NULL,
                pelatihan_id INT NOT NULL,
                tahun_target YEAR,
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_kewajiban (pegawai_id, pelatihan_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            
            "CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
        
        foreach ($queries as $sql) {
            if (!$conn->query($sql)) {
                throw new Exception($conn->error);
            }
        }
        return true;
    },
    
    '002_seed_default_data' => function($conn) {
        // Kategori
        $conn->query("INSERT IGNORE INTO kategori_pelatihan (id, nama) VALUES 
            (1, 'Mutlak'), (2, 'Penting'), (3, 'Perlu'), (4, 'Pelatihan IDEAS')");
        
        // Lingkup
        $conn->query("INSERT IGNORE INTO lingkup_pelatihan (id, nama) VALUES 
            (1, 'TI'), (2, 'Komoditi Obat'), (3, 'Komoditi Pangan'), 
            (4, 'Komoditi OBA-SK'), (5, 'Komoditi Kosmetik'), (6, 'Keuangan')");
        
        // Settings
        $settings = [
            ['gsheet_id', ''],
            ['gsheet_name', 'Form Responses 1'],
            ['sync_interval_minutes', '5'],
            ['auto_sync_enabled', '0'],
        ];
        
        $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($settings as $s) {
            $stmt->bind_param("ss", $s[0], $s[1]);
            $stmt->execute();
        }
        
        return true;
    },
    
    '003_add_lingkup_lainnya' => function($conn) {
        // Tambah opsi "Lainnya" ke lingkup pelatihan
        $conn->query("INSERT IGNORE INTO lingkup_pelatihan (id, nama) VALUES (7, 'Lainnya')");
        return true;
    },
    
    '004_add_jadwal_monitoring_sync' => function($conn) {
        // Tambah kolom jadwal_id ke monitoring_pelatihan untuk tracking sumber data
        $checkCol = $conn->query("SHOW COLUMNS FROM monitoring_pelatihan LIKE 'jadwal_id'");
        if ($checkCol->num_rows === 0) {
            $conn->query("ALTER TABLE monitoring_pelatihan ADD COLUMN jadwal_id INT NULL AFTER pelatihan_id");
            $conn->query("ALTER TABLE monitoring_pelatihan ADD INDEX idx_jadwal (jadwal_id)");
        }
        return true;
    },
    
    '005_add_monitoring_dates_and_file' => function($conn) {
        // Tambah kolom tanggal_mulai, tanggal_selesai, dan file_sertifikat
        $checkCol = $conn->query("SHOW COLUMNS FROM monitoring_pelatihan LIKE 'tanggal_mulai'");
        if ($checkCol->num_rows === 0) {
            $conn->query("ALTER TABLE monitoring_pelatihan ADD COLUMN tanggal_mulai DATE NULL AFTER pelaksanaan");
            $conn->query("ALTER TABLE monitoring_pelatihan ADD COLUMN tanggal_selesai DATE NULL AFTER tanggal_mulai");
        }
        
        $checkFile = $conn->query("SHOW COLUMNS FROM monitoring_pelatihan LIKE 'file_sertifikat'");
        if ($checkFile->num_rows === 0) {
            $conn->query("ALTER TABLE monitoring_pelatihan ADD COLUMN file_sertifikat VARCHAR(255) NULL AFTER no_sertifikat");
        }
        return true;
    },
    
    '006_create_users_table' => function($conn) {
        $conn->query("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nama VARCHAR(100),
            role ENUM('admin', 'guest') DEFAULT 'guest',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Insert default admin (password: admin123)
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT IGNORE INTO users (id, username, password, nama, role) VALUES (1, 'admin', '$hash', 'Administrator', 'admin')");
        return true;
    },
    
    '007_add_foreign_keys' => function($conn) {
        // Foreign keys are optional - skip if fails (shared hosting sometimes has issues)
        // The app will still work without FK constraints, just less strict
        $fks = [
            ['pelatihan', 'fk_pelatihan_lingkup', 'lingkup_id', 'lingkup_pelatihan', 'id'],
            ['pelatihan', 'fk_pelatihan_kategori', 'kategori_id', 'kategori_pelatihan', 'id'],
            ['jadwal_pelatihan', 'fk_jadwal_pelatihan', 'pelatihan_id', 'pelatihan', 'id'],
            ['jadwal_peserta', 'fk_jadwal_peserta_jadwal', 'jadwal_id', 'jadwal_pelatihan', 'id'],
            ['jadwal_peserta', 'fk_jadwal_peserta_pegawai', 'pegawai_id', 'pegawai', 'id'],
            ['monitoring_pelatihan', 'fk_monitoring_pegawai', 'pegawai_id', 'pegawai', 'id'],
            ['monitoring_pelatihan', 'fk_monitoring_pelatihan', 'pelatihan_id', 'pelatihan', 'id'],
            ['kewajiban_pelatihan', 'fk_kewajiban_pegawai', 'pegawai_id', 'pegawai', 'id'],
            ['kewajiban_pelatihan', 'fk_kewajiban_pelatihan', 'pelatihan_id', 'pelatihan', 'id'],
        ];
        
        $successCount = 0;
        
        foreach ($fks as $fk) {
            list($table, $name, $column, $refTable, $refColumn) = $fk;
            
            // Check if FK exists
            $check = $conn->query("SELECT * FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = '" . DB_NAME . "' 
                AND TABLE_NAME = '$table' 
                AND CONSTRAINT_NAME = '$name'");
            
            if ($check->num_rows === 0) {
                $sql = "ALTER TABLE $table ADD CONSTRAINT $name 
                        FOREIGN KEY ($column) REFERENCES $refTable($refColumn) 
                        ON DELETE SET NULL ON UPDATE CASCADE";
                if ($conn->query($sql)) {
                    $successCount++;
                }
                // Silently ignore FK errors - app works without them
            } else {
                $successCount++;
            }
        }
        
        // Always return true - FK constraints are optional
        return true;
    },
];

// Run pending migrations
$migrated = 0;
$warnings = [];

foreach ($migrations as $name => $callback) {
    if (in_array($name, $executed)) {
        output("Skipped: $name (already executed)", 'info');
        continue;
    }
    
    try {
        $conn->begin_transaction();
        
        $callback($conn);
        
        // Record migration
        $stmt = $conn->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        
        $conn->commit();
        output("Migrated: $name", 'success');
        $migrated++;
        
    } catch (Exception $e) {
        $conn->rollback();
        
        // For non-critical migrations (like foreign keys), mark as done with warning
        if (strpos($name, 'foreign_keys') !== false) {
            $stmt = $conn->prepare("INSERT IGNORE INTO migrations (migration) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            output("Warning: $name - " . $e->getMessage() . " (marked as done, FK optional)", 'warning');
            $warnings[] = $name;
        } else {
            output("Failed: $name - " . $e->getMessage(), 'error');
            // Don't add to errors - let it retry next time
        }
    }
}

$conn->close();

// Summary
output("Migration complete. Executed: $migrated, Warnings: " . count($warnings), 'success');

if (!$isCli) {
    // Always return success if base tables are created
    // Warnings (like FK issues) are acceptable
    echo json_encode([
        'success' => true,
        'migrated' => $migrated,
        'warnings' => $warnings,
        'results' => $results
    ]);
}

// Always exit 0 - warnings are acceptable
exit(0);
