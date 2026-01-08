<?php 
require_once '../includes/header.php';
$conn = getConnection();

// Check if settings table exists
$conn->query("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Get saved settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
while($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default spreadsheet ID dari Google Form yang sudah dibuat
$defaultSpreadsheetId = '1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk';
$spreadsheetId = $settings['gsheet_id'] ?? $defaultSpreadsheetId;
$sheetName = $settings['gsheet_name'] ?? 'Form Responses 1';
$lastSync = $settings['last_sync'] ?? null;

$message = '';
$messageType = '';
$previewData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        $spreadsheetId = trim($_POST['spreadsheet_id']);
        $sheetName = trim($_POST['sheet_name']) ?: 'Form Responses 1';
        
        // Extract ID from URL if full URL is provided
        if (strpos($spreadsheetId, 'docs.google.com') !== false) {
            preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $spreadsheetId, $matches);
            $spreadsheetId = $matches[1] ?? $spreadsheetId;
        }
        
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $key, $val, $val);
        
        $key = 'gsheet_id'; $val = $spreadsheetId; $stmt->execute();
        $key = 'gsheet_name'; $val = $sheetName; $stmt->execute();
        
        $message = 'Pengaturan berhasil disimpan!';
        $messageType = 'success';
    }
    
    if ($action === 'sync_now') {
        $spreadsheetId = $settings['gsheet_id'] ?? '';
        $sheetName = $settings['gsheet_name'] ?? 'Form Responses 1';
        
        if (empty($spreadsheetId)) {
            $message = 'Spreadsheet ID belum diatur!';
            $messageType = 'danger';
        } else {
            // Fetch data from Google Sheets (public/published sheet)
            $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0'
                ]
            ]);
            
            $csvData = @file_get_contents($url, false, $context);
            
            if ($csvData === false) {
                $message = 'Gagal mengambil data dari Google Sheets. Pastikan spreadsheet sudah di-publish ke web (File → Share → Publish to web)';
                $messageType = 'danger';
            } else {
                // Parse CSV
                $lines = array_map('str_getcsv', explode("\n", $csvData));
                $header = array_shift($lines); // Remove header
                
                $imported = 0;
                $skipped = 0;
                $errors = [];
                
                // Column mapping sesuai Google Form:
                // 0: Timestamp
                // 1: Nama Pegawai
                // 2: Pelatihan yang sudah diikuti
                // 3: Tanggal Pelatihan
                // 4: Keterangan
                // 5: Upload Sertifikat (link file)
                
                foreach ($lines as $data) {
                    if (count($data) < 3 || empty(trim($data[1] ?? ''))) continue;
                    
                    // Skip if already imported (check by timestamp + nama + pelatihan)
                    $timestamp = trim($data[0] ?? '');
                    $nama = trim($data[1] ?? '');
                    $pelatihan_nama = trim($data[2] ?? ''); // Pelatihan yang sudah diikuti
                    $tanggal_pelatihan = trim($data[3] ?? '');
                    $keterangan = trim($data[4] ?? ''); // Keterangan/No sertifikat
                    $link_sertifikat = trim($data[5] ?? ''); // Link upload sertifikat
                    
                    // Extract tahun dari tanggal atau timestamp
                    $tahun = date('Y');
                    if (!empty($tanggal_pelatihan)) {
                        $date = date_create_from_format('d/m/Y', $tanggal_pelatihan) 
                            ?: date_create_from_format('m/d/Y', $tanggal_pelatihan)
                            ?: date_create_from_format('Y-m-d', $tanggal_pelatihan)
                            ?: date_create($tanggal_pelatihan);
                        if ($date) $tahun = (int)$date->format('Y');
                    }
                    
                    if (empty($nama) || empty($pelatihan_nama)) continue;
                    
                    // Check if already synced (by unique combination)
                    $checkHash = md5($timestamp . $nama . $pelatihan_nama);
                    $existing = $conn->query("SELECT id FROM monitoring_pelatihan WHERE sync_hash = '$checkHash'")->fetch_assoc();
                    if ($existing) {
                        $skipped++;
                        continue;
                    }
                    
                    // Find or create pegawai (tanpa NIP karena tidak ada di form)
                    $namaSafe = $conn->real_escape_string($nama);
                    $pegawai = $conn->query("SELECT id FROM pegawai WHERE nama = '$namaSafe' LIMIT 1")->fetch_assoc();
                    if (!$pegawai) {
                        // Generate NIP otomatis jika tidak ada (format: AUTO-timestamp)
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
                $lastSync = $now;
                
                $message = "Sync selesai! $imported data baru diimport, $skipped data sudah ada sebelumnya.";
                $messageType = 'success';
            }
        }
    }
    
    if ($action === 'preview') {
        $spreadsheetId = $settings['gsheet_id'] ?? '';
        $sheetName = $settings['gsheet_name'] ?? 'Form Responses 1';
        
        if (!empty($spreadsheetId)) {
            $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
            $csvData = @file_get_contents($url);
            
            if ($csvData) {
                $lines = array_map('str_getcsv', explode("\n", $csvData));
                $previewData = array_slice($lines, 0, 11); // Header + 10 rows
            }
        }
    }
}

// Refresh settings
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
while($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
// Default spreadsheet ID dari Google Form yang sudah dibuat
$defaultSpreadsheetId = '1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk';
$spreadsheetId = $settings['gsheet_id'] ?? $defaultSpreadsheetId;
$sheetName = $settings['gsheet_name'] ?? 'Form Responses 1';
$lastSync = $settings['last_sync'] ?? null;
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Sinkronisasi Otomatis</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;">SYNC GOOGLE SHEETS</h1>
    </div>
    <div class="flex gap-2">
        <a href="https://forms.gle/gtAyX37spwN6FqdJ7" target="_blank" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-blue-500 bg-blue-500 hover:bg-blue-600 text-white">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Isi Google Form
        </a>
        <a href="../" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Beranda
        </a>
    </div>
</div>

<?php if($message): ?>
<div class="mb-4 px-4 py-3 rounded-lg text-sm <?= $messageType == 'success' ? 'bg-green-100 text-green-700' : ($messageType == 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') ?>">
    <?= $message ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Settings -->
    <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
            <h2 class="font-semibold text-white text-sm">Pengaturan Google Sheets</h2>
        </div>
        <form method="POST" class="p-4">
            <input type="hidden" name="action" value="save_settings">
            
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-600 mb-1">Spreadsheet ID atau URL</label>
                <input type="text" name="spreadsheet_id" value="<?= htmlspecialchars($spreadsheetId) ?>" 
                    placeholder="1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Bisa paste URL lengkap atau ID saja</p>
            </div>
            
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-600 mb-1">Nama Sheet</label>
                <input type="text" name="sheet_name" value="<?= htmlspecialchars($sheetName) ?>" 
                    placeholder="Form Responses 1"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Biasanya "Form Responses 1" untuk Google Form</p>
            </div>
            
            <button type="submit" class="w-full px-4 py-2 text-sm text-white rounded hover:opacity-90" style="background:#005BAC;">
                Simpan Pengaturan
            </button>
        </form>
        
        <?php if($spreadsheetId): ?>
        <div class="px-4 pb-4 space-y-2">
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="sync_now">
                <button type="submit" class="w-full px-4 py-2 text-sm text-white rounded hover:opacity-90" style="background:#059669;">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Sinkronkan Sekarang
                </button>
            </form>
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="preview">
                <button type="submit" class="w-full px-4 py-2 text-sm border border-gray-300 rounded hover:bg-gray-50 text-gray-600">
                    Preview Data
                </button>
            </form>
            <a href="../api/test_gsheet_connection.php" target="_blank" class="block w-full px-4 py-2 text-sm text-center border border-blue-300 rounded hover:bg-blue-50 text-blue-600">
                Test Koneksi
            </a>
        </div>
        <?php endif; ?>
        
        <?php if($lastSync): ?>
        <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
            <p class="text-xs text-gray-500">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Terakhir sync: <?= date('d/m/Y H:i', strtotime($lastSync)) ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Instructions -->
    <div class="lg:col-span-2 bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
            <h2 class="font-semibold text-white text-sm">Cara Pengaturan</h2>
        </div>
        <div class="p-4">
            <div class="space-y-4 text-sm">
                <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                    <p class="font-medium text-green-800 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Link Penting
                    </p>
                    <div class="text-xs text-green-700 space-y-1">
                        <p><strong>Google Form:</strong> <a href="https://forms.gle/gtAyX37spwN6FqdJ7" target="_blank" class="underline hover:text-green-900">https://forms.gle/gtAyX37spwN6FqdJ7</a></p>
                        <p><strong>Spreadsheet:</strong> <a href="https://docs.google.com/spreadsheets/d/1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk/edit" target="_blank" class="underline hover:text-green-900">Lihat Spreadsheet</a></p>
                    </div>
                </div>
                
                <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                    <p class="font-medium text-yellow-800 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Penting: Publish Spreadsheet (Sudah Dilakukan ✓)
                    </p>
                    <p class="text-yellow-700 text-xs">Spreadsheet sudah di-publish dan siap untuk sinkronisasi otomatis.</p>
                </div>
                
                <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <p class="font-medium text-blue-800 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        Format Kolom Google Form
                    </p>
                    <p class="text-blue-700 text-xs mb-2">Urutan kolom yang diharapkan di spreadsheet (sesuai GForm):</p>
                    <ol class="list-decimal list-inside text-xs text-blue-700 space-y-1">
                        <li><strong>Timestamp</strong> - Otomatis dari Google Form</li>
                        <li><strong>Nama Pegawai</strong> - Nama lengkap pegawai</li>
                        <li><strong>Pelatihan yang sudah diikuti</strong> - Nama pelatihan</li>
                        <li><strong>Tanggal Pelatihan</strong> - Format dd/mm/yyyy</li>
                        <li><strong>Keterangan</strong> - Nomor sertifikat atau catatan</li>
                        <li><strong>Upload Sertifikat</strong> - Link file sertifikat</li>
                    </ol>
                </div>
                
                <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                    <p class="font-medium text-green-800 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Fitur Sync
                    </p>
                    <ul class="list-disc list-inside text-xs text-green-700 space-y-1">
                        <li>Data yang sudah di-sync tidak akan duplikat</li>
                        <li>Pegawai & pelatihan baru otomatis ditambahkan</li>
                        <li>Kategori dicocokkan otomatis</li>
                        <li>Bisa sync berkali-kali tanpa khawatir duplikat</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if(!empty($previewData)): ?>
<!-- Preview Data -->
<div class="mt-6 bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200" style="background:#f8fafc;">
        <h2 class="font-semibold text-gray-800 text-sm">Preview Data dari Google Sheets</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <?php foreach($previewData as $i => $row): ?>
            <tr class="<?= $i == 0 ? 'bg-gray-100 font-semibold' : ($i % 2 == 0 ? 'bg-gray-50' : '') ?>">
                <?php foreach($row as $cell): ?>
                <td class="px-2 py-1 border border-gray-200 truncate max-w-xs"><?= htmlspecialchars($cell) ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <div class="px-4 py-2 border-t border-gray-200 text-xs text-gray-500">
        Menampilkan 10 baris pertama
    </div>
</div>
<?php endif; ?>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
