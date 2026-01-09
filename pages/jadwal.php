<?php 
require_once '../includes/header.php';
$conn = getConnection();

// Cek apakah admin untuk aksi write
$canEdit = isAdmin();

// Fungsi sinkronisasi peserta ke monitoring
function syncToMonitoring($conn, $jadwal_id) {
    // Ambil data jadwal
    $jadwal = $conn->query("SELECT j.*, p.jumlah_jp FROM jadwal_pelatihan j 
        LEFT JOIN pelatihan p ON j.pelatihan_id = p.id WHERE j.id = $jadwal_id")->fetch_assoc();
    
    if (!$jadwal || $jadwal['status'] !== 'Completed') return 0;
    
    // Ambil peserta yang hadir
    $peserta = $conn->query("SELECT * FROM jadwal_peserta WHERE jadwal_id = $jadwal_id AND status = 'Hadir'");
    $synced = 0;
    
    while ($p = $peserta->fetch_assoc()) {
        // Cek apakah sudah ada di monitoring
        $exists = $conn->query("SELECT id FROM monitoring_pelatihan 
            WHERE jadwal_id = $jadwal_id AND pegawai_id = {$p['pegawai_id']}")->num_rows;
        
        if (!$exists) {
            $tahun = $jadwal['tanggal_selesai'] ? date('Y', strtotime($jadwal['tanggal_selesai'])) : date('Y');
            $tanggal_mulai = $jadwal['tanggal_mulai'];
            $tanggal_selesai = $jadwal['tanggal_selesai'];
            $jp = $jadwal['jumlah_jp'] ?? 0;
            
            $stmt = $conn->prepare("INSERT INTO monitoring_pelatihan 
                (pegawai_id, pelatihan_id, jadwal_id, tahun, tanggal_mulai, tanggal_selesai, jumlah_jp) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiissi", $p['pegawai_id'], $jadwal['pelatihan_id'], $jadwal_id, $tahun, $tanggal_mulai, $tanggal_selesai, $jp);
            if ($stmt->execute()) $synced++;
        }
    }
    return $synced;
}

// Hanya proses POST jika admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $pelatihan_id = (int)$_POST['pelatihan_id'];
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $biaya = (float)$_POST['biaya'];
        $status = sanitize($_POST['status']);
        $stmt = $conn->prepare("INSERT INTO jadwal_pelatihan (pelatihan_id, tanggal_mulai, tanggal_selesai, biaya, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issds", $pelatihan_id, $tanggal_mulai, $tanggal_selesai, $biaya, $status);
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            if ($status === 'Completed') syncToMonitoring($conn, $newId);
            alert('Jadwal berhasil ditambahkan!');
        } else {
            alert('Gagal menambahkan jadwal', 'danger');
        }
        redirect('jadwal.php');
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $pelatihan_id = (int)$_POST['pelatihan_id'];
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $biaya = (float)$_POST['biaya'];
        $status = sanitize($_POST['status']);
        
        // Cek status sebelumnya
        $oldStatus = $conn->query("SELECT status FROM jadwal_pelatihan WHERE id = $id")->fetch_assoc()['status'];
        
        $stmt = $conn->prepare("UPDATE jadwal_pelatihan SET pelatihan_id=?, tanggal_mulai=?, tanggal_selesai=?, biaya=?, status=? WHERE id=?");
        $stmt->bind_param("issdsi", $pelatihan_id, $tanggal_mulai, $tanggal_selesai, $biaya, $status, $id);
        if ($stmt->execute()) {
            // Sync ke monitoring jika status berubah ke Completed
            if ($status === 'Completed' && $oldStatus !== 'Completed') {
                $synced = syncToMonitoring($conn, $id);
                if ($synced > 0) alert("Jadwal diupdate & $synced peserta disinkronkan ke monitoring!");
                else alert('Jadwal berhasil diupdate!');
            } else {
                alert('Jadwal berhasil diupdate!');
            }
        } else {
            alert('Gagal mengupdate jadwal', 'danger');
        }
        redirect('jadwal.php');
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM jadwal_peserta WHERE jadwal_id = $id");
        $conn->query("DELETE FROM jadwal_pelatihan WHERE id = $id");
        alert('Jadwal berhasil dihapus!');
        redirect('jadwal.php');
    }
    
    // Tambah peserta ke jadwal
    if ($action === 'add_peserta') {
        $jadwal_id = (int)$_POST['jadwal_id'];
        $pegawai_ids = $_POST['pegawai_ids'] ?? [];
        $added = 0;
        foreach ($pegawai_ids as $pid) {
            $pid = (int)$pid;
            $stmt = $conn->prepare("INSERT IGNORE INTO jadwal_peserta (jadwal_id, pegawai_id, status) VALUES (?, ?, 'Terdaftar')");
            $stmt->bind_param("ii", $jadwal_id, $pid);
            if ($stmt->execute() && $stmt->affected_rows > 0) $added++;
        }
        if ($added > 0) alert("$added peserta berhasil ditambahkan!");
        redirect("jadwal.php?peserta=$jadwal_id");
    }
    
    // Update status peserta
    if ($action === 'update_peserta_status') {
        $peserta_id = (int)$_POST['peserta_id'];
        $status = sanitize($_POST['status']);
        $jadwal_id = (int)$_POST['jadwal_id'];
        $conn->query("UPDATE jadwal_peserta SET status = '$status' WHERE id = $peserta_id");
        
        // Jika jadwal sudah completed dan peserta diubah ke Hadir, sync ke monitoring
        $jadwal = $conn->query("SELECT status FROM jadwal_pelatihan WHERE id = $jadwal_id")->fetch_assoc();
        if ($jadwal['status'] === 'Completed' && $status === 'Hadir') {
            syncToMonitoring($conn, $jadwal_id);
        }
        
        alert('Status peserta diupdate!');
        redirect("jadwal.php?peserta=$jadwal_id");
    }
    
    // Hapus peserta dari jadwal
    if ($action === 'remove_peserta') {
        $peserta_id = (int)$_POST['peserta_id'];
        $jadwal_id = (int)$_POST['jadwal_id'];
        $conn->query("DELETE FROM jadwal_peserta WHERE id = $peserta_id");
        alert('Peserta dihapus dari jadwal!');
        redirect("jadwal.php?peserta=$jadwal_id");
    }
    
    // Manual sync ke monitoring
    if ($action === 'sync_monitoring') {
        $jadwal_id = (int)$_POST['jadwal_id'];
        $synced = syncToMonitoring($conn, $jadwal_id);
        if ($synced > 0) alert("$synced peserta berhasil disinkronkan ke monitoring!");
        else alert('Tidak ada peserta baru untuk disinkronkan', 'warning');
        redirect("jadwal.php?peserta=$jadwal_id");
    }
}

$pelatihan = $conn->query("SELECT * FROM pelatihan ORDER BY nama");
$search = $_GET['search'] ?? '';
$whereClause = $search ? "WHERE p.nama LIKE '%$search%'" : "";
$jadwal = $conn->query("SELECT j.*, p.nama as pelatihan_nama,
    (SELECT COUNT(*) FROM jadwal_peserta WHERE jadwal_id = j.id) as jumlah_peserta,
    (SELECT GROUP_CONCAT(pg.nama SEPARATOR ', ') FROM jadwal_peserta jp 
     LEFT JOIN pegawai pg ON jp.pegawai_id = pg.id WHERE jp.jadwal_id = j.id) as nama_peserta
    FROM jadwal_pelatihan j 
    LEFT JOIN pelatihan p ON j.pelatihan_id = p.id $whereClause ORDER BY j.tanggal_mulai DESC");

$editData = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editData = $conn->query("SELECT * FROM jadwal_pelatihan WHERE id = $editId")->fetch_assoc();
}
$showForm = (isset($_GET['action']) && $_GET['action'] == 'add') || $editData;

// Mode kelola peserta
$pesertaMode = isset($_GET['peserta']);
$jadwalPeserta = null;
$daftarPeserta = null;
$pegawaiList = null;

if ($pesertaMode) {
    $jadwalId = (int)$_GET['peserta'];
    $jadwalPeserta = $conn->query("SELECT j.*, p.nama as pelatihan_nama FROM jadwal_pelatihan j 
        LEFT JOIN pelatihan p ON j.pelatihan_id = p.id WHERE j.id = $jadwalId")->fetch_assoc();
    
    if ($jadwalPeserta) {
        $daftarPeserta = $conn->query("SELECT jp.*, pg.nama as pegawai_nama, pg.nip, pg.jabatan 
            FROM jadwal_peserta jp 
            LEFT JOIN pegawai pg ON jp.pegawai_id = pg.id 
            WHERE jp.jadwal_id = $jadwalId ORDER BY pg.nama");
        $pegawaiList = $conn->query("SELECT * FROM pegawai WHERE id NOT IN 
            (SELECT pegawai_id FROM jadwal_peserta WHERE jadwal_id = $jadwalId) ORDER BY nama");
    }
}

$statusLabels = ['Not Started'=>'Belum Mulai','In-Progress'=>'Sedang Berjalan','Completed'=>'Selesai','Cancelled'=>'Dibatalkan'];
$pesertaStatusLabels = ['Terdaftar'=>'Terdaftar','Hadir'=>'Hadir','Tidak Hadir'=>'Tidak Hadir','Batal'=>'Batal'];
?>

<?php if($pesertaMode && $jadwalPeserta): ?>
<!-- Mode Kelola Peserta -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Kelola Peserta Pelatihan</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;"><?= htmlspecialchars($jadwalPeserta['pelatihan_nama']) ?></h1>
        <p class="text-sm text-gray-600 mt-1">
            <?= $jadwalPeserta['tanggal_mulai'] ? date('d/m/Y', strtotime($jadwalPeserta['tanggal_mulai'])) : '-' ?> 
            s/d <?= $jadwalPeserta['tanggal_selesai'] ? date('d/m/Y', strtotime($jadwalPeserta['tanggal_selesai'])) : '-' ?>
            <span class="ml-2 text-xs px-2 py-0.5 rounded" style="background:<?= $jadwalPeserta['status'] === 'Completed' ? '#05966920' : '#2563eb20' ?>; color:<?= $jadwalPeserta['status'] === 'Completed' ? '#059669' : '#2563eb' ?>;">
                <?= $statusLabels[$jadwalPeserta['status']] ?? $jadwalPeserta['status'] ?>
            </span>
        </p>
    </div>
    <div class="flex gap-2">
        <?php if($jadwalPeserta['status'] === 'Completed' && $canEdit): ?>
        <form method="POST" class="inline">
            <input type="hidden" name="action" value="sync_monitoring">
            <input type="hidden" name="jadwal_id" value="<?= $jadwalPeserta['id'] ?>">
            <button type="submit" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded text-white" style="background:#059669;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Sync ke Monitoring
            </button>
        </form>
        <?php endif; ?>
        <a href="jadwal.php" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali
        </a>
    </div>
</div>

<!-- Form Tambah Peserta -->
<?php if($canEdit): ?>
<div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-6">
    <div class="px-4 py-3 border-b border-gray-200" style="background:#f0f7ff;">
        <h2 class="font-semibold text-gray-800">Tambah Peserta</h2>
    </div>
    <form method="POST" class="p-4">
        <input type="hidden" name="action" value="add_peserta">
        <input type="hidden" name="jadwal_id" value="<?= $jadwalPeserta['id'] ?>">
        <div class="flex flex-col md:flex-row gap-3">
            <div class="flex-1">
                <select name="pegawai_ids[]" multiple class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500" style="min-height:100px;">
                    <?php if($pegawaiList && $pegawaiList->num_rows > 0): ?>
                    <?php while($pg = $pegawaiList->fetch_assoc()): ?>
                    <option value="<?= $pg['id'] ?>"><?= $pg['nama'] ?> <?= $pg['nip'] ? "({$pg['nip']})" : '' ?></option>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <option disabled>Semua pegawai sudah terdaftar</option>
                    <?php endif; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Tahan Ctrl/Cmd untuk memilih beberapa pegawai</p>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 text-sm text-white rounded hover:opacity-90" style="background:#00A651;">Tambah Peserta</button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Daftar Peserta -->
<div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200" style="background:#f8fafc;">
        <span class="font-semibold text-gray-700">Daftar Peserta (<?= $daftarPeserta ? $daftarPeserta->num_rows : 0 ?>)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr style="background:#1a365d; color:white;">
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800 w-12">No</th>
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800">Nama Pegawai</th>
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800 w-40">NIP</th>
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800 w-48">Jabatan</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-36">Status</th>
                    <th class="px-3 py-2.5 text-center font-semibold w-20">Aksi</th>
                </tr>
            </thead>
            <tbody>
<?php if($daftarPeserta && $daftarPeserta->num_rows > 0): $no = 1; while($row = $daftarPeserta->fetch_assoc()): 
    $bgColor = $no % 2 == 0 ? '#f8fafc' : '#ffffff';
    $statusColors = ['Terdaftar'=>'#6b7280','Hadir'=>'#059669','Tidak Hadir'=>'#dc2626','Batal'=>'#d97706'];
    $statusColor = $statusColors[$row['status']] ?? '#6b7280';
?>
                <tr style="background:<?= $bgColor ?>;" class="hover:bg-blue-50 border-b border-gray-200">
                    <td class="px-3 py-2 text-gray-500 border-r border-gray-200 text-center"><?= $no++ ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 font-medium text-gray-800"><?= $row['pegawai_nama'] ?? '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-gray-600"><?= $row['nip'] ?? '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-gray-600"><?= $row['jabatan'] ?? '-' ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center">
                        <?php if($canEdit): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="update_peserta_status">
                            <input type="hidden" name="peserta_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="jadwal_id" value="<?= $jadwalPeserta['id'] ?>">
                            <select name="status" onchange="this.form.submit()" class="text-xs px-2 py-1 rounded border-0" style="background:<?= $statusColor ?>20; color:<?= $statusColor ?>;">
                                <?php foreach($pesertaStatusLabels as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $row['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php else: ?>
                        <span class="text-xs px-2 py-1 rounded" style="background:<?= $statusColor ?>20; color:<?= $statusColor ?>;"><?= $pesertaStatusLabels[$row['status']] ?? $row['status'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <?php if($canEdit): ?>
                        <form method="POST" onsubmit="return confirm('Hapus peserta ini?')" class="inline">
                            <input type="hidden" name="action" value="remove_peserta">
                            <input type="hidden" name="peserta_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="jadwal_id" value="<?= $jadwalPeserta['id'] ?>">
                            <button type="submit" class="p-1 text-red-600 hover:bg-red-50 rounded" title="Hapus">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
<?php endwhile; else: ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                        <p>Belum ada peserta terdaftar</p>
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- Mode Normal - Daftar Jadwal -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Jadwal Pelaksanaan Pelatihan</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;">DATABASE JADWAL</h1>
    </div>
    <a href="../" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        KEMBALI
    </a>
</div>

<?php if($showForm && $canEdit): ?>
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
        <?php if(!$showForm && $canEdit): ?>
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
                    <th class="px-3 py-2.5 text-left font-semibold border-r border-blue-800" style="min-width:200px;">Peserta</th>
                    <th class="px-3 py-2.5 text-right font-semibold border-r border-blue-800 w-36">Biaya (Rp)</th>
                    <th class="px-3 py-2.5 text-center font-semibold border-r border-blue-800 w-32">Status</th>
                    <th class="px-3 py-2.5 text-center font-semibold w-28">Aksi</th>
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
                    <td class="px-3 py-2 border-r border-gray-200">
                        <?php if($row['nama_peserta']): ?>
                        <div class="text-xs text-gray-700" title="<?= htmlspecialchars($row['nama_peserta']) ?>">
                            <?php 
                            $pesertaArr = explode(', ', $row['nama_peserta']);
                            $maxShow = 3;
                            $shown = array_slice($pesertaArr, 0, $maxShow);
                            echo htmlspecialchars(implode(', ', $shown));
                            if (count($pesertaArr) > $maxShow) {
                                echo ' <span class="text-blue-600">+' . (count($pesertaArr) - $maxShow) . ' lainnya</span>';
                            }
                            ?>
                        </div>
                        <span class="text-xs text-gray-400">(<?= $row['jumlah_peserta'] ?> peserta)</span>
                        <?php else: ?>
                        <span class="text-xs text-gray-400 italic">Belum ada peserta</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2 border-r border-gray-200 text-right text-gray-700">Rp <?= number_format($row['biaya'], 0, ',', '.') ?></td>
                    <td class="px-3 py-2 border-r border-gray-200 text-center">
                        <span class="text-xs px-2 py-0.5 rounded" style="background:<?= $statusColor ?>20; color:<?= $statusColor ?>;"><?= $statusLabel ?></span>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <div class="flex items-center justify-center gap-1">
                            <a href="?peserta=<?= $row['id'] ?>" class="p-1 text-blue-600 hover:bg-blue-50 rounded" title="Lihat Peserta">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </a>
                            <?php if($canEdit): ?>
                            <a href="?edit=<?= $row['id'] ?>" class="p-1 text-amber-600 hover:bg-amber-50 rounded" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" onsubmit="return confirm('Hapus jadwal ini beserta pesertanya?')" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="p-1 text-red-600 hover:bg-red-50 rounded" title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            <?php endif; ?>
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
<?php endif; ?>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
