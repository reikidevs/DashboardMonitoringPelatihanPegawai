<?php
// Konfigurasi Database
// Menggunakan environment variables dari Wasmer/hosting, atau default untuk local
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USERNAME') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'monitor_pelatihan_pegawai');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

// Base Path
define('BASE_PATH', dirname(__DIR__) . '/');
define('BASE_URL', '/');

// Koneksi Database
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Helper Functions
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

session_start();
