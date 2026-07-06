<?php
/**
 * API: Insert Cattle / Biodigester Data
 * Menerima data monitoring kandang sapi dan biodigester dari ESP32
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';
if (!validateApiKey($conn, $apiKey)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid API key']);
    exit;
}

$liquid_level = isset($_POST['liquid_level']) ? floatval($_POST['liquid_level']) : null;
$liquid_volume = isset($_POST['liquid_volume']) ? floatval($_POST['liquid_volume']) : null;
$gas_pressure = isset($_POST['gas_pressure']) ? floatval($_POST['gas_pressure']) : null;
$soil_moisture_raw = isset($_POST['soil_moisture_raw']) ? intval($_POST['soil_moisture_raw']) : null;
$soil_moisture_percent = isset($_POST['soil_moisture_percent']) ? floatval($_POST['soil_moisture_percent']) : null;
$device_id = isset($_POST['device_id']) ? intval($_POST['device_id']) : null;

// Minimal satu data harus ada
if ($liquid_level === null && $gas_pressure === null && $soil_moisture_raw === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Minimal satu data sensor harus diisi']);
    exit;
}

// Hitung volume jika tidak disediakan
if ($liquid_volume === null && $liquid_level !== null) {
    $height = floatval(getSetting($conn, 'biodigester_height') ?: 150);
    $diameter = floatval(getSetting($conn, 'biodigester_diameter') ?: 100);
    $liquid_volume = calculateBiodigesterVolume($height, $diameter, $liquid_level);
}

// Hitung persentase kelembaban tanah
if ($soil_moisture_percent === null && $soil_moisture_raw !== null) {
    $dryVal = intval(getSetting($conn, 'soil_dry_value') ?: 4095);
    $wetVal = intval(getSetting($conn, 'soil_wet_value') ?: 1000);
    $soil_moisture_percent = round(mapValue($soil_moisture_raw, $dryVal, $wetVal, 0, 100), 1);
}

$stmt = $conn->prepare("INSERT INTO cattle_monitoring (device_id, liquid_level, liquid_volume, gas_pressure, soil_moisture_raw, soil_moisture_percent) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("idddid", $device_id, $liquid_level, $liquid_volume, $gas_pressure, $soil_moisture_raw, $soil_moisture_percent);

if ($stmt->execute()) {
    if ($device_id) {
        $conn->query("UPDATE devices SET last_seen = NOW() WHERE id = $device_id");
    }
    echo json_encode(['status' => 'success', 'message' => 'Data kandang sapi berhasil disimpan', 'id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
}

$stmt->close();
$conn->close();
