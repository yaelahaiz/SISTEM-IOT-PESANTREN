<?php
/**
 * Database Configuration
 * Koneksi ke database MySQL menggunakan MySQLi
 * 
 * @package IoT Pesantren
 */

// Konfigurasi database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'iot_pesantren');
define('DB_CHARSET', 'utf8mb4');

// Buat koneksi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    // Log error (jangan tampilkan detail ke user di production)
    error_log("Database connection failed: " . $conn->connect_error);
    
    // Tampilkan pesan umum
    if (php_sapi_name() === 'cli') {
        die("Koneksi database gagal. Periksa konfigurasi database.\n");
    } else {
        die("<div style='padding:20px;font-family:Arial;color:#dc2626;'>
            <h3>Koneksi Database Gagal</h3>
            <p>Pastikan MySQL sudah berjalan dan database 'iot_pesantren' sudah dibuat.</p>
            <p>Import file <code>database/iot_pesantren.sql</code> ke phpMyAdmin.</p>
        </div>");
    }
}

// Set charset
$conn->set_charset(DB_CHARSET);

// Set timezone
$conn->query("SET time_zone = '+07:00'");
