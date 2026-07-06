<?php
/**
 * API: Insert Solar Panel Data
 * Menerima data monitoring panel surya dari ESP32
 * 
 * Method: POST
 * Header: X-API-Key atau parameter api_key
 * Body: temperature, humidity, voltage, current_amp, power, energy, device_id
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

// Validasi API Key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';
if (!validateApiKey($conn, $apiKey)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid API key']);
    exit;
}

// Ambil data
$temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
$humidity = isset($_POST['humidity']) ? floatval($_POST['humidity']) : null;
$voltage = isset($_POST['voltage']) ? floatval($_POST['voltage']) : null;
$current_amp = isset($_POST['current_amp']) ? floatval($_POST['current_amp']) : null;
$power = isset($_POST['power']) ? floatval($_POST['power']) : null;
$energy = isset($_POST['energy']) ? floatval($_POST['energy']) : null;
$device_id = isset($_POST['device_id']) ? intval($_POST['device_id']) : null;

// Validasi: minimal temperature atau voltage harus ada
if ($temperature === null && $voltage === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Minimal temperature atau voltage harus diisi']);
    exit;
}

// Hitung power jika tidak disediakan
if ($power === null && $voltage !== null && $current_amp !== null) {
    $power = round($voltage * $current_amp, 2);
}

// Insert data
$stmt = $conn->prepare("INSERT INTO solar_monitoring (device_id, temperature, humidity, voltage, current_amp, power, energy) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("idddddd", $device_id, $temperature, $humidity, $voltage, $current_amp, $power, $energy);

if ($stmt->execute()) {
    $insertId = $stmt->insert_id;
    
    // Update last_seen device
    if ($device_id) {
        $conn->query("UPDATE devices SET last_seen = NOW() WHERE id = $device_id");
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Data panel surya berhasil disimpan',
        'id' => $insertId
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
}

$stmt->close();
$conn->close();
