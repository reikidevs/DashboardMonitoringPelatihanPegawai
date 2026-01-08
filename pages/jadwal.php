<?php 
require_once '../includes/header.php';
$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pelatihan_id = (int)$_POST['pelatihan_id'];
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $rencana_peserta = (int)$_POST['rencana_peserta'];
        $biaya = (float)$_POST['biaya'];
        $status = sanitize($_POST['status']);
        $stmt = $conn->prepare("INSERT INTO jadwal_pelatihan (pelatihan_id, tanggal_mulai, tanggal_selesai, rencana_peserta, biaya, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issids", $pelatihan_id, $tanggal_mulai, $tanggal_selesai, $rencana_peserta, $biaya, $status);
        if ($stmt->execute()) { alert('Jadwal berhasil ditambahkan!'); }
        else { alert('Gagal menambahkan jadwal', 'danger'); }
        redirect('jadwal.php');
    }
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $pelatihan_id = (int)$_POST['pelatihan_id'];
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $rencana_peserta = (int)$_POST['rencana_peserta'];
        $biaya = (float)$_POST['biaya'];
        $status = sanitize($_POST['status']);
        $stmt = $conn->prepare("UPDATE jadwal_pelatihan SET pelatihan_id=?, tanggal_mulai=?, tanggal_selesai=?, rencana_peserta=?, biaya=?, status=? WHERE id=?");
        $stmt->bind_param("issidsi", $pelatihan_id, $tanggal_mulai, $tanggal_selesai, $rencana_peserta, $biaya, $status, $id);
        if ($stmt->execute()) { alert('Jadwal berhasil diupdate!'); }
        else { alert('Gagal mengupdate jadwal', 'danger'); }
        redirect('jadwal.php');
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM jadwal_pelatihan WHERE id = $id");
        alert('Jadwal berhasil dihapus!');
        redirect('jadwal.php');
    }
}

$pelatihan = $conn->query("SELECT * FROM pelatihan ORDER BY nama");
$search = $_GET['search'] ?? '';
$whereClause = $search ? "WHERE p.nama LIKE '%$search%'" : "";
$jadwal = $conn->query("SELECT j.*, p.nama as pelatihan_nama FROM jadwal_pelatihan j 
    LEFT JOIN pelatihan p ON j.pelatihan_id = p.id $whereClause ORDER BY j.tanggal_mulai DESC");

$editData = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editData = $conn->query("SELECT * FROM jadwal_pelatihan WHERE id = $editId")->fetch_assoc();
}
$showForm = (isset($_GET['action']) && $_GET['action'] == 'add') || $editData;

// Status labels Indonesia
$statusLabels = ['Not Started'=>'Belum Mulai','In-Progress'=>'Sedang Berjalan','Completed'=>'Selesai','Cancelled'=>'Dibatalkan'];
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Jadwal Pelaksanaan Pelatihan</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;">DATABASE JADWAL</h1>
    </div>
    <a href="../index.php" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        KEMBALI
    </a>
</div>

<?php if($showForm): ?>
<div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-6">
    <div class="px-4 py-3 border-b border-gray-200" style="background:#f0f7ff;">
        <h2 class="font-semibold text-gray-800"><?= $editData ? 'Edit Jadwal' : 'Tambah Jadwal Baru' ?></h2>
    </div>
    <form method="POST" class="p-4">
        <input type="hidden" name="action" value="<?= $editData ? 'edit' : 'add' ?>">
        <?php if($editData): ?><input type="hidden" name="id" value="<?= $editData['id'] ?>"><?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Pelatihan *</label>
                <select name="pelatihan_id" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <option value="">-- Pilih Pelatihan --</option>
                    <?php $pelatihan->data_seek(0); while($r = $pelatihan->fetch_assoc()): ?>
                    <option value="<?= $r['id'] ?>" <?= ($editData['pelatihan_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= $r['nama'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="<?= $editData['tanggal_mulai'] ?? '' ?>" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" value="<?= $editData['tanggal_selesai'] ?? '' ?>" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Rencana Jumlah Peserta</label>
                <input type="number" name="rencana_peserta" min="0" value="<?= $editData['rencana_peserta'] ?? 0 ?>" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Biaya (Rp)</label>
                <input type="number" name="biaya" min="0" step="1000" value="<?= $editData['biaya'] ?? 0 ?>" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <option value="Not Started" <?= ($editData['status'] ?? '') == 'Not Started' ? 'selected' : '' ?>>Belum Mulai</option>
                    <option value="In-Progress" <?= ($editData['status'] ?? '') == 'In-Progress' ? 'selected' : '' ?>>Sedang Berjalan</option>
                    <option value="Completed" <?= ($editData['status'] ?? '') == 'Completed' ? 'selected' : '' ?>>Selesai</option>
                    <option value="Cancelled" <?= ($editData['status'] ?? '') == 'Cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-4 pt-3 border-t border-gray-200">
            <button type="submit" class="px-4 py-2 text-sm text-white rounded hover:opacity-90" style="background:#005BAC;"><?= $editData ? 'Perbarui' : 'Simpan' ?></button>
            <a href="jadwal.php" class="px-4 py-2 text-sm border border-gray-300 rounded hover:bg-gray-50 text-gray-600">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Tabel Data -->
<div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3 border-b border-gray-200" style="background:#f8fafc;">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" placeholder="Cari pelatihan..." value="<?= htmlspecialchars($search) ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded w-64 focus:outline-none focus:border-blue-500">
            <button type="submit" class="px-3 py-1.5 text-sm text-white rounded" style="background:#005BAC;">Cari</button>
            <?php if($search): ?><a href="jadwal.php" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50">Reset</a><?php endif; ?>
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
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800">Pelatihan</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-28">Tgl Mulai</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-28">Tgl Selesai</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-32">Rencana Peserta</th>
                    <th class="px-3 py-2.5 text-right font-semibold border-r border-blue-800 w-36">Biaya (Rp)</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-32">Status</th>
                    <th class="px-3 py-2.5 text-center font-semibold w-20">Aksi</th>
                </tr>
            </thead>
            <tbody>
<?php if($jadwal->num_rows > 0): $no = 1; $totalBiaya = 0; while($row = $jadwal->fetch_assoc()): 
    $bgColor = $no % 2 == 0 ? '#f8fafc' : '#ffffff';
    $totalBiaya += $row['biaya'];
    $statusColors = ['Not Started'=>'#6b7280','In-Progress'=>'#2563eb','Completed'=>'#059669','Cancelled'=>'#dc2626'];
    $statusColor = $statusColors[$row['status']] ?? '#6b7280';
    $statusLabel = $statusLabels[$row['status']] ?? $row['status'];
?>
                <tr style="background:<?= $bgColor ?>;" class="hover:bg-blue-50 border-b border-gray-200">
                    <td class="px-3 py-2 text-gray-500 border-r border-gray-200 text-center"><?= $no++ ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 font-medium text-gray-800"><?= $row['pelatihan_nama'] ?? '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center text-gray-600"><?= $row['tanggal_mulai'] ? date('d/m/Y', strtotime($row['tanggal_mulai'])) : '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center text-gray-600"><?= $row['tanggal_selesai'] ? date('d/m/Y', strtotime($row['tanggal_selesai'])) : '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center font-medium" style="color:#005BAC;"><?= $row['rencana_peserta'] ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-right text-gray-700">Rp <?= number_format($row['biaya'], 0, ',', '.') ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center">
                        <span class="text-xs px-2 py-0.5 rounded" style="background:<?= $statusColor ?>20; color:<?= $statusColor ?>;"><?= $statusLabel ?></span>
                    </td>
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
                    <td colspan="5" class="px-3 py-2 border-r border-gray-300 text-right">Total Biaya:</td>
                    <td class="px-3 py-2 border-r border-gray-300 text-right" style="color:#005BAC;">Rp <?= number_format($totalBiaya, 0, ',', '.') ?></td>
                    <td colspan="2"></td>
                </tr>
<?php else: ?>
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="font-medium">Tidak ada data jadwal</p>
                        <p class="text-sm mt-1">Klik tombol "Tambah" untuk menambahkan data baru</p>
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="px-4 py-2 border-t border-gray-200 text-xs text-gray-500" style="background:#f8fafc;">
        Total: <?= $jadwal->num_rows ?> jadwal pelatihan
    </div>
</div>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
