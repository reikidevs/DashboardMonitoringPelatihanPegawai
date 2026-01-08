<?php 
require_once '../includes/header.php';
$conn = getConnection();

// Check if table exists, create if not
$conn->query("CREATE TABLE IF NOT EXISTS kewajiban_pelatihan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pegawai_id INT NOT NULL,
    pelatihan_id INT NOT NULL,
    tahun_target YEAR,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_kewajiban (pegawai_id, pelatihan_id)
)");

$pegawai_id = isset($_GET['pegawai_id']) ? (int)$_GET['pegawai_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pid = (int)$_POST['pegawai_id'];
        $pelatihan_id = (int)$_POST['pelatihan_id'];
        $tahun_target = $_POST['tahun_target'] ?: null;
        $keterangan = sanitize($_POST['keterangan']);
        $stmt = $conn->prepare("INSERT IGNORE INTO kewajiban_pelatihan (pegawai_id, pelatihan_id, tahun_target, keterangan) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $pid, $pelatihan_id, $tahun_target, $keterangan);
        if ($stmt->execute()) { alert('Kewajiban pelatihan berhasil ditambahkan!'); }
        else { alert('Gagal menambahkan kewajiban', 'danger'); }
        redirect('kewajiban.php' . ($pid ? "?pegawai_id=$pid" : ''));
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pid = (int)$_POST['pegawai_id'];
        $conn->query("DELETE FROM kewajiban_pelatihan WHERE id = $id");
        alert('Kewajiban pelatihan berhasil dihapus!');
        redirect('kewajiban.php' . ($pid ? "?pegawai_id=$pid" : ''));
    }
    if ($action === 'bulk_add') {
        $pid = (int)$_POST['pegawai_id'];
        $pelatihan_ids = $_POST['pelatihan_ids'] ?? [];
        $tahun_target = $_POST['tahun_target'] ?: null;
        $added = 0;
        foreach($pelatihan_ids as $plid) {
            $plid = (int)$plid;
            $stmt = $conn->prepare("INSERT IGNORE INTO kewajiban_pelatihan (pegawai_id, pelatihan_id, tahun_target) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $pid, $plid, $tahun_target);
            if ($stmt->execute() && $stmt->affected_rows > 0) $added++;
        }
        alert("$added kewajiban pelatihan berhasil ditambahkan!");
        redirect('kewajiban.php?pegawai_id=' . $pid);
    }
}

$pegawaiList = $conn->query("SELECT * FROM pegawai ORDER BY nama");
$pelatihanList = $conn->query("SELECT p.*, k.nama as kategori_nama FROM pelatihan p LEFT JOIN kategori_pelatihan k ON p.kategori_id = k.id ORDER BY k.nama, p.nama");

// Get kewajiban
$whereClause = $pegawai_id ? "WHERE kp.pegawai_id = $pegawai_id" : "";
$kewajiban = $conn->query("
    SELECT kp.*, pg.nama as pegawai_nama, pg.nip, p.nama as pelatihan_nama, k.nama as kategori_nama,
           (SELECT COUNT(*) FROM monitoring_pelatihan m WHERE m.pegawai_id = kp.pegawai_id AND m.pelatihan_id = kp.pelatihan_id) as sudah_ikut
    FROM kewajiban_pelatihan kp
    LEFT JOIN pegawai pg ON kp.pegawai_id = pg.id
    LEFT JOIN pelatihan p ON kp.pelatihan_id = p.id
    LEFT JOIN kategori_pelatihan k ON p.kategori_id = k.id
    $whereClause
    ORDER BY pg.nama, k.nama, p.nama
");

$pegawaiInfo = $pegawai_id ? $conn->query("SELECT * FROM pegawai WHERE id = $pegawai_id")->fetch_assoc() : null;
$showForm = isset($_GET['action']) && $_GET['action'] == 'add';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Pengaturan Kewajiban Pelatihan</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;">
            <?= $pegawaiInfo ? 'KEWAJIBAN: ' . strtoupper($pegawaiInfo['nama']) : 'KEWAJIBAN PELATIHAN' ?>
        </h1>
        <?php if($pegawaiInfo): ?>
        <p class="text-sm text-gray-500 mt-1">NIP: <?= $pegawaiInfo['nip'] ?></p>
        <?php endif; ?>
    </div>
    <div class="flex items-center gap-2">
        <?php if($pegawaiInfo): ?>
        <a href="pegawai_detail.php?id=<?= $pegawai_id ?>" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Detail Pegawai
        </a>
        <?php else: ?>
        <a href="../index.php" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Beranda
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if($showForm && $pegawai_id): ?>
<!-- Form Tambah Kewajiban -->
<div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-6">
    <div class="px-4 py-3 border-b border-gray-200" style="background:#f0f7ff;">
        <h2 class="font-semibold text-gray-800">Tambah Kewajiban Pelatihan</h2>
    </div>
    <form method="POST" class="p-4">
        <input type="hidden" name="action" value="bulk_add">
        <input type="hidden" name="pegawai_id" value="<?= $pegawai_id ?>">
        <div class="mb-4">
            <label class="block text-xs font-medium text-gray-600 mb-1">Tahun Target</label>
            <input type="number" name="tahun_target" min="2020" max="2030" value="<?= date('Y') ?>" class="w-32 px-3 py-2 text-sm border border-gray-300 rounded">
        </div>
        <div class="mb-4">
            <label class="block text-xs font-medium text-gray-600 mb-2">Pilih Pelatihan Wajib</label>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto border border-gray-200 rounded p-3">
                <?php 
                $pelatihanList->data_seek(0);
                $currentKat = '';
                while($p = $pelatihanList->fetch_assoc()): 
                    if($currentKat != $p['kategori_nama']):
                        $currentKat = $p['kategori_nama'];
                ?>
                <div class="col-span-full font-semibold text-xs text-gray-500 mt-2 first:mt-0 border-b pb-1"><?= $currentKat ?: 'Lainnya' ?></div>
                <?php endif; ?>
                <label class="flex items-center gap-2 text-sm hover:bg-gray-50 p-1 rounded cursor-pointer">
                    <input type="checkbox" name="pelatihan_ids[]" value="<?= $p['id'] ?>" class="rounded border-gray-300">
                    <span><?= $p['nama'] ?></span>
                </label>
                <?php endwhile; ?>
            </div>
        </div>
        <div class="flex gap-2 pt-3 border-t border-gray-200">
            <button type="submit" class="px-4 py-2 text-sm text-white rounded hover:opacity-90" style="background:#005BAC;">Simpan</button>
            <a href="kewajiban.php?pegawai_id=<?= $pegawai_id ?>" class="px-4 py-2 text-sm border border-gray-300 rounded hover:bg-gray-50 text-gray-600">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Filter & Actions -->
<div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3 border-b border-gray-200" style="background:#f8fafc;">
        <form method="GET" class="flex items-center gap-2">
            <select name="pegawai_id" onchange="this.form.submit()" class="px-3 py-1.5 text-sm border border-gray-300 rounded w-64">
                <option value="">-- Semua Pegawai --</option>
                <?php $pegawaiList->data_seek(0); while($pg = $pegawaiList->fetch_assoc()): ?>
                <option value="<?= $pg['id'] ?>" <?= $pegawai_id == $pg['id'] ? 'selected' : '' ?>><?= $pg['nama'] ?></option>
                <?php endwhile; ?>
            </select>
        </form>
        <?php if($pegawai_id && !$showForm): ?>
        <a href="?pegawai_id=<?= $pegawai_id ?>&action=add" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-white rounded" style="background:#00A651;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Kewajiban
        </a>
        <?php endif; ?>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr style="background:#1a365d; color:white;">
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800 w-12">No</th>
                    <?php if(!$pegawai_id): ?>
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800">Pegawai</th>
                    <?php endif; ?>
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800">Pelatihan Wajib</th>
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800 w-32">Kategori</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-24">Tahun Target</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-24">Status</th>
                    <th class="px-3 py-2.5 text-center font-semibold w-20">Aksi</th>
                </tr>
            </thead>
            <tbody>
<?php if($kewajiban->num_rows > 0): $no = 1; while($row = $kewajiban->fetch_assoc()): 
    $bgColor = $no % 2 == 0 ? '#f8fafc' : '#ffffff';
    $isDone = $row['sudah_ikut'] > 0;
    $katColors = ['Mutlak'=>'#dc2626','Penting'=>'#d97706','Perlu'=>'#2563eb','Pelatihan IDEAS'=>'#7c3aed'];
    $katColor = $katColors[$row['kategori_nama']] ?? '#6b7280';
?>
                <tr style="background:<?= $bgColor ?>;" class="hover:bg-blue-50 border-b border-gray-200">
                    <td class="px-3 py-2 text-gray-500 border-r border-gray-200 text-center"><?= $no++ ?></td>
                    <?php if(!$pegawai_id): ?>
                    <td class="px-3 py-2 border-r border-gray-200">
                        <a href="pegawai_detail.php?id=<?= $row['pegawai_id'] ?>" class="font-medium text-blue-600 hover:underline"><?= $row['pegawai_nama'] ?></a>
                    </td>
                    <?php endif; ?>
                    <td class="px-3 py-2 border-r border-gray-200 font-medium text-gray-800"><?= $row['pelatihan_nama'] ?></td>
                    <td class="px-3 py-2 border-r border-gray-200">
                        <span class="text-xs px-2 py-0.5 rounded" style="background:<?= $katColor ?>20; color:<?= $katColor ?>;"><?= $row['kategori_nama'] ?? '-' ?></span>
                    </td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center font-medium" style="color:#005BAC;"><?= $row['tahun_target'] ?: '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center">
                        <?php if($isDone): ?>
                        <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700 inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Selesai
                        </span>
                        <?php else: ?>
                        <span class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-700">Belum</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <form method="POST" onsubmit="return confirm('Hapus kewajiban ini?')" class="inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="pegawai_id" value="<?= $pegawai_id ?>">
                            <button type="submit" class="p-1 text-red-600 hover:bg-red-50 rounded" title="Hapus">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </td>
                </tr>
<?php endwhile; else: ?>
                <tr>
                    <td colspan="<?= $pegawai_id ? 6 : 7 ?>" class="px-4 py-12 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        <p class="font-medium">Belum ada kewajiban pelatihan</p>
                        <?php if($pegawai_id): ?>
                        <a href="?pegawai_id=<?= $pegawai_id ?>&action=add" class="text-blue-600 hover:underline text-sm mt-1 inline-block">+ Tambah Kewajiban</a>
                        <?php else: ?>
                        <p class="text-sm mt-1">Pilih pegawai terlebih dahulu</p>
                        <?php endif; ?>
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="px-4 py-2 border-t border-gray-200 text-xs text-gray-500" style="background:#f8fafc;">
        Total: <?= $kewajiban->num_rows ?> kewajiban pelatihan
    </div>
</div>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
