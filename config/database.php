<?php
/**
 * SIMARIS - Konfigurasi Database
 */

// === Konfigurasi Database Hosting InfinityFree ===
define('DB_HOST',    'sql301.infinityfree.com');
define('DB_USER',    'if0_42178113');
define('DB_PASS',    'simaris123');
define('DB_NAME',    'if0_42178113_simaris');
define('DB_CHARSET', 'utf8mb4');

// === Konfigurasi Aplikasi ===
define('APP_NAME',      'SIMARIS');
define('APP_FULL_NAME', 'Sistem Informasi Manajemen Risiko');
define('APP_URL',       'https://simaris-app.infinityfreeapp.com/simaris');
define('APP_VERSION',   '1.0.0');

date_default_timezone_set('Asia/Jakarta');

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Mendapatkan koneksi PDO ke database SIMARIS
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;

    } catch (PDOException $e) {
        die("Koneksi Database Gagal: " . $e->getMessage());
    }
}