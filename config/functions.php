<?php
/**
 * Helper Functions
 * Fungsi-fungsi pembantu untuk sistem IoT Pesantren
 * 
 * @package IoT Pesantren
 */

/**
 * Sanitasi input dari user
 * @param string $data Input data
 * @return string Data yang sudah disanitasi
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Format angka dengan desimal
 * @param float $number Angka
 * @param int $decimals Jumlah desimal
 * @return string Angka terformat
 */
function formatNumber($number, $decimals = 2) {
    if ($number === null || $number === '') return '-';
    return number_format((float)$number, $decimals, ',', '.');
}

/**
 * Format rupiah
 * @param float $number Angka
 * @return string Format rupiah
 */
function formatRupiah($number) {
    if ($number === null) return 'Rp 0';
    return 'Rp ' . number_format((float)$number, 0, ',', '.');
}

/**
 * Waktu yang lalu (relative time)
 * @param string $datetime Datetime string
 * @return string Waktu relatif
 */
function timeAgo($datetime) {
    if (empty($datetime)) return 'Tidak diketahui';
    
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}

/**
 * Ambil data terbaru panel surya
 * @param mysqli $conn
 * @return array|null
 */
function getLatestSolarData($conn) {
    $result = $conn->query("SELECT * FROM solar_monitoring ORDER BY recorded_at DESC LIMIT 1");
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Ambil data terbaru rumah pengering
 * @param mysqli $conn
 * @return array|null
 */
function getLatestDryerData($conn) {
    $result = $conn->query("SELECT * FROM dryer_monitoring ORDER BY recorded_at DESC LIMIT 1");
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Ambil data terbaru kandang sapi
 * @param mysqli $conn
 * @return array|null
 */
function getLatestCattleData($conn) {
    $result = $conn->query("SELECT * FROM cattle_monitoring ORDER BY recorded_at DESC LIMIT 1");
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Ambil data terbaru permaculture
 * @param mysqli $conn
 * @return array|null
 */
function getLatestPermacultureData($conn) {
    $result = $conn->query("SELECT * FROM permaculture_monitoring ORDER BY recorded_at DESC LIMIT 1");
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Ambil nilai setting
 * @param mysqli $conn
 * @param string $key Setting key
 * @return string|null Setting value
 */
function getSetting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['setting_value'] : null;
}

/**
 * Update nilai setting
 * @param mysqli $conn
 * @param string $key Setting key
 * @param string $value Setting value
 * @return bool
 */
function updateSetting($conn, $key, $value) {
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param("ss", $value, $key);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

/**
 * Validasi API key
 * @param mysqli $conn
 * @param string $apiKey API key dari request
 * @return bool Valid atau tidak
 */
function validateApiKey($conn, $apiKey) {
    if (empty($apiKey)) return false;
    
    // Cek di tabel settings
    $stmt = $conn->prepare("SELECT setting_key FROM settings WHERE setting_value = ? AND setting_key LIKE 'api_key_%'");
    $stmt->bind_param("s", $apiKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $valid = $result->num_rows > 0;
    $stmt->close();
    
    if ($valid) return true;
    
    // Cek di tabel devices
    $stmt = $conn->prepare("SELECT id FROM devices WHERE api_key = ? AND is_active = 1");
    $stmt->bind_param("s", $apiKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $valid = $result->num_rows > 0;
    $stmt->close();
    
    return $valid;
}

/**
 * Hitung volume biodigester (silinder)
 * V = π × (d/2)² × h / 1000 (dalam Liter)
 * 
 * @param float $height_cm Tinggi tabung (cm)
 * @param float $diameter_cm Diameter tabung (cm)
 * @param float $liquid_level_cm Tinggi cairan (cm)
 * @return float Volume dalam Liter
 */
function calculateBiodigesterVolume($height_cm, $diameter_cm, $liquid_level_cm) {
    $radius = $diameter_cm / 2;
    $volume_cm3 = M_PI * pow($radius, 2) * $liquid_level_cm;
    $volume_liter = $volume_cm3 / 1000;
    return round($volume_liter, 2);
}

/**
 * Tentukan status berdasarkan nilai sensor
 * @param float $value Nilai sensor
 * @param float $min_warning Batas bawah warning
 * @param float $max_warning Batas atas warning
 * @param float $min_danger Batas bawah danger
 * @param float $max_danger Batas atas danger
 * @return string 'normal', 'warning', atau 'danger'
 */
function getStatusClass($value, $min_warning = null, $max_warning = null, $min_danger = null, $max_danger = null) {
    if ($value === null) return 'normal';
    
    if ($min_danger !== null && $value < $min_danger) return 'danger';
    if ($max_danger !== null && $value > $max_danger) return 'danger';
    if ($min_warning !== null && $value < $min_warning) return 'warning';
    if ($max_warning !== null && $value > $max_warning) return 'warning';
    
    return 'normal';
}

/**
 * Export data ke CSV
 * @param array $data Array data
 * @param string $filename Nama file
 */
function exportToCSV($data, $filename) {
    if (empty($data)) return;
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // BOM untuk Excel
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Header
    fputcsv($output, array_keys($data[0]));
    
    // Data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

/**
 * Dapatkan base URL project
 * @return string Base URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host . '/SISTEM IOT PESANTREN';
}

/**
 * Map nilai dari satu range ke range lain
 * @param float $value Nilai input
 * @param float $fromLow Batas bawah input
 * @param float $fromHigh Batas atas input
 * @param float $toLow Batas bawah output
 * @param float $toHigh Batas atas output
 * @return float Nilai output
 */
function mapValue($value, $fromLow, $fromHigh, $toLow, $toHigh) {
    $mapped = ($value - $fromLow) * ($toHigh - $toLow) / ($fromHigh - $fromLow) + $toLow;
    return max($toLow, min($toHigh, $mapped));
}
