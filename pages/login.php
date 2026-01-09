<?php
require_once '../config/config.php';

// Jika sudah login, redirect ke home
if (isLoggedIn()) {
    redirect('../');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } elseif (login($username, $password)) {
        redirect('../');
    } else {
        $error = 'Username atau password salah';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Monitoring Pelatihan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-8" style="background: linear-gradient(135deg, #1a365d 0%, #005BAC 100%);">
                <div class="text-center">
                    <img src="../assets/BADAN_POM.png" alt="Logo" class="h-16 mx-auto mb-3">
                    <h1 class="text-xl font-bold text-white">Monitoring Pelatihan</h1>
                    <p class="text-blue-200 text-sm mt-1">Silakan login untuk melanjutkan</p>
                </div>
            </div>
            
            <form method="POST" class="p-6">
                <?php if($error): ?>
                <div class="mb-4 px-4 py-3 rounded text-sm" style="background:#fee2e2; color:#dc2626;">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['alert'])): ?>
                <div class="mb-4 px-4 py-3 rounded text-sm" style="background:<?= $_SESSION['alert']['type'] === 'danger' ? '#fee2e2' : '#dcfce7' ?>; color:<?= $_SESSION['alert']['type'] === 'danger' ? '#dc2626' : '#16a34a' ?>;">
                    <?= $_SESSION['alert']['message'] ?>
                </div>
                <?php unset($_SESSION['alert']); endif; ?>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" required autofocus
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Masukkan username">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Masukkan password">
                </div>
                
                <button type="submit" class="w-full py-2.5 text-white font-medium rounded-lg hover:opacity-90 transition" style="background:#005BAC;">
                    Login
                </button>
                
                <div class="mt-4 text-center">
                    <a href="../" class="text-sm text-gray-500 hover:text-blue-600">← Kembali ke Beranda</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
