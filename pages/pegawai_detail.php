<?php 
require_once '../includes/header.php';
$conn = getConnection();

$pegawai_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$pegawai_id) { redirect('pegawai.php'); }

$pegawai = $conn->query("SELECT * FROM pegawai WHERE id = $pegawai_id")->fetch_assoc();
if (!$pegawai) { redirect('pegawai.php'); }

$filterYear = $_GET['year'] ?? date('Y');

// Get all pelatihan yang sudah diikuti pegawai ini
$pelatihanDiikuti = $conn->query("
    SELECT m.*, p.nama as pelatihan_nama, p.jumlah_jp as jp_pelatihan,
           k.nama as kategori_nama, l.nama as lingkup_nama
    FROM monitoring_pelatihan m
    LEFT JOIN pelatihan p ON m.pelatihan_id = p.id
    LEFT JOIN kategori_pelatihan k ON p.kategori_id = k.id
    LEFT JOIN lingkup_pelatihan l ON p.lingkup_id = l.id
    WHERE m.pegawai_id = $pegawai_id " . ($filterYear ? "AND m.tahun = $filterYear" : "") . "
    ORDER BY m.tahun DESC, p.nama
");

// Hitung statistik per kategori
$statsByKategori = $conn->query("
    SELECT k.id, k.nama, 
           COUNT(DISTINCT m.pelatihan_id) as jumlah_diikuti,
           COALESCE(SUM(m.jumlah_jp), 0) as total_jp
    FROM kategori_pelatihan k
    LEFT JOIN pelatihan p ON k.id = p.kategori_id
    LEFT JOIN monitoring_pelatihan m ON p.id = m.pelatihan_id AND m.pegawai_id = $pegawai_id " . ($filterYear ? "AND m.tahun = $filterYear" : "") . "
    GROUP BY k.id, k.nama
    ORDER BY k.id
");

// Total pelatihan per kategori (untuk persentase)
$totalPerKategori = [];
$result = $conn->query("SELECT k.nama, COUNT(p.id) as total FROM kategori_pelatihan k LEFT JOIN pelatihan p ON k.id = p.kategori_id GROUP BY k.id");
while($r = $result->fetch_assoc()) { $totalPerKategori[$r['nama']] = $r['total']; }

// Get kewajiban pelatihan pegawai ini
$kewajiban = $conn->query("
    SELECT kp.*, p.nama as pelatihan_nama, k.nama as kategori_nama,
           (SELECT COUNT(*) FROM monitoring_pelatihan m WHERE m.pegawai_id = $pegawai_id AND m.pelatihan_id = kp.pelatihan_id " . ($filterYear ? "AND m.tahun = $filterYear" : "") . ") as sudah_ikut
    FROM kewajiban_pelatihan kp
    LEFT JOIN pelatihan p ON kp.pelatihan_id = p.id
    LEFT JOIN kategori_pelatihan k ON p.kategori_id = k.id
    WHERE kp.pegawai_id = $pegawai_id
    ORDER BY k.nama, p.nama
");

// Hitung persentase kewajiban
$totalKewajiban = 0;
$kewajibanSelesai = 0;
$kewajibanData = [];
if ($kewajiban && $kewajiban->num_rows > 0) {
    while($kw = $kewajiban->fetch_assoc()) {
        $kewajibanData[] = $kw;
        $totalKewajiban++;
        if ($kw['sudah_ikut'] > 0) $kewajibanSelesai++;
    }
}
$persentaseKewajiban = $totalKewajiban > 0 ? round(($kewajibanSelesai / $totalKewajiban) * 100) : 0;

// Total JP
$totalJP = $conn->query("SELECT COALESCE(SUM(jumlah_jp), 0) as total FROM monitoring_pelatihan WHERE pegawai_id = $pegawai_id " . ($filterYear ? "AND tahun = $filterYear" : ""))->fetch_assoc()['total'];
$totalPelatihan = $pelatihanDiikuti->num_rows;

// Years for filter
$years = $conn->query("SELECT DISTINCT tahun FROM monitoring_pelatihan WHERE pegawai_id = $pegawai_id ORDER BY tahun DESC");
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Detail Pelatihan Pegawai</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;"><?= strtoupper($pegawai['nama']) ?></h1>
        <p class="text-sm text-gray-500 mt-1">NIP: <?= $pegawai['nip'] ?> | <?= $pegawai['jabatan'] ?: '-' ?></p>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="id" value="<?= $pegawai_id ?>">
            <select name="year" onchange="this.form.submit()" class="px-3 py-1.5 text-sm border border-gray-300 rounded">
                <option value="">Semua Tahun</option>
                <?php $years->data_seek(0); while($y = $years->fetch_assoc()): ?>
                <option value="<?= $y['tahun'] ?>" <?= $filterYear == $y['tahun'] ? 'selected' : '' ?>><?= $y['tahun'] ?></option>
                <?php endwhile; ?>
                <?php if(!$filterYear || !in_array($filterYear, array_column(iterator_to_array($years), 'tahun'))): ?>
                <option value="<?= date('Y') ?>" <?= $filterYear == date('Y') ? 'selected' : '' ?>><?= date('Y') ?></option>
                <?php endif; ?>
            </select>
        </form>
        <a href="pegawai.php" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background: conic-gradient(#059669 <?= $persentaseKewajiban ?>%, #e5e7eb <?= $persentaseKewajiban ?>%);">
                <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center">
                    <span class="text-sm font-bold" style="color:#059669;"><?= $persentaseKewajiban ?>%</span>
                </div>
            </div>
            <div>
                <p class="text-2xl font-bold" style="color:#059669;"><?= $kewajibanSelesai ?>/<?= $totalKewajiban ?></p>
                <p class="text-xs text-gray-500">Kewajiban Selesai</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#005BAC20;">
                <svg class="w-5 h-5" style="color:#005BAC;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold" style="color:#005BAC;"><?= $totalPelatihan ?></p>
                <p class="text-xs text-gray-500">Pelatihan Diikuti</p>
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
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#7c3aed20;">
                <svg class="w-5 h-5" style="color:#7c3aed;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold" style="color:#7c3aed;"><?= $conn->query("SELECT COUNT(*) as c FROM monitoring_pelatihan WHERE pegawai_id = $pegawai_id AND no_sertifikat IS NOT NULL AND no_sertifikat != ''" . ($filterYear ? " AND tahun = $filterYear" : ""))->fetch_assoc()['c'] ?></p>
                <p class="text-xs text-gray-500">Sertifikat</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Statistik per Kategori -->
    <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
            <h2 class="font-semibold text-white text-sm">Pelatihan per Kategori</h2>
        </div>
        <div class="p-4">
            <?php 
            $katColors = ['Mutlak'=>'#dc2626','Penting'=>'#d97706','Perlu'=>'#2563eb','Pelatihan IDEAS'=>'#7c3aed'];
            $statsByKategori->data_seek(0);
            while($s = $statsByKategori->fetch_assoc()): 
                $color = $katColors[$s['nama']] ?? '#6b7280';
                $total = $totalPerKategori[$s['nama']] ?? 0;
                $persen = $total > 0 ? round(($s['jumlah_diikuti'] / $total) * 100) : 0;
            ?>
            <div class="mb-3 last:mb-0">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm text-gray-700"><?= $s['nama'] ?></span>
                    <span class="text-xs font-medium" style="color:<?= $color ?>;"><?= $s['jumlah_diikuti'] ?>/<?= $total ?> (<?= $persen ?>%)</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="h-2 rounded-full" style="width:<?= $persen ?>%; background:<?= $color ?>;"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1"><?= $s['total_jp'] ?> JP</p>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Kewajiban Pelatihan -->
    <div class="lg:col-span-2 bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between" style="background:#1a365d;">
            <h2 class="font-semibold text-white text-sm">Kewajiban Pelatihan</h2>
            <a href="kewajiban.php?pegawai_id=<?= $pegawai_id ?>" class="text-xs text-blue-200 hover:text-white">Kelola →</a>
        </div>
        <?php if(count($kewajibanData) > 0): ?>
        <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
            <?php foreach($kewajibanData as $kw): 
                $isDone = $kw['sudah_ikut'] > 0;
            ?>
            <div class="px-4 py-2 flex items-center justify-between hover:bg-gray-50">
                <div class="flex items-center gap-3">
                    <?php if($isDone): ?>
                    <span class="w-6 h-6 rounded-full flex items-center justify-center" style="background:#05966920;">
                        <svg class="w-4 h-4" style="color:#059669;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <?php else: ?>
                    <span class="w-6 h-6 rounded-full flex items-center justify-center" style="background:#dc262620;">
                        <svg class="w-4 h-4" style="color:#dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <?php endif; ?>
                    <div>
                        <p class="text-sm font-medium text-gray-800 <?= $isDone ? 'line-through text-gray-400' : '' ?>"><?= $kw['pelatihan_nama'] ?></p>
                        <p class="text-xs text-gray-500"><?= $kw['kategori_nama'] ?></p>
                    </div>
                </div>
                <span class="text-xs px-2 py-0.5 rounded <?= $isDone ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= $isDone ? 'Selesai' : 'Belum' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="px-4 py-8 text-center text-gray-500 text-sm">
            <p>Belum ada kewajiban pelatihan yang ditetapkan</p>
            <a href="kewajiban.php?pegawai_id=<?= $pegawai_id ?>" class="text-blue-600 hover:underline text-xs mt-1 inline-block">+ Tambah Kewajiban</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Daftar Pelatihan yang Diikuti -->
<div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
        <h2 class="font-semibold text-white text-sm">Riwayat Pelatihan <?= $filterYear ? "Tahun $filterYear" : "" ?></h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr style="background:#f1f5f9;">
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b border-gray-200 w-10">No</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b border-gray-200">Nama Pelatihan</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b border-gray-200 w-28">Kategori</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b border-gray-200 w-28">Lingkup</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b border-gray-200 w-16">Tahun</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b border-gray-200 w-28">Pelaksanaan</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b border-gray-200 w-14">JP</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 border-b border-gray-200 w-24">Sertifikat</th>
                </tr>
            </thead>
            <tbody>
<?php if($pelatihanDiikuti->num_rows > 0): $no = 1; while($row = $pelatihanDiikuti->fetch_assoc()): 
    $bgColor = $no % 2 == 0 ? '#f8fafc' : '#ffffff';
    $katColors = ['Mutlak'=>'#dc2626','Penting'=>'#d97706','Perlu'=>'#2563eb','Pelatihan IDEAS'=>'#7c3aed'];
    $katColor = $katColors[$row['kategori_nama']] ?? '#6b7280';
?>
                <tr style="background:<?= $bgColor ?>;" class="hover:bg-blue-50 border-b border-gray-100">
                    <td class="px-3 py-2 text-gray-500"><?= $no++ ?></td>
                    <td class="px-3 py-2 font-medium text-gray-800"><?= $row['pelatihan_nama'] ?></td>
                    <td class="px-3 py-2">
                        <span class="text-xs px-2 py-0.5 rounded" style="background:<?= $katColor ?>20; color:<?= $katColor ?>;"><?= $row['kategori_nama'] ?? '-' ?></span>
                    </td>
                    <td class="px-3 py-2 text-gray-600"><?= $row['lingkup_nama'] ?? '-' ?></td>
                    <td class="px-3 py-2 text-center font-medium" style="color:#005BAC;"><?= $row['tahun'] ?></td>
                    <td class="px-3 py-2 text-center text-gray-600"><?= $row['pelaksanaan'] ? date('d/m/Y', strtotime($row['pelaksanaan'])) : '-' ?></td>
                    <td class="px-3 py-2 text-center font-medium" style="color:#005BAC;"><?= $row['jumlah_jp'] ?></td>
                    <td class="px-3 py-2 text-center">
                        <?php if($row['no_sertifikat']): ?>
                        <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700" title="<?= $row['no_sertifikat'] ?>">Ada</span>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
<?php endwhile; else: ?>
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        <p>Belum ada data pelatihan</p>
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
