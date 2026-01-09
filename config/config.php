<?php
/**
 * Konfigurasi Aplikasi
 * 
 * Environment Variables Priority:
 * 1. System Environment (Wasmer/Hosting)
 * 2. .env file (Local Development)
 * 3. Default values
 */

// Load .env file jika ada (untuk local development)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // Skip comments
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!getenv($key)) {
            putenv("$key=$value");
        }
    }
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'monitor_pelatihan_pegawai');
define('DB_USER', getenv('DB_USERNAME') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');

// App Configuration
define('APP_ENV', getenv('APP_ENV') ?: 'local');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:3000');

// Base Path
define('BASE_PATH', dirname(__DIR__) . '/');

/**
 * Get Database Connection
 */
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
        
        if ($conn->connect_error) {
            if (APP_DEBUG) {
                die("Koneksi gagal: " . $conn->connect_error . 
                    "<br>Host: " . DB_HOST . 
                    "<br>Port: " . DB_PORT . 
                    "<br>Database: " . DB_NAME);
            } else {
                die("Koneksi database gagal. Silakan hubungi administrator.");
            }
        }
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

/**
 * Helper Functions
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function alert($message, $type = 'success') {
    $_SESSION['alert'] = ['message' => $message, 'type' => $type];
}

/**
 * Check if running in production
 */
function isProduction() {
    return APP_ENV === 'production' || APP_ENV === 'prod';
}

/**
 * Debug helper - only works in development
 */
function dd($data) {
    if (APP_DEBUG) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        die();
    }
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authentication Functions
 */

// Halaman yang bisa diakses guest (tanpa login)
define('GUEST_PAGES', ['jadwal', 'monitoring', 'kalender', 'index']);

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'nama' => $_SESSION['user_nama'],
        'role' => $_SESSION['user_role']
    ];
}

function login($username, $password) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_nama'] = $user['nama'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    session_start();
}

function requireAdmin($redirectTo = '../pages/login.php') {
    if (!isAdmin()) {
        alert('Anda harus login sebagai admin untuk mengakses halaman ini', 'danger');
        redirect($redirectTo);
    }
}

function canAccessPage($pageName) {
    // Admin bisa akses semua
    if (isAdmin()) return true;
    
    // Guest hanya bisa akses halaman tertentu
    return in_array($pageName, GUEST_PAGES);
}
