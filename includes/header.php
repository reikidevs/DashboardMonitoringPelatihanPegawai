<?php 
// Determine base path based on current file location
$currentDir = dirname($_SERVER['PHP_SELF']);
$isInPages = strpos($currentDir, '/pages') !== false;
$isInApi = strpos($currentDir, '/api') !== false;
$basePath = ($isInPages || $isInApi) ? '../' : '';
$homeUrl = $basePath ?: './';

require_once $basePath . 'config/config.php';

// Cek akses halaman
$currentFile = basename($_SERVER['PHP_SELF'], '.php');
$isGuestPage = in_array($currentFile, GUEST_PAGES);

// Halaman yang butuh admin
$adminOnlyPages = ['pegawai', 'pegawai_detail', 'pelatihan', 'kewajiban', 'realisasi', 'laporan', 'import_gsheet', 'sync_gsheet', 'settings_sync'];

if (in_array($currentFile, $adminOnlyPages) && !isAdmin()) {
    alert('Anda harus login sebagai admin untuk mengakses halaman ini', 'danger');
    redirect($basePath . 'pages/login.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Monitoring Pelatihan Pegawai - BPOM</title>
    <script src="https://cdn.tailwindcss.com?v=<?= time() ?>"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'bpom-blue': '#005BAC',
                        'bpom-blue-dark': '#004a8c',
                        'bpom-blue-light': '#e6f0fa',
                        'bpom-green': '#00A651',
                        'bpom-green-dark': '#008a43',
                        'bpom-green-light': '#e6f7ed',
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex justify-between items-center h-16">
                <a href="<?= $homeUrl ?>" class="flex items-center gap-3 hover:opacity-80 transition">
                    <img src="<?= $basePath ?>assets/BADAN_POM.png" alt="Logo BPOM" class="h-10 w-auto">
                    <span class="font-bold text-lg text-gray-900 hidden md:block">Monitoring Pelatihan Pegawai</span>
                </a>
                <div class="flex items-center gap-2">
                    <?php 
                    $currentPage = basename($_SERVER['PHP_SELF']);
                    
                    // Menu untuk guest
                    $guestMenu = [
                        ['url' => $homeUrl, 'label' => 'Beranda', 'file' => 'index.php'],
                        ['url' => $basePath . 'pages/jadwal.php', 'label' => 'Jadwal', 'file' => 'jadwal.php'],
                        ['url' => $basePath . 'pages/kalender.php', 'label' => 'Kalender', 'file' => 'kalender.php'],
                        ['url' => $basePath . 'pages/monitoring.php', 'label' => 'Monitoring', 'file' => 'monitoring.php'],
                    ];
                    
                    // Menu tambahan untuk admin
                    $adminMenu = [
                        ['url' => $basePath . 'pages/pegawai.php', 'label' => 'Pegawai', 'file' => 'pegawai.php'],
                        ['url' => $basePath . 'pages/pelatihan.php', 'label' => 'Pelatihan', 'file' => 'pelatihan.php'],
                        ['url' => $basePath . 'pages/laporan.php', 'label' => 'Laporan', 'file' => 'laporan.php'],
                    ];
                    
                    // Tampilkan menu guest
                    foreach($guestMenu as $item):
                        $isActive = $currentPage == $item['file'];
                    ?>
                    <a href="<?= $item['url'] ?>" class="px-3 py-2 rounded-lg text-sm font-medium transition-all <?= $isActive ? 'text-white' : 'text-gray-600 hover:bg-gray-100' ?>" <?= $isActive ? 'style="background-color: #005BAC;"' : '' ?>>
                        <?= $item['label'] ?>
                    </a>
                    <?php endforeach; ?>
                    
                    <?php if(isAdmin()): ?>
                    <!-- Separator -->
                    <span class="text-gray-300">|</span>
                    
                    <!-- Menu Admin -->
                    <?php foreach($adminMenu as $item):
                        $isActive = $currentPage == $item['file'];
                    ?>
                    <a href="<?= $item['url'] ?>" class="px-3 py-2 rounded-lg text-sm font-medium transition-all <?= $isActive ? 'text-white' : 'text-gray-600 hover:bg-gray-100' ?>" <?= $isActive ? 'style="background-color: #005BAC;"' : '' ?>>
                        <?= $item['label'] ?>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Login/Logout -->
                    <span class="text-gray-300 ml-2">|</span>
                    <?php if(isLoggedIn()): ?>
                    <div class="flex items-center gap-2 ml-2">
                        <span class="text-xs text-gray-500"><?= htmlspecialchars($_SESSION['user_nama'] ?? $_SESSION['username']) ?></span>
                        <a href="<?= $basePath ?>pages/logout.php" class="px-3 py-1.5 text-xs rounded border border-gray-300 text-gray-600 hover:bg-gray-100">Logout</a>
                    </div>
                    <?php else: ?>
                    <a href="<?= $basePath ?>pages/login.php" class="ml-2 px-3 py-1.5 text-xs rounded text-white hover:opacity-90" style="background:#005BAC;">Login Admin</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
    <?php if (isset($_SESSION['alert'])): ?>
        <?php 
        $alertType = $_SESSION['alert']['type'];
        $alertColors = [
            'success' => 'background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;',
            'danger' => 'background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca;',
            'warning' => 'background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;',
            'info' => 'background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe;'
        ];
        ?>
        <div class="mb-5 px-4 py-3 rounded-lg flex items-center gap-3 text-sm" style="<?= $alertColors[$alertType] ?? $alertColors['info'] ?>">
            <?php if($alertType == 'success'): ?>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <?php elseif($alertType == 'danger'): ?>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <?php else: ?>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php endif; ?>
            <span><?= $_SESSION['alert']['message'] ?></span>
        </div>
        <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>
