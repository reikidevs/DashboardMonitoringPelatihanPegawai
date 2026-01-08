<?php 
require_once '../includes/header.php';
$conn = getConnection();

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Validasi bulan
if ($bulan < 1) { $bulan = 12; $tahun--; }
if ($bulan > 12) { $bulan = 1; $tahun++; }

$namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

// Get jadwal pelatihan bulan ini
$startDate = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-01";
$endDate = date('Y-m-t', strtotime($startDate));

$jadwalBulanIni = $conn->query("
    SELECT j.*, p.nama as pelatihan_nama, p.tipe, k.nama as kategori_nama
    FROM jadwal_pelatihan j
    LEFT JOIN pelatihan p ON j.pelatihan_id = p.id
    LEFT JOIN kategori_pelatihan k ON p.kategori_id = k.id
    WHERE (j.tanggal_mulai BETWEEN '$startDate' AND '$endDate')
       OR (j.tanggal_selesai BETWEEN '$startDate' AND '$endDate')
       OR (j.tanggal_mulai <= '$startDate' AND j.tanggal_selesai >= '$endDate')
    ORDER BY j.tanggal_mulai
");

// Organize events by date
$events = [];
while ($row = $jadwalBulanIni->fetch_assoc()) {
    $start = new DateTime($row['tanggal_mulai']);
    $end = new DateTime($row['tanggal_selesai'] ?: $row['tanggal_mulai']);
    $end->modify('+1 day');
    
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);
    foreach ($period as $date) {
        $day = (int)$date->format('j');
        $month = (int)$date->format('n');
        if ($month == $bulan) {
            if (!isset($events[$day])) $events[$day] = [];
            $events[$day][] = $row;
        }
    }
}

// Get monitoring pelatihan bulan ini (realisasi)
$monitoringBulanIni = $conn->query("
    SELECT m.*, p.nama as pelatihan_nama, pg.nama as pegawai_nama
    FROM monitoring_pelatihan m
    LEFT JOIN pelatihan p ON m.pelatihan_id = p.id
    LEFT JOIN pegawai pg ON m.pegawai_id = pg.id
    WHERE m.pelaksanaan BETWEEN '$startDate' AND '$endDate'
    ORDER BY m.pelaksanaan
");

$realisasi = [];
while ($row = $monitoringBulanIni->fetch_assoc()) {
    $day = (int)date('j', strtotime($row['pelaksanaan']));
    if (!isset($realisasi[$day])) $realisasi[$day] = [];
    $realisasi[$day][] = $row;
}

// Calendar calculation
$firstDay = date('w', strtotime($startDate)); // 0=Sunday
$totalDays = date('t', strtotime($startDate));

// Upcoming events
$upcoming = $conn->query("
    SELECT j.*, p.nama as pelatihan_nama, p.tipe, k.nama as kategori_nama
    FROM jadwal_pelatihan j
    LEFT JOIN pelatihan p ON j.pelatihan_id = p.id
    LEFT JOIN kategori_pelatihan k ON p.kategori_id = k.id
    WHERE j.tanggal_mulai >= CURDATE()
    ORDER BY j.tanggal_mulai
    LIMIT 5
");
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Jadwal Pelaksanaan</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;">KALENDER PELATIHAN</h1>
    </div>
    <a href="../index.php" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Beranda
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Calendar -->
    <div class="lg:col-span-3 bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <!-- Calendar Header -->
        <div class="flex items-center justify-between px-4 py-3" style="background:#1a365d;">
            <a href="?bulan=<?= $bulan - 1 ?>&tahun=<?= $tahun ?>" class="p-2 text-white hover:bg-white/10 rounded">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-bold text-white"><?= $namaBulan[$bulan] ?> <?= $tahun ?></h2>
            <a href="?bulan=<?= $bulan + 1 ?>&tahun=<?= $tahun ?>" class="p-2 text-white hover:bg-white/10 rounded">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        
        <!-- Day Headers -->
        <div class="grid grid-cols-7 border-b border-gray-200">
            <?php foreach ($namaHari as $i => $hari): ?>
            <div class="px-2 py-2 text-center text-xs font-semibold <?= $i == 0 ? 'text-red-500' : 'text-gray-600' ?>" style="background:#f1f5f9;">
                <?= $hari ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Calendar Grid -->
        <div class="grid grid-cols-7">
            <?php
            $dayCount = 1;
            $totalCells = ceil(($firstDay + $totalDays) / 7) * 7;
            
            for ($i = 0; $i < $totalCells; $i++):
                $isCurrentMonth = ($i >= $firstDay && $dayCount <= $totalDays);
                $day = $isCurrentMonth ? $dayCount : '';
                $isToday = $isCurrentMonth && $day == date('j') && $bulan == date('n') && $tahun == date('Y');
                $isSunday = $i % 7 == 0;
                $hasEvent = isset($events[$day]) && count($events[$day]) > 0;
                $hasRealisasi = isset($realisasi[$day]) && count($realisasi[$day]) > 0;
                
                if ($isCurrentMonth) $dayCount++;
            ?>
            <div class="min-h-24 border-b border-r border-gray-200 p-1 <?= !$isCurrentMonth ? 'bg-gray-50' : '' ?> <?= $isToday ? 'bg-blue-50' : '' ?>">
                <?php if ($isCurrentMonth): ?>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium <?= $isSunday ? 'text-red-500' : 'text-gray-700' ?> <?= $isToday ? 'bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center' : '' ?>">
                        <?= $day ?>
                    </span>
                </div>
                
                <!-- Events -->
                <?php if ($hasEvent): ?>
                    <?php foreach (array_slice($events[$day], 0, 2) as $event): 
                        $statusColors = ['Not Started'=>'#6b7280','In-Progress'=>'#2563eb','Completed'=>'#059669','Cancelled'=>'#dc2626'];
                        $color = $statusColors[$event['status']] ?? '#6b7280';
                    ?>
                    <div class="text-xs px-1 py-0.5 mb-0.5 rounded truncate cursor-pointer hover:opacity-80" 
                         style="background:<?= $color ?>20; color:<?= $color ?>; border-left:2px solid <?= $color ?>;"
                         title="<?= htmlspecialchars($event['pelatihan_nama']) ?> (<?= $event['status'] ?>)">
                        <?= htmlspecialchars(mb_substr($event['pelatihan_nama'], 0, 15)) ?>...
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($events[$day]) > 2): ?>
                    <div class="text-xs text-gray-500 px-1">+<?= count($events[$day]) - 2 ?> lainnya</div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Realisasi -->
                <?php if ($hasRealisasi): ?>
                    <?php foreach (array_slice($realisasi[$day], 0, 1) as $r): ?>
                    <div class="text-xs px-1 py-0.5 mb-0.5 rounded truncate" 
                         style="background:#05966920; color:#059669;"
                         title="Realisasi: <?= htmlspecialchars($r['pegawai_nama']) ?> - <?= htmlspecialchars($r['pelatihan_nama']) ?>">
                        <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <?= htmlspecialchars(mb_substr($r['pegawai_nama'], 0, 10)) ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($realisasi[$day]) > 1): ?>
                    <div class="text-xs text-green-600 px-1">+<?= count($realisasi[$day]) - 1 ?> realisasi</div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
        
        <!-- Legend -->
        <div class="px-4 py-2 border-t border-gray-200 flex items-center gap-4 text-xs" style="background:#f8fafc;">
            <span class="flex items-center gap-1">
                <span class="w-3 h-3 rounded" style="background:#2563eb20; border-left:2px solid #2563eb;"></span>
                Sedang Berjalan
            </span>
            <span class="flex items-center gap-1">
                <span class="w-3 h-3 rounded" style="background:#05966920; border-left:2px solid #059669;"></span>
                Selesai
            </span>
            <span class="flex items-center gap-1">
                <span class="w-3 h-3 rounded" style="background:#6b728020; border-left:2px solid #6b7280;"></span>
                Belum Mulai
            </span>
            <span class="flex items-center gap-1">
                <span class="w-3 h-3 rounded" style="background:#05966920;"></span>
                <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Realisasi
            </span>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="space-y-4">
        <!-- Quick Navigation -->
        <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
                <h3 class="font-semibold text-white text-sm">Navigasi Cepat</h3>
            </div>
            <div class="p-3 space-y-2">
                <a href="?bulan=<?= date('n') ?>&tahun=<?= date('Y') ?>" class="flex items-center gap-2 px-3 py-2 text-sm rounded hover:bg-gray-100 text-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Bulan Ini
                </a>
                <a href="jadwal.php" class="flex items-center gap-2 px-3 py-2 text-sm rounded hover:bg-gray-100 text-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    Kelola Jadwal
                </a>
                <a href="jadwal.php?action=add" class="block px-3 py-2 text-sm rounded text-white" style="background:#00A651;">
                    + Tambah Jadwal
                </a>
            </div>
        </div>
        
        <!-- Upcoming Events -->
        <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
                <h3 class="font-semibold text-white text-sm">Pelatihan Mendatang</h3>
            </div>
            <div class="divide-y divide-gray-100">
                <?php if ($upcoming->num_rows > 0): while ($u = $upcoming->fetch_assoc()): ?>
                <div class="px-3 py-2 hover:bg-gray-50">
                    <p class="text-sm font-medium text-gray-800 truncate"><?= $u['pelatihan_nama'] ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <?= date('d M Y', strtotime($u['tanggal_mulai'])) ?>
                        <?php if ($u['tanggal_selesai'] && $u['tanggal_selesai'] != $u['tanggal_mulai']): ?>
                        - <?= date('d M Y', strtotime($u['tanggal_selesai'])) ?>
                        <?php endif; ?>
                    </p>
                    <?php 
                    $statusLabels = ['Not Started'=>'Belum Mulai','In-Progress'=>'Sedang Berjalan','Completed'=>'Selesai','Cancelled'=>'Dibatalkan'];
                    $statusLabel = $statusLabels[$u['status']] ?? $u['status'];
                    ?>
                    <span class="text-xs px-1.5 py-0.5 rounded mt-1 inline-block" style="background:#<?= $u['status'] == 'In-Progress' ? '2563eb' : ($u['status'] == 'Completed' ? '059669' : '6b7280') ?>20; color:#<?= $u['status'] == 'In-Progress' ? '2563eb' : ($u['status'] == 'Completed' ? '059669' : '6b7280') ?>;">
                        <?= $statusLabel ?>
                    </span>
                </div>
                <?php endwhile; else: ?>
                <div class="px-3 py-4 text-center text-gray-500 text-sm">
                    Tidak ada jadwal mendatang
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Stats Bulan Ini -->
        <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
                <h3 class="font-semibold text-white text-sm">Statistik <?= $namaBulan[$bulan] ?></h3>
            </div>
            <div class="p-3 space-y-2">
                <?php
                $statsJadwal = $conn->query("SELECT COUNT(*) as c FROM jadwal_pelatihan WHERE tanggal_mulai BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['c'];
                $statsRealisasi = $conn->query("SELECT COUNT(*) as c FROM monitoring_pelatihan WHERE pelaksanaan BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['c'];
                $statsPeserta = $conn->query("SELECT COUNT(DISTINCT pegawai_id) as c FROM monitoring_pelatihan WHERE pelaksanaan BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['c'];
                ?>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">Jadwal Pelatihan</span>
                    <span class="font-semibold" style="color:#005BAC;"><?= $statsJadwal ?></span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">Realisasi</span>
                    <span class="font-semibold" style="color:#059669;"><?= $statsRealisasi ?></span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">Peserta Aktif</span>
                    <span class="font-semibold" style="color:#7c3aed;"><?= $statsPeserta ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
