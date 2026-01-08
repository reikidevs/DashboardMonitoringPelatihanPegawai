<?php 
// Determine base path based on current file location
$currentDir = dirname($_SERVER['PHP_SELF']);
$isInPages = strpos($currentDir, '/pages') !== false;
$isInApi = strpos($currentDir, '/api') !== false;
$basePath = ($isInPages || $isInApi) ? '../' : '';
$homeUrl = $basePath ?: './';

require_once $basePath . 'config/config.php'; 
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
                    <span class="font-bold text-lg text-gray-900">Monitoring Pelatihan Pegawai BPOM</span>
                </a>
                <div class="flex gap-1">
                    <?php 
                    $currentPage = basename($_SERVER['PHP_SELF']);
                    $pagesPath = $isInPages ? '' : 'pages/';
                    $navItems = [
                        ['url' => $homeUrl, 'label' => 'Beranda', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'file' => 'index.php'],
                        ['url' => $basePath . $pagesPath . 'pegawai.php', 'label' => 'Pegawai', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z', 'file' => 'pegawai.php'],
                        ['url' => $basePath . $pagesPath . 'pelatihan.php', 'label' => 'Pelatihan', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'file' => 'pelatihan.php'],
                        ['url' => $basePath . $pagesPath . 'jadwal.php', 'label' => 'Jadwal', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'file' => 'jadwal.php'],
                        ['url' => $basePath . $pagesPath . 'kalender.php', 'label' => 'Kalender', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'file' => 'kalender.php'],
                        ['url' => $basePath . $pagesPath . 'monitoring.php', 'label' => 'Monitoring', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', 'file' => 'monitoring.php'],
                        ['url' => $basePath . $pagesPath . 'laporan.php', 'label' => 'Laporan', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'file' => 'laporan.php'],
                    ];
                    foreach($navItems as $item):
                        $isActive = $currentPage == $item['file'];
                    ?>
                    <a href="<?= $item['url'] ?>" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all <?= $isActive ? 'text-white' : 'text-gray-600 hover:bg-gray-100' ?>" <?= $isActive ? 'style="background-color: #005BAC;"' : '' ?>>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
                        </svg>
                        <?= $item['label'] ?>
                    </a>
                    <?php endforeach; ?>
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
