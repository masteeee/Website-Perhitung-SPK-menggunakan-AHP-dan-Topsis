<?php
/**
 * Konfigurasi Database dan Helper SPK Beasiswa AHP - TOPSIS
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'spk_beasiswa';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . htmlspecialchars($e->getMessage()));
}

// Base URL helper
function base_url($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Tentukan direktori relatif
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $base = rtrim($scriptDir, '/\\');
    
    // Jika path diawali dengan /, bersihkan
    $path = ltrim($path, '/');
    return $base ? "{$base}/{$path}" : "/{$path}";
}

// Cek autentikasi admin
function check_login() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk mengakses sistem.';
        header('Location: login.php');
        exit;
    }
}

// Flash message helper
function set_flash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function get_flash($type) {
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}

// Format Rupiah
function format_rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// Format Angka Desimal
function format_desimal($angka, $precision = 4) {
    return number_format((float)$angka, $precision, ',', '.');
}

// Random Index (RI) Saaty untuk AHP
function get_saaty_ri($n) {
    $ri_table = [
        1 => 0.00,
        2 => 0.00,
        3 => 0.58,
        4 => 0.90,
        5 => 1.12,
        6 => 1.24,
        7 => 1.32,
        8 => 1.41,
        9 => 1.45,
        10 => 1.49,
        11 => 1.51,
        12 => 1.48,
        13 => 1.56,
        14 => 1.57,
        15 => 1.59
    ];
    return $ri_table[$n] ?? 1.59;
}
