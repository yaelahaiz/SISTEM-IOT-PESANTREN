<?php
/**
 * Database Configuration
 * Koneksi ke database MySQL menggunakan MySQLi
 * 
 * @package Riyadul Muta'alimin
 */

require_once __DIR__ . '/app.php';

// Konfigurasi database
if (isProductionHost()) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'arndilh2_monitoringpesantrenrm');
    define('DB_PASS', 'Done12345@@##');
    define('DB_NAME', 'arndilh2_monitoringpesantrenrm');
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'iot_pesantren');
}
define('DB_CHARSET', 'utf8mb4');

// Buat koneksi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    // Log error (jangan tampilkan detail ke user di production)
    error_log("Database connection failed: " . $conn->connect_error);
    $importFile = isProductionHost() ? 'database/iot_pesantren_hosting.sql' : 'database/iot_pesantren.sql';
    
    // Tampilkan pesan umum
    if (php_sapi_name() === 'cli') {
        die("Koneksi database gagal. Periksa konfigurasi database.\n");
    } else {
        die("<div style='padding:20px;font-family:Arial;color:#dc2626;'>
            <h3>Koneksi Database Gagal</h3>
            <p>Pastikan MySQL sudah berjalan dan database '<strong>" . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . "</strong>' sudah dibuat.</p>
            <p>Import file <code>" . htmlspecialchars($importFile, ENT_QUOTES, 'UTF-8') . "</code> ke phpMyAdmin.</p>
        </div>");
    }
}

// Set charset
$conn->set_charset(DB_CHARSET);

// Set timezone
$conn->query("SET time_zone = '+07:00'");

