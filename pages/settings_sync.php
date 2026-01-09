<?php 
require_once '../includes/header.php';
$conn = getConnection();

$message = '';
$messageType = '';

// Get current settings
$result = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('sync_interval_minutes', 'auto_sync_enabled')");
$settings = [];
while($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$syncInterval = $settings['sync_interval_minutes'] ?? 5;
$autoSyncEnabled = ($settings['auto_sync_enabled'] ?? '1') == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        $interval = (int)$_POST['sync_interval'];
        $enabled = isset($_POST['auto_sync_enabled']) ? '1' : '0';
        
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        
        // Save interval
        $key = 'sync_interval_minutes';
        $val = (string)$interval;
        $stmt->bind_param("sss", $key, $val, $val);
        $stmt->execute();
        
        // Save enabled status
        $key = 'auto_sync_enabled';
        $val = $enabled;
        $stmt->bind_param("sss", $key, $val, $val);
        $stmt->execute();
        
        $message = 'Pengaturan berhasil disimpan!';
        $messageType = 'success';
        
        // Refresh settings
        $syncInterval = $interval;
        $autoSyncEnabled = $enabled == '1';
    }
}

// Get last sync info
$lastSyncResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'last_sync'");
$lastSync = $lastSyncResult && $lastSyncResult->num_rows > 0 ? $lastSyncResult->fetch_assoc()['setting_value'] : null;
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Pengaturan Sinkronisasi</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;">PENGATURAN AUTO-SYNC</h1>
    </div>
    <a href="../" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Beranda
    </a>
</div>

<?php if($message): ?>
<div class="mb-4 px-4 py-3 rounded-lg text-sm <?= $messageType == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
    <?= $message ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Settings Form -->
    <div class="lg:col-span-2 bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
            <h2 class="font-semibold text-white text-sm">Pengaturan Auto-Sync</h2>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="save_settings">
            
            <div class="mb-6">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="auto_sync_enabled" value="1" <?= $autoSyncEnabled ? 'checked' : '' ?> class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                    <div>
                        <span class="font-medium text-gray-900">Aktifkan Auto-Sync</span>
                        <p class="text-sm text-gray-500">Sinkronisasi otomatis saat membuka dashboard</p>
                    </div>
                </label>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Interval Sync (Menit)</label>
                <div class="flex items-center gap-4">
                    <input type="range" name="sync_interval" min="1" max="60" value="<?= $syncInterval ?>" 
                        class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                        oninput="document.getElementById('intervalValue').textContent = this.value">
                    <span id="intervalValue" class="text-2xl font-bold text-blue-600 w-16 text-center"><?= $syncInterval ?></span>
                    <span class="text-gray-500">menit</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Sistem akan mengecek data baru setiap <strong id="intervalText"><?= $syncInterval ?></strong> menit</p>
            </div>
            
            <div class="p-4 bg-blue-50 rounded-lg border border-blue-200 mb-6">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="text-sm text-blue-700">
                        <p class="font-medium mb-1">Cara Kerja Auto-Sync:</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>Saat Anda membuka dashboard, sistem akan otomatis mengecek data baru</li>
                            <li>Jika sudah lewat dari interval yang ditentukan, sistem akan sync otomatis</li>
                            <li>Data baru akan langsung muncul tanpa perlu refresh manual</li>
                            <li>Tidak ada duplikat data karena sistem menggunakan hash unik</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="w-full px-4 py-3 text-sm font-medium text-white rounded-lg hover:opacity-90 transition" style="background:#005BAC;">
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Simpan Pengaturan
            </button>
        </form>
    </div>
    
    <!-- Info Panel -->
    <div class="space-y-6">
        <!-- Status -->
        <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200" style="background:#f8fafc;">
                <h2 class="font-semibold text-gray-800 text-sm">Status Sync</h2>
            </div>
            <div class="p-4">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background:<?= $autoSyncEnabled ? '#05966920' : '#6b728020' ?>;">
                        <svg class="w-6 h-6" style="color:<?= $autoSyncEnabled ? '#059669' : '#6b7280' ?>;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900"><?= $autoSyncEnabled ? 'Aktif' : 'Nonaktif' ?></p>
                        <p class="text-xs text-gray-500">Auto-Sync</p>
                    </div>
                </div>
                
                <?php if($lastSync): ?>
                <div class="pt-3 border-t border-gray-200">
                    <p class="text-xs text-gray-500 mb-1">Sync Terakhir:</p>
                    <p class="text-sm font-medium text-gray-900"><?= date('d/m/Y H:i:s', strtotime($lastSync)) ?></p>
                    <p class="text-xs text-gray-400 mt-1">
                        <?php
                        $diff = time() - strtotime($lastSync);
                        $minutes = floor($diff / 60);
                        $hours = floor($minutes / 60);
                        if ($hours > 0) {
                            echo $hours . ' jam yang lalu';
                        } else if ($minutes > 0) {
                            echo $minutes . ' menit yang lalu';
                        } else {
                            echo 'Baru saja';
                        }
                        ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="pt-3 border-t border-gray-200">
                    <p class="text-xs text-gray-500">Belum pernah sync</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200" style="background:#f8fafc;">
                <h2 class="font-semibold text-gray-800 text-sm">Quick Actions</h2>
            </div>
            <div class="p-4 space-y-2">
                <a href="sync_gsheet.php" class="block w-full px-4 py-2 text-sm text-center border border-blue-500 text-blue-600 rounded-lg hover:bg-blue-50 transition">
                    Sync Manual
                </a>
                <a href="test_gsheet_connection.php" target="_blank" class="block w-full px-4 py-2 text-sm text-center border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition">
                    Test Koneksi
                </a>
                <a href="monitoring.php" class="block w-full px-4 py-2 text-sm text-center border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition">
                    Lihat Data
                </a>
            </div>
        </div>
        
        <!-- Rekomendasi -->
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                <div class="text-sm text-blue-700">
                    <p class="font-medium mb-1">💡 Rekomendasi</p>
                    <p class="text-xs">Untuk penggunaan optimal, set interval 5-10 menit agar data selalu update tanpa membebani server.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update interval text dynamically
document.querySelector('input[name="sync_interval"]').addEventListener('input', function() {
    document.getElementById('intervalText').textContent = this.value;
});
</script>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
