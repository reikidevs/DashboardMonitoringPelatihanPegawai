<?php 
require_once '../includes/header.php';
$conn = getConnection();

// Check if table exists
$conn->query("CREATE TABLE IF NOT EXISTS kewajiban_pelatihan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pegawai_id INT NOT NULL,
    pelatihan_id INT NOT NULL,
    tahun_target YEAR,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_kewajiban (pegawai_id, pelatihan_id)
)");

$filterYear = $_GET['year'] ?? date('Y');
$filterKategori = $_GET['kategori'] ?? '';

// Get all pegawai with their training stats
$pegawaiStats = $conn->query("
    SELECT 
        pg.id, pg.nama, pg.nip, pg.jabatan,
        (SELECT COUNT(*) FROM kewajiban_pelatihan kp WHERE kp.pegawai_id = pg.id) as total_kewajiban,
        (SELECT COUNT(*) FROM kewajiban_pelatihan kp 
            INNER JOIN monitoring_pelatihan m ON kp.pegawai_id = m.pegawai_id AND kp.pelatihan_id = m.pelatihan_id
            WHERE kp.pegawai_id = pg.id " . ($filterYear ? "AND m.tahun = $filterYear" : "") . "
        ) as kewajiban_selesai,
        (SELECT COUNT(DISTINCT m.pelatihan_id) FROM monitoring_pelatihan m WHERE m.pegawai_id = pg.id " . ($filterYear ? "AND m.tahun = $filterYear" : "") . ") as total_pelatihan,
        (SELECT COALESCE(SUM(m.jumlah_jp), 0) FROM monitoring_pelatihan m WHERE m.pegawai_id = pg.id " . ($filterYear ? "AND m.tahun = $filterYear" : "") . ") as total_jp
    FROM pegawai pg
    ORDER BY pg.nama
");

// Stats per kategori
$kategoriStats = $conn->query("
    SELECT 
        k.id, k.nama,
        COUNT(DISTINCT m.pegawai_id) as pegawai_ikut,
        COUNT(m.id) as total_partisipasi,
        COALESCE(SUM(m.jumlah_jp), 0) as total_jp
    FROM kategori_pelatihan k
    LEFT JOIN pelatihan p ON k.id = p.kategori_id
    LEFT JOIN monitoring_pelatihan m ON p.id = m.pelatihan_id " . ($filterYear ? "AND m.tahun = $filterYear" : "") . "
    GROUP BY k.id, k.nama
    ORDER BY k.id
");

// Overall stats
$totalPegawai = $conn->query("SELECT COUNT(*) as c FROM pegawai")->fetch_assoc()['c'];
$totalPelatihanDiikuti = $conn->query("SELECT COUNT(*) as c FROM monitoring_pelatihan " . ($filterYear ? "WHERE tahun = $filterYear" : ""))->fetch_assoc()['c'];
$totalJP = $conn->query("SELECT COALESCE(SUM(jumlah_jp), 0) as c FROM monitoring_pelatihan " . ($filterYear ? "WHERE tahun = $filterYear" : ""))->fetch_assoc()['c'];
$pegawaiDenganPelatihan = $conn->query("SELECT COUNT(DISTINCT pegawai_id) as c FROM monitoring_pelatihan " . ($filterYear ? "WHERE tahun = $filterYear" : ""))->fetch_assoc()['c'];

$years = $conn->query("SELECT DISTINCT tahun FROM monitoring_pelatihan ORDER BY tahun DESC");
$kategoriList = $conn->query("SELECT * FROM kategori_pelatihan ORDER BY id");
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Laporan Monitoring Pelatihan</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;">REKAP PELATIHAN PEGAWAI <?= $filterYear ?></h1>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
            <select name="year" onchange="this.form.submit()" class="px-3 py-1.5 text-sm border border-gray-300 rounded">
                <option value="">Semua Tahun</option>
                <?php $years->data_seek(0); while($y = $years->fetch_assoc()): ?>
                <option value="<?= $y['tahun'] ?>" <?= $filterYear == $y['tahun'] ? 'selected' : '' ?>><?= $y['tahun'] ?></option>
                <?php endwhile; ?>
                <?php if($filterYear == date('Y')): ?>
                <option value="<?= date('Y') ?>" selected><?= date('Y') ?></option>
                <?php endif; ?>
            </select>
        </form>
        <a href="../api/export.php?year=<?= $filterYear ?>" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-white rounded" style="background:#059669;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export Excel
        </a>
        <a href="../" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Beranda
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#005BAC20;">
                <svg class="w-5 h-5" style="color:#005BAC;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold" style="color:#005BAC;"><?= $pegawaiDenganPelatihan ?>/<?= $totalPegawai ?></p>
                <p class="text-xs text-gray-500">Pegawai Ikut Pelatihan</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#00A65120;">
                <svg class="w-5 h-5" style="color:#00A651;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold" style="color:#00A651;"><?= $totalPelatihanDiikuti ?></p>
                <p class="text-xs text-gray-500">Total Partisipasi</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#dc262620;">
                <svg class="w-5 h-5" style="color:#dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold" style="color:#dc2626;"><?= $totalJP ?></p>
                <p class="text-xs text-gray-500">Total JP</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background: conic-gradient(#059669 <?= $totalPegawai > 0 ? round(($pegawaiDenganPelatihan / $totalPegawai) * 100) : 0 ?>%, #e5e7eb <?= $totalPegawai > 0 ? round(($pegawaiDenganPelatihan / $totalPegawai) * 100) : 0 ?>%);">
                <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center">
                    <span class="text-sm font-bold" style="color:#059669;"><?= $totalPegawai > 0 ? round(($pegawaiDenganPelatihan / $totalPegawai) * 100) : 0 ?>%</span>
                </div>
            </div>
            <div>
                <p class="text-lg font-bold" style="color:#059669;">Partisipasi</p>
                <p class="text-xs text-gray-500">Partisipasi</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Stats per Kategori -->
    <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
            <h2 class="font-semibold text-white text-sm">Partisipasi per Kategori</h2>
        </div>
        <div class="p-4">
            <?php 
            $katColors = ['Mutlak'=>'#dc2626','Penting'=>'#d97706','Perlu'=>'#2563eb','Pelatihan IDEAS'=>'#7c3aed'];
            while($k = $kategoriStats->fetch_assoc()): 
                $color = $katColors[$k['nama']] ?? '#6b7280';
                $persen = $totalPegawai > 0 ? round(($k['pegawai_ikut'] / $totalPegawai) * 100) : 0;
            ?>
            <div class="mb-3 last:mb-0">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm text-gray-700"><?= $k['nama'] ?></span>
                    <span class="text-xs font-medium" style="color:<?= $color ?>;"><?= $k['pegawai_ikut'] ?> pegawai</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="h-2 rounded-full" style="width:<?= $persen ?>%; background:<?= $color ?>;"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1"><?= $k['total_partisipasi'] ?> partisipasi, <?= $k['total_jp'] ?> JP</p>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Rekap per Pegawai -->
    <div class="lg:col-span-2 bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
            <h2 class="font-semibold text-white text-sm">Rekap per Pegawai</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background:#f1f5f9;">
                        <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b border-gray-200">Nama</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b border-gray-200 w-24">Kewajiban</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b border-gray-200 w-24">Pelatihan</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b border-gray-200 w-16">JP</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b border-gray-200 w-24">Progres</th>
                    </tr>
                </thead>
                <tbody>
<?php $no = 1; while($row = $pegawaiStats->fetch_assoc()): 
    $bgColor = $no % 2 == 0 ? '#f8fafc' : '#ffffff';
    $persen = $row['total_kewajiban'] > 0 ? round(($row['kewajiban_selesai'] / $row['total_kewajiban']) * 100) : ($row['total_pelatihan'] > 0 ? 100 : 0);
    $progressColor = $persen >= 80 ? '#059669' : ($persen >= 50 ? '#d97706' : '#dc2626');
    $no++;
?>
                    <tr style="background:<?= $bgColor ?>;" class="hover:bg-blue-50 border-b border-gray-100">
                        <td class="px-3 py-2">
                            <a href="pegawai_detail.php?id=<?= $row['id'] ?>&year=<?= $filterYear ?>" class="font-medium text-blue-600 hover:underline"><?= $row['nama'] ?></a>
                            <p class="text-xs text-gray-500"><?= $row['jabatan'] ?: '-' ?></p>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <?php if($row['total_kewajiban'] > 0): ?>
                            <span class="text-sm font-medium <?= $row['kewajiban_selesai'] == $row['total_kewajiban'] ? 'text-green-600' : 'text-orange-600' ?>">
                                <?= $row['kewajiban_selesai'] ?>/<?= $row['total_kewajiban'] ?>
                            </span>
                            <?php else: ?>
                            <span class="text-xs text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-center font-medium" style="color:#005BAC;"><?= $row['total_pelatihan'] ?></td>
                        <td class="px-3 py-2 text-center font-medium" style="color:#dc2626;"><?= $row['total_jp'] ?></td>
                        <td class="px-3 py-2 text-center">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full" style="width:<?= $persen ?>%; background:<?= $progressColor ?>;"></div>
                                </div>
                                <span class="text-xs font-medium" style="color:<?= $progressColor ?>;"><?= $persen ?>%</span>
                            </div>
                        </td>
                    </tr>
<?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
