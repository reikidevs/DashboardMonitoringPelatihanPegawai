<?php 
require_once '../includes/header.php';
$conn = getConnection();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'import_csv') {
        // Import from uploaded CSV file (exported from Google Sheets)
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            
            // Skip header row
            $header = fgetcsv($handle);
            
            $imported = 0;
            $errors = [];
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) < 5) continue;
                
                // Expected columns: Nama, NIP, Jabatan, Kategori, Nama Pelatihan, Tahun, Realisasi, No Sertifikat
                $nama = trim($data[0] ?? '');
                $nip = trim($data[1] ?? '');
                $jabatan = trim($data[2] ?? '');
                $kategori = trim($data[3] ?? '');
                $pelatihan_nama = trim($data[4] ?? '');
                $tahun = (int)trim($data[5] ?? date('Y'));
                $realisasi = trim($data[6] ?? '');
                $keterangan = trim($data[7] ?? '');
                
                if (empty($nama) || empty($pelatihan_nama)) continue;
                
                // Find or create pegawai
                $namaSafe = $conn->real_escape_string($nama);
                $nipSafe = $conn->real_escape_string($nip);
                $pegawai = $conn->query("SELECT id FROM pegawai WHERE nip = '$nipSafe' OR nama = '$namaSafe' LIMIT 1")->fetch_assoc();
                if (!$pegawai) {
                    $stmt = $conn->prepare("INSERT INTO pegawai (nama, nip, jabatan) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $nama, $nip, $jabatan);
                    $stmt->execute();
                    $pegawai_id = $conn->insert_id;
                } else {
                    $pegawai_id = $pegawai['id'];
                }
                
                // Find kategori
                $katSafe = $conn->real_escape_string($kategori);
                $kat = $conn->query("SELECT id FROM kategori_pelatihan WHERE nama LIKE '%$katSafe%' LIMIT 1")->fetch_assoc();
                $kategori_id = $kat ? $kat['id'] : null;
                
                // Find or create pelatihan
                $pelSafe = $conn->real_escape_string($pelatihan_nama);
                $pel = $conn->query("SELECT id FROM pelatihan WHERE nama = '$pelSafe' LIMIT 1")->fetch_assoc();
                if (!$pel) {
                    $stmt = $conn->prepare("INSERT INTO pelatihan (nama, kategori_id) VALUES (?, ?)");
                    $stmt->bind_param("si", $pelatihan_nama, $kategori_id);
                    $stmt->execute();
                    $pelatihan_id = $conn->insert_id;
                } else {
                    $pelatihan_id = $pel['id'];
                }
                
                // Parse realisasi date
                $pelaksanaan = null;
                if (!empty($realisasi)) {
                    $date = date_create_from_format('d/m/Y', $realisasi) ?: date_create_from_format('Y-m-d', $realisasi);
                    if ($date) $pelaksanaan = $date->format('Y-m-d');
                }
                
                // Insert monitoring
                $stmt = $conn->prepare("INSERT INTO monitoring_pelatihan (pegawai_id, pelatihan_id, tahun, pelaksanaan, no_sertifikat, jumlah_jp) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->bind_param("iiiss", $pegawai_id, $pelatihan_id, $tahun, $pelaksanaan, $keterangan);
                
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $errors[] = "Gagal import: $nama - $pelatihan_nama";
                }
            }
            
            fclose($handle);
            
            if ($imported > 0) {
                $message = "$imported data berhasil diimport!";
                $messageType = 'success';
            }
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " data gagal.";
                $messageType = 'warning';
            }
        } else {
            $message = 'Gagal upload file';
            $messageType = 'danger';
        }
    }
}

$pegawaiList = $conn->query("SELECT * FROM pegawai ORDER BY nama");
$pelatihanList = $conn->query("SELECT * FROM pelatihan ORDER BY nama");
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <div>
        <p class="text-xs text-gray-500 mb-1">Import Data Pelatihan</p>
        <h1 class="text-xl font-bold text-gray-800" style="color:#1a365d;">IMPORT DATA</h1>
    </div>
    <a href="../index.php" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Beranda
    </a>
</div>

<?php if($message): ?>
<div class="mb-4 px-4 py-3 rounded-lg text-sm <?= $messageType == 'success' ? 'bg-green-100 text-green-700' : ($messageType == 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') ?>">
    <?= $message ?>
</div>
<?php endif; ?>

<!-- Sync Otomatis Banner -->
<div class="mb-6 p-4 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg text-white">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="font-semibold text-lg flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Sync Otomatis dengan Google Sheets
            </h3>
            <p class="text-blue-100 text-sm mt-1">Hubungkan langsung dengan spreadsheet Google Form tanpa perlu download CSV manual</p>
            <a href="https://forms.gle/gtAyX37spwN6FqdJ7" target="_blank" class="inline-flex items-center gap-1 text-blue-100 hover:text-white text-xs mt-2 underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                Isi Google Form
            </a>
        </div>
        <a href="sync_gsheet.php" class="px-4 py-2 bg-white text-blue-600 rounded-lg font-medium hover:bg-blue-50 transition">
            Setup Sync →
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Import dari CSV -->
    <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
            <h2 class="font-semibold text-white text-sm">Import Manual dari CSV</h2>
        </div>
        <div class="p-4">
            <div class="mb-4 p-3 bg-gray-50 rounded-lg text-xs">
                <p class="font-medium mb-1">Format kolom:</p>
                <code class="text-gray-600">Nama, NIP, Jabatan, Kategori, Nama Pelatihan, Tahun, Realisasi, No Sertifikat</code>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv">
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Pilih File CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded">
                </div>
                <button type="submit" class="w-full px-4 py-2 text-sm text-white rounded hover:opacity-90" style="background:#005BAC;">
                    Import Data
                </button>
            </form>
        </div>
    </div>
    
    <!-- Download Template -->
    <div class="bg-white rounded-lg border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200" style="background:#1a365d;">
            <h2 class="font-semibold text-white text-sm">Download Template</h2>
        </div>
        <div class="p-4">
            <p class="text-sm text-gray-600 mb-4">Download template CSV untuk memudahkan input data secara manual:</p>
            <a href="../api/template.php" class="inline-flex items-center gap-1 px-4 py-2 text-sm text-white rounded" style="background:#059669;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Download Template CSV
            </a>
            
            <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                <p class="font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Rekomendasi:
                </p>
                <p class="mt-1">Gunakan fitur <a href="sync_gsheet.php" class="underline font-medium">Sinkronisasi Otomatis</a> untuk mengambil data langsung dari Google Sheets tanpa perlu download manual.</p>
            </div>
        </div>
    </div>
</div>

<?php $conn->close(); require_once '../includes/footer.php'; ?>
