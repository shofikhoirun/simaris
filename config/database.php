<?php
/**
 * SIMARIS - Konfigurasi Database
 * Koneksi PDO ke MySQL/MariaDB (Laragon default)
 */

// === Konfigurasi Database (sesuaikan jika perlu) ===
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');                 // Laragon default kosong
define('DB_NAME',    'simaris_db');
define('DB_CHARSET', 'utf8mb4');

// === Konfigurasi Aplikasi ===
define('APP_NAME',      'SIMARIS');
define('APP_FULL_NAME', 'Sistem Informasi Manajemen Risiko');
define('APP_URL',       'http://localhost/simaris');
define('APP_VERSION',   '1.0.0');

date_default_timezone_set('Asia/Jakarta');

// Error reporting (matikan di production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Mendapatkan koneksi PDO ke database SIMARIS.
 * Digunakan via wrapper db() di includes/functions.php
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
        die("
        <div style='font-family:Arial,sans-serif;padding:40px;background:#f5f5f5;text-align:center;max-width:600px;margin:60px auto;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1)'>
            <h2 style='color:#d32f2f;margin:0 0 12px'>⚠️ Koneksi Database Gagal</h2>
            <p style='color:#555'>" . htmlspecialchars($e->getMessage()) . "</p>
            <hr style='margin:20px 0;border:0;border-top:1px solid #ddd'>
            <p><strong>Solusi:</strong></p>
            <ol style='text-align:left;display:inline-block;color:#666'>
                <li>Pastikan <b>Apache</b> dan <b>MySQL</b> aktif di Laragon (klik Start All)</li>
                <li>Pastikan database <b>" . DB_NAME . "</b> sudah dibuat di phpMyAdmin</li>
                <li>Import file <code>database/simaris.sql</code></li>
                <li>Periksa konfigurasi di <code>config/database.php</code></li>
            </ol>
        </div>");
    }
}
