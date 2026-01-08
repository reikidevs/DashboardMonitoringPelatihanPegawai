<?php 
require_once '../includes/header.php';
$conn = getConnection();

$filterYear = $_GET['year'] ?? date('Y');
$filterPegawai = $_GET['pegawai'] ?? '';

// Get pegawai list for filter
$pegawaiList = $conn->query("SELECT id, nama FROM pegawai ORDER BY nama");

// Get years from jadwal
$years = $conn->query("SELECT DISTINCT YEAR(tanggal_mulai) as tahun FROM jadwal_pelatihan 
    UNION SELECT DISTINCT tahun FROM monitoring_pelatihan ORDER BY tahun DESC");

// Build where clause
$whereJadwal = "WHERE YEAR(j.tanggal_mulai) = $filterYear";
$whereMonitoring = "WHERE m.tahun = $filterYear";
if ($filterPegawai) {
    $whereJadwal .= " AND j.id IN (SELECT jadwal_id FROM jadwal_peserta WHERE pegawai_id = $filterPegawai)";
    $whereMonitoring .= " AND m.pegawai_id = $filterPegawai";
}

// Get Jadwal (Rencana) data
$jadwalData = $conn->query("SELECT j.*, p.nama as pelatihan_nama, k.nama as kategori_nama,
    (SELECT GROUP_CONCAT(pg.nama SEPARATOR ', ') FROM jadwal_peserta jp 
     LEFT JOIN pegawai pg ON jp.pegawai_id = pg.id WHERE jp.jadwal_id = j.id) as peserta
    FROM jadwal_pelatihan j
    LEFT JOIN pelatihan p ON j.pelatihan_id = p.id
    LEFT JOIN kategori_pelatihan k ON p.kategori_id = k.id
    $whereJadwal ORDER BY j.tanggal_mulai");

// Get Monitoring (Realisasi) data
$monitoringData = $conn->query("SELECT m.*, pg.nama as pegawai_nama, p.nama as pelatihan_nama, 
    k.nama as kategori_nama
    FROM monitoring_pelatihan m
    LEFT JOIN pegawai pg ON m.pegawai_id = pg.id
    LEFT JOIN pelatihan p ON m.pelatihan_id = p.id
    LEFT JOIN kategori_pelatihan k ON p.kategori_id = k.id
    $whereMonitoring ORDER BY m.pelaksanaan, pg.nama");

// Summary stats
$totalRencana = $jadwalData->num_rows;
$totalRealisasi = $monitoringData->num_rows;

// Count by status
$statusCount = $conn->query("SELECT status, COUNT(*) as total FROM jadwal_pelatihan 
    WHERE YEAR(tanggal_mulai) = $filterYear GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$statusMap = [];
foreach($statusCount as $s) { $statusMap[$s['status']] = $s['total']; }
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Perbandingan Rencana vs Realisasi</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;">RENCANA VS REALISASI</h1>
    </div>
    <a href="../" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        KEMBALI
    </a>
</div>

<!-- Filter -->
<div class="bg-white rounded-lg border border-gray-300 shadow-sm mb-6">
    <form method="GET" class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tahun</label>
                <select name="year" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <?php $years->data_seek(0); while($y = $years->fetch_assoc()): ?>
                    <option value="<?= $y['tahun'] ?>" <?= $filterYear == $y['tahun'] ? 'selected' : '' ?>><?= $y['tahun'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Pegawai</label>
                <select name="pegawai" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <option value="">-- Semua Pegawai --</option>
                    <?php while($p = $pegawaiList->fetch_assoc()): ?>
                    <option value="<?= $p['id'] ?>" <?= $filterPegawai == $p['id'] ? 'selected' : '' ?>><?= $p['nama'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="px-4 py-2 text-sm text-white rounded hover:opacity-90" style="background:#005BAC;">Filter</button>
                <a href="realisasi.php" class="px-4 py-2 text-sm border border-gray-300 rounded hover:bg-gray-50 text-gray-600">Reset</a>
            </div>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#7c3aed20;">
                <svg class="w-5 h-5" style="color:#7c3aed;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold" style="color:#7c3aed;"><?= $totalRencana ?></p>
                <p class="text-xs text-gray-500">Total Rencana</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#05966920;">
                <svg class="w-5 h-5" style="color:#059669;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold" style="color:#059669;"><?= $statusMap['Completed'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Selesai</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#2563eb20;">
                <svg class="w-5 h-5" style="color:#2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold" style="color:#2563eb;"><?= $statusMap['In-Progress'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Sedang Berjalan</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#d9770620;">
                <svg class="w-5 h-5" style="color:#d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold" style="color:#d97706;"><?= $totalRealisasi ?></p>
                <p class="text-xs text-gray-500">Total Realisasi</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Rencana (Jadwal) -->
    <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200" style="background:#7c3aed;">
            <h2 class="font-semibold text-white text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Rencana Pelatihan <?= $filterYear ?>
            </h2>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-sm">
                <thead class="sticky top-0">
                    <tr style="background:#f8fafc;">
                        <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">Pelatihan</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b w-28">Tanggal</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b w-24">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($jadwalData->num_rows > 0): $jadwalData->data_seek(0); while($j = $jadwalData->fetch_assoc()): 
                        $statusColors = ['Not Started'=>'#6b7280','In-Progress'=>'#2563eb','Completed'=>'#059669','Cancelled'=>'#dc2626'];
                        $statusLabels = ['Not Started'=>'Belum','In-Progress'=>'Berjalan','Completed'=>'Selesai','Cancelled'=>'Batal'];
                        $color = $statusColors[$j['status']] ?? '#6b7280';
                        $label = $statusLabels[$j['status']] ?? $j['status'];
                    ?>
                    <tr class="hover:bg-gray-50 border-b border-gray-100">
                        <td class="px-3 py-2">
                            <p class="font-medium text-gray-800"><?= $j['pelatihan_nama'] ?></p>
                            <?php if($j['peserta']): ?>
                            <p class="text-xs text-gray-500 mt-0.5"><?= $j['peserta'] ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-center text-xs text-gray-600">
                            <?= date('d/m/Y', strtotime($j['tanggal_mulai'])) ?>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <span class="text-xs px-2 py-0.5 rounded" style="background:<?= $color ?>20; color:<?= $color ?>;"><?= $label ?></span>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">Tidak ada data rencana</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 border-t border-gray-200 text-xs text-gray-500" style="background:#f8fafc;">
            <a href="jadwal.php" class="hover:underline" style="color:#7c3aed;">Kelola Jadwal →</a>
        </div>
    </div>
    
    <!-- Realisasi (Monitoring) -->
    <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200" style="background:#059669;">
            <h2 class="font-semibold text-white text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Realisasi Pelatihan <?= $filterYear ?>
            </h2>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-sm">
                <thead class="sticky top-0">
                    <tr style="background:#f8fafc;">
                        <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">Pegawai</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">Pelatihan</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b w-28">Pelaksanaan</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b w-20">Sertifikat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($monitoringData->num_rows > 0): $monitoringData->data_seek(0); while($m = $monitoringData->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 border-b border-gray-100">
                        <td class="px-3 py-2 font-medium text-gray-800"><?= $m['pegawai_nama'] ?></td>
                        <td class="px-3 py-2 text-gray-700"><?= $m['pelatihan_nama'] ?></td>
                        <td class="px-3 py-2 text-center text-xs text-gray-600">
                            <?= $m['pelaksanaan'] ? date('d/m/Y', strtotime($m['pelaksanaan'])) : '-' ?>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <?php if($m['no_sertifikat']): 
                                if(strpos($m['no_sertifikat'], 'http') !== false):
                                    preg_match('/(https?:\/\/[^\s]+)/', $m['no_sertifikat'], $matches);
                                    $link = $matches[1] ?? '';
                            ?>
                                <a href="<?= $link ?>" target="_blank" class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded hover:opacity-80" style="background:#05966920; color:#059669;">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    Lihat
                                </a>
                                <?php else: ?>
                                <span class="text-xs px-2 py-0.5 rounded" style="background:#05966920; color:#059669;" title="<?= htmlspecialchars($m['no_sertifikat']) ?>">Ada</span>
                                <?php endif; ?>
                            <?php else: ?>
                            <span class="text-xs text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Tidak ada data realisasi</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 border-t border-gray-200 text-xs text-gray-500" style="background:#f8fafc;">
            <a href="monitoring.php" class="hover:underline" style="color:#059669;">Kelola Monitoring →</a>
        </div>
    </div>
</div>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
