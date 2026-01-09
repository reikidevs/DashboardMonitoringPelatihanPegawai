<?php 
require_once 'includes/header.php';
$conn = getConnection();

$canEdit = isAdmin();

// Stats untuk semua user
$totalPelatihan = $conn->query("SELECT COUNT(*) as c FROM pelatihan")->fetch_assoc()['c'];
$totalJadwal = $conn->query("SELECT COUNT(*) as c FROM jadwal_pelatihan")->fetch_assoc()['c'];
$totalMonitoring = $conn->query("SELECT COUNT(*) as c FROM monitoring_pelatihan")->fetch_assoc()['c'];
$totalJP = $conn->query("SELECT COALESCE(SUM(jumlah_jp),0) as c FROM monitoring_pelatihan")->fetch_assoc()['c'];

// Stats khusus admin
if ($canEdit) {
    $totalPegawai = $conn->query("SELECT COUNT(*) as c FROM pegawai")->fetch_assoc()['c'];
}

// Jadwal Status
$jadwalStatus = $conn->query("SELECT status, COUNT(*) as total FROM jadwal_pelatihan GROUP BY status");
$statusData = [];
while($s = $jadwalStatus->fetch_assoc()) { $statusData[$s['status']] = $s['total']; }

// Recent Monitoring
$recentMonitoring = $conn->query("SELECT m.*, pg.nama as pegawai_nama, p.nama as pelatihan_nama 
    FROM monitoring_pelatihan m 
    LEFT JOIN pegawai pg ON m.pegawai_id = pg.id 
    LEFT JOIN pelatihan p ON m.pelatihan_id = p.id 
    ORDER BY m.created_at DESC LIMIT 5");
?>

<style>
.card-hover { transition: all 0.3s ease; cursor: pointer; }
.card-hover:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -10px rgba(0, 91, 172, 0.2); }
.stat-card { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); }
</style>

<!-- Header Dashboard -->
<div class="mb-8">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-gray-500 mt-1">Sistem Monitoring Pelatihan Pegawai BPOM</p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <?php
                $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                echo $hari[date('w')] . ', ' . date('d') . ' ' . $bulan[date('n')] . ' ' . date('Y');
                ?>
            </p>
            <?php if($canEdit): ?>
            <div id="autoSyncStatus" class="mt-2 text-xs"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if($canEdit): ?>
<!-- Auto Sync Notification (Admin Only) -->
<div id="autoSyncNotification" class="hidden mb-4 px-4 py-3 rounded-lg text-sm bg-blue-50 border border-blue-200 text-blue-700">
    <div class="flex items-center gap-2">
        <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        <span id="autoSyncMessage">Memeriksa data baru dari Google Sheets...</span>
    </div>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-<?= $canEdit ? '5' : '4' ?> gap-4 mb-8">
    <?php if($canEdit): ?>
    <!-- Pegawai (Admin Only) -->
    <div class="stat-card rounded-xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background:#005BAC20;">
                <svg class="w-6 h-6" style="color:#005BAC;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-gray-900 mb-1"><?= $totalPegawai ?></p>
        <p class="text-sm text-gray-500">Total Pegawai</p>
    </div>
    <?php endif; ?>

    <!-- Pelatihan -->
    <div class="stat-card rounded-xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background:#00A65120;">
                <svg class="w-6 h-6" style="color:#00A651;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-gray-900 mb-1"><?= $totalPelatihan ?></p>
        <p class="text-sm text-gray-500">Jenis Pelatihan</p>
    </div>

    <!-- Jadwal -->
    <div class="stat-card rounded-xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background:#7c3aed20;">
                <svg class="w-6 h-6" style="color:#7c3aed;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-gray-900 mb-1"><?= $totalJadwal ?></p>
        <p class="text-sm text-gray-500">Jadwal Pelatihan</p>
    </div>

    <!-- Monitoring -->
    <div class="stat-card rounded-xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background:#d9770620;">
                <svg class="w-6 h-6" style="color:#d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-gray-900 mb-1"><?= $totalMonitoring ?></p>
        <p class="text-sm text-gray-500">Data Monitoring</p>
    </div>

    <!-- Total JP -->
    <div class="stat-card rounded-xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background:#dc262620;">
                <svg class="w-6 h-6" style="color:#dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-gray-900 mb-1"><?= $totalJP ?></p>
        <p class="text-sm text-gray-500">Total Jam Pelajaran</p>
    </div>
</div>

<!-- Menu Utama -->
<div class="mb-8">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Menu Utama</h2>
    <div class="grid grid-cols-2 md:grid-cols-<?= $canEdit ? '4' : '3' ?> gap-4">
        <?php if($canEdit): ?>
        <!-- Pegawai (Admin Only) -->
        <a href="pages/pegawai.php" class="card-hover bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
            <div class="w-14 h-14 rounded-xl flex items-center justify-center mb-4" style="background:#005BAC;">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
            </div>
            <h3 class="font-semibold text-gray-900 mb-1">Data Pegawai</h3>
            <p class="text-sm text-gray-500">Kelola data pegawai</p>
        </a>
        <?php endif; ?>

        <!-- Jadwal -->
        <a href="pages/jadwal.php" class="card-hover bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
            <div class="w-14 h-14 rounded-xl flex items-center justify-center mb-4" style="background:#7c3aed;">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="font-semibold text-gray-900 mb-1">Jadwal Pelatihan</h3>
            <p class="text-sm text-gray-500">Rencana pelatihan</p>
        </a>

        <!-- Kalender -->
        <a href="pages/kalender.php" class="card-hover bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
            <div class="w-14 h-14 rounded-xl flex items-center justify-center mb-4" style="background:#0891b2;">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="font-semibold text-gray-900 mb-1">Kalender</h3>
            <p class="text-sm text-gray-500">Lihat jadwal kalender</p>
        </a>

        <!-- Monitoring -->
        <a href="pages/monitoring.php" class="card-hover bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
            <div class="w-14 h-14 rounded-xl flex items-center justify-center mb-4" style="background:#d97706;">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
            <h3 class="font-semibold text-gray-900 mb-1">Monitoring</h3>
            <p class="text-sm text-gray-500">Realisasi pelatihan</p>
        </a>
    </div>
</div>

<?php if($canEdit): ?>
<!-- Menu Admin -->
<div class="mb-8">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Menu Admin</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="pages/pelatihan.php" class="card-hover bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#00A65120;">
                <svg class="w-6 h-6" style="color:#00A651;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 text-sm">Database Pelatihan</h3>
                <p class="text-xs text-gray-500">Master data</p>
            </div>
        </a>

        <a href="pages/laporan.php" class="card-hover bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#8b5cf620;">
                <svg class="w-6 h-6" style="color:#8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 text-sm">Laporan</h3>
                <p class="text-xs text-gray-500">Export & Cetak</p>
            </div>
        </a>

        <a href="pages/sync_gsheet.php" class="card-hover bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#dc262620;">
                <svg class="w-6 h-6" style="color:#dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 text-sm">Sync Google Sheets</h3>
                <p class="text-xs text-gray-500">Sinkronisasi data</p>
            </div>
        </a>

        <a href="pages/settings_sync.php" class="card-hover bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#6366f120;">
                <svg class="w-6 h-6" style="color:#6366f1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 text-sm">Pengaturan Sync</h3>
                <p class="text-xs text-gray-500">Atur interval</p>
            </div>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Status Jadwal -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-white">
            <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Status Jadwal Pelatihan
            </h2>
        </div>
        <div class="p-6">
            <?php 
            $statusColors = ['Not Started'=>'#6b7280','In-Progress'=>'#2563eb','Completed'=>'#059669','Cancelled'=>'#dc2626'];
            $statusLabels = ['Not Started'=>'Belum Mulai','In-Progress'=>'Sedang Berjalan','Completed'=>'Selesai','Cancelled'=>'Dibatalkan'];
            $statusIcons = [
                'Not Started' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                'In-Progress' => 'M13 10V3L4 14h7v7l9-11h-7z',
                'Completed' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                'Cancelled' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'
            ];
            foreach($statusColors as $status => $color): 
                $count = $statusData[$status] ?? 0;
                $label = $statusLabels[$status] ?? $status;
                $icon = $statusIcons[$status] ?? '';
            ?>
            <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:<?= $color ?>20;">
                        <svg class="w-5 h-5" style="color:<?= $color ?>;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-700"><?= $label ?></span>
                </div>
                <span class="text-lg font-bold px-4 py-1 rounded-lg" style="background:<?= $color ?>20; color:<?= $color ?>;"><?= $count ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="px-6 py-3 border-t border-gray-200 bg-gray-50">
            <a href="pages/jadwal.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
                Lihat semua jadwal
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    <!-- Monitoring Terbaru -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-orange-50 to-white">
            <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Monitoring Terbaru
            </h2>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if($recentMonitoring->num_rows > 0): while($m = $recentMonitoring->fetch_assoc()): ?>
            <div class="px-6 py-4 hover:bg-gray-50 transition">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#d9770620;">
                        <svg class="w-5 h-5" style="color:#d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= $m['pegawai_nama'] ?></p>
                        <p class="text-xs text-gray-500 mt-0.5 truncate"><?= $m['pelatihan_nama'] ?></p>
                        <p class="text-xs text-gray-400 mt-1">Tahun <?= $m['tahun'] ?></p>
                    </div>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="px-6 py-12 text-center">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                <p class="text-sm text-gray-500">Belum ada data monitoring</p>
            </div>
            <?php endif; ?>
        </div>
        <div class="px-6 py-3 border-t border-gray-200 bg-gray-50">
            <a href="pages/monitoring.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
                Lihat semua monitoring
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</div>

<?php if($canEdit): ?>
<script>
// Auto Sync Function (Admin Only)
function autoSync() {
    const notification = document.getElementById('autoSyncNotification');
    const message = document.getElementById('autoSyncMessage');
    const status = document.getElementById('autoSyncStatus');
    
    notification.classList.remove('hidden');
    message.textContent = 'Memeriksa data baru dari Google Sheets...';
    
    fetch('api/auto_sync.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.imported > 0) {
                    notification.classList.remove('bg-blue-50', 'border-blue-200', 'text-blue-700');
                    notification.classList.add('bg-green-50', 'border-green-200', 'text-green-700');
                    message.innerHTML = `<strong>✓ ${data.message}</strong> <a href="pages/monitoring.php" class="underline ml-2">Lihat Data →</a>`;
                    setTimeout(() => { location.reload(); }, 3000);
                } else {
                    notification.classList.add('hidden');
                }
                status.innerHTML = `<span class="text-green-600">● Sync terakhir: ${new Date().toLocaleTimeString('id-ID')}</span>`;
            } else {
                notification.classList.remove('bg-blue-50', 'border-blue-200', 'text-blue-700');
                notification.classList.add('bg-yellow-50', 'border-yellow-200', 'text-yellow-700');
                message.textContent = data.message;
                setTimeout(() => { notification.classList.add('hidden'); }, 5000);
            }
        })
        .catch(error => {
            console.error('Auto-sync error:', error);
            notification.classList.add('hidden');
        });
}

document.addEventListener('DOMContentLoaded', function() {
    autoSync();
    setInterval(autoSync, 300000);
});
</script>
<?php endif; ?>

<?php $conn->close(); require_once 'includes/footer.php'; ?>
