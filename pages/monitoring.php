<?php 
require_once '../includes/header.php';
$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pegawai_id = (int)$_POST['pegawai_id'];
        $pelatihan_id = (int)$_POST['pelatihan_id'];
        $tahun = (int)$_POST['tahun'];
        $pelaksanaan = $_POST['pelaksanaan'];
        $no_sertifikat = sanitize($_POST['no_sertifikat']);
        $jumlah_jp = (int)$_POST['jumlah_jp'];
        $stmt = $conn->prepare("INSERT INTO monitoring_pelatihan (pegawai_id, pelatihan_id, tahun, pelaksanaan, no_sertifikat, jumlah_jp) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissi", $pegawai_id, $pelatihan_id, $tahun, $pelaksanaan, $no_sertifikat, $jumlah_jp);
        if ($stmt->execute()) { alert('Data monitoring berhasil ditambahkan!'); }
        else { alert('Gagal menambahkan data', 'danger'); }
        redirect('monitoring.php');
    }
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $pegawai_id = (int)$_POST['pegawai_id'];
        $pelatihan_id = (int)$_POST['pelatihan_id'];
        $tahun = (int)$_POST['tahun'];
        $pelaksanaan = $_POST['pelaksanaan'];
        $no_sertifikat = sanitize($_POST['no_sertifikat']);
        $jumlah_jp = (int)$_POST['jumlah_jp'];
        $stmt = $conn->prepare("UPDATE monitoring_pelatihan SET pegawai_id=?, pelatihan_id=?, tahun=?, pelaksanaan=?, no_sertifikat=?, jumlah_jp=? WHERE id=?");
        $stmt->bind_param("iiissii", $pegawai_id, $pelatihan_id, $tahun, $pelaksanaan, $no_sertifikat, $jumlah_jp, $id);
        if ($stmt->execute()) { alert('Data monitoring berhasil diupdate!'); }
        else { alert('Gagal mengupdate data', 'danger'); }
        redirect('monitoring.php');
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM monitoring_pelatihan WHERE id = $id");
        alert('Data monitoring berhasil dihapus!');
        redirect('monitoring.php');
    }
}

$pegawaiList = $conn->query("SELECT * FROM pegawai ORDER BY nama");
$pelatihanList = $conn->query("SELECT * FROM pelatihan ORDER BY nama");
$kategori = $conn->query("SELECT * FROM kategori_pelatihan ORDER BY nama");
$lingkup = $conn->query("SELECT * FROM lingkup_pelatihan ORDER BY nama");

$search = $_GET['search'] ?? '';
$filterYear = $_GET['year'] ?? '';
$whereClause = "WHERE 1=1";
if ($search) $whereClause .= " AND (pg.nama LIKE '%$search%' OR p.nama LIKE '%$search%')";
if ($filterYear) $whereClause .= " AND m.tahun = $filterYear";

$monitoring = $conn->query("SELECT m.*, pg.nama as pegawai_nama, p.nama as pelatihan_nama, 
    k.nama as kategori_nama, l.nama as lingkup_nama
    FROM monitoring_pelatihan m 
    LEFT JOIN pegawai pg ON m.pegawai_id = pg.id
    LEFT JOIN pelatihan p ON m.pelatihan_id = p.id
    LEFT JOIN kategori_pelatihan k ON p.kategori_id = k.id
    LEFT JOIN lingkup_pelatihan l ON p.lingkup_id = l.id
    $whereClause ORDER BY m.tahun DESC, pg.nama");

$editData = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editData = $conn->query("SELECT * FROM monitoring_pelatihan WHERE id = $editId")->fetch_assoc();
}
$showForm = (isset($_GET['action']) && $_GET['action'] == 'add') || $editData;
$years = $conn->query("SELECT DISTINCT tahun FROM monitoring_pelatihan ORDER BY tahun DESC");
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Monitoring Pelatihan Pegawai</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;">MONITORING PELATIHAN</h1>
    </div>
    <a href="../" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        KEMBALI
    </a>
</div>

<?php if($showForm): ?>
<div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-6">
    <div class="px-4 py-3 border-b border-gray-200" style="background:#f0f7ff;">
        <h2 class="font-semibold text-gray-800"><?= $editData ? 'Edit Data' : 'Tambah Data Monitoring' ?></h2>
    </div>
    <form method="POST" class="p-4">
        <input type="hidden" name="action" value="<?= $editData ? 'edit' : 'add' ?>">
        <?php if($editData): ?><input type="hidden" name="id" value="<?= $editData['id'] ?>"><?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Peserta Pelatihan *</label>
                <select name="pegawai_id" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <option value="">-- Pilih Pegawai --</option>
                    <?php $pegawaiList->data_seek(0); while($r = $pegawaiList->fetch_assoc()): ?>
                    <option value="<?= $r['id'] ?>" <?= ($editData['pegawai_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= $r['nama'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Pelatihan *</label>
                <select name="pelatihan_id" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <option value="">-- Pilih Pelatihan --</option>
                    <?php $pelatihanList->data_seek(0); while($r = $pelatihanList->fetch_assoc()): ?>
                    <option value="<?= $r['id'] ?>" <?= ($editData['pelatihan_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= $r['nama'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tahun</label>
                <input type="number" name="tahun" min="2020" max="2030" value="<?= $editData['tahun'] ?? date('Y') ?>" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Pelaksanaan</label>
                <input type="date" name="pelaksanaan" value="<?= $editData['pelaksanaan'] ?? '' ?>" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Sertifikat</label>
                <input type="text" name="no_sertifikat" value="<?= $editData['no_sertifikat'] ?? '' ?>" placeholder="No. Sertifikat" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">JP</label>
                <input type="number" name="jumlah_jp" min="0" value="<?= $editData['jumlah_jp'] ?? 0 ?>" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
        </div>
        <div class="flex gap-2 mt-4 pt-3 border-t border-gray-200">
            <button type="submit" class="px-4 py-2 text-sm text-white rounded hover:opacity-90" style="background:#005BAC;"><?= $editData ? 'Perbarui' : 'Simpan' ?></button>
            <a href="monitoring.php" class="px-4 py-2 text-sm border border-gray-300 rounded hover:bg-gray-50 text-gray-600">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Tabel Data -->
<div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3 border-b border-gray-200" style="background:#f8fafc;">
        <form method="GET" class="flex items-center gap-2 flex-wrap">
            <input type="text" name="search" placeholder="Cari peserta/pelatihan..." value="<?= htmlspecialchars($search) ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded w-52 focus:outline-none focus:border-blue-500">
            <select name="year" class="px-3 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                <option value="">Semua Tahun</option>
                <?php $years->data_seek(0); while($y = $years->fetch_assoc()): ?>
                <option value="<?= $y['tahun'] ?>" <?= $filterYear == $y['tahun'] ? 'selected' : '' ?>><?= $y['tahun'] ?></option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="px-3 py-1.5 text-sm text-white rounded" style="background:#005BAC;">Filter</button>
            <?php if($search || $filterYear): ?><a href="monitoring.php" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50">Reset</a><?php endif; ?>
        </form>
        <?php if(!$showForm): ?>
        <a href="?action=add" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-white rounded" style="background:#00A651;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah
        </a>
        <?php endif; ?>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr style="background:#1a365d; color:white;">
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800 w-12">No</th>
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800">Peserta Pelatihan</th>
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800">Pelatihan</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-16">Tahun</th>
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800 w-32">Kategori Pelatihan</th>
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800 w-32">Lingkup Pelatihan</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-28">Pelaksanaan</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-24">Sertifikat</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-14">JP</th>
                    <th class="px-3 py-2.5 text-center font-semibold w-20">Aksi</th>
                </tr>
            </thead>
            <tbody>
<?php if($monitoring->num_rows > 0): $no = 1; $totalJP = 0; while($row = $monitoring->fetch_assoc()): 
    $bgColor = $no % 2 == 0 ? '#f8fafc' : '#ffffff';
    $totalJP += $row['jumlah_jp'];
    $katColors = ['Mutlak'=>'#dc2626','Penting'=>'#d97706','Perlu'=>'#2563eb','Pelatihan IDEAS'=>'#7c3aed'];
    $katColor = $katColors[$row['kategori_nama']] ?? '#6b7280';
?>
                <tr style="background:<?= $bgColor ?>;" class="hover:bg-blue-50 border-b border-gray-200">
                    <td class="px-3 py-2 text-gray-500 border-r border-gray-200 text-center"><?= $no++ ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 font-medium text-gray-800"><?= $row['pegawai_nama'] ?? '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-gray-700"><?= $row['pelatihan_nama'] ?? '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center font-medium" style="color:#005BAC;"><?= $row['tahun'] ?></td>
                    <td class="px-3 py-2 border-r border-gray-200">
                        <span class="text-xs px-2 py-0.5 rounded" style="background:<?= $katColor ?>20; color:<?= $katColor ?>;"><?= $row['kategori_nama'] ?? '-' ?></span>
                    </td>
                    <td class="px-3 py-2 border-r border-gray-200 text-gray-600"><?= $row['lingkup_nama'] ?? '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center text-gray-600"><?= $row['pelaksanaan'] ? date('d/m/Y', strtotime($row['pelaksanaan'])) : '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center">
                        <?php if($row['no_sertifikat']): 
                            // Check if it contains a link
                            if(strpos($row['no_sertifikat'], 'http') !== false):
                                preg_match('/(https?:\/\/[^\s|]+)/', $row['no_sertifikat'], $matches);
                                $link = $matches[1] ?? '';
                        ?>
                        <a href="<?= $link ?>" target="_blank" class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded hover:opacity-80" style="background:#05966920; color:#059669;" title="Lihat Sertifikat">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            Lihat
                        </a>
                        <?php else: ?>
                        <span class="text-xs px-2 py-0.5 rounded" style="background:#05966920; color:#059669;" title="<?= htmlspecialchars($row['no_sertifikat']) ?>">Ada</span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-xs px-2 py-0.5 rounded" style="background:#6b728020; color:#6b7280;">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center font-medium" style="color:#005BAC;"><?= $row['jumlah_jp'] ?></td>
                    <td class="px-3 py-2 text-center">
                        <div class="flex items-center justify-center gap-1">
                            <a href="?edit=<?= $row['id'] ?>" class="p-1 text-amber-600 hover:bg-amber-50 rounded" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" onsubmit="return confirm('Hapus data ini?')" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="p-1 text-red-600 hover:bg-red-50 rounded" title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
<?php endwhile; ?>
                <tr style="background:#e2e8f0;" class="font-semibold">
                    <td colspan="8" class="px-3 py-2 border-r border-gray-300 text-right">Total JP:</td>
                    <td class="px-3 py-2 border-r border-gray-300 text-center" style="color:#005BAC;"><?= $totalJP ?></td>
                    <td></td>
                </tr>
<?php else: ?>
                <tr>
                    <td colspan="10" class="px-4 py-12 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        <p class="font-medium">Tidak ada data monitoring</p>
                        <p class="text-sm mt-1">Klik tombol "Tambah" untuk menambahkan data baru</p>
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="px-4 py-2 border-t border-gray-200 text-xs text-gray-500" style="background:#f8fafc;">
        Total: <?= $monitoring->num_rows ?> data monitoring
    </div>
</div>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
