<?php
/**
 * API: Insert Dryer House Data
 * Menerima data monitoring rumah pengering dari ESP32
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

$temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
$humidity = isset($_POST['humidity']) ? floatval($_POST['humidity']) : null;
$voltage_ac = isset($_POST['voltage_ac']) ? floatval($_POST['voltage_ac']) : null;
$current_ac = isset($_POST['current_ac']) ? floatval($_POST['current_ac']) : null;
$power_ac = isset($_POST['power_ac']) ? floatval($_POST['power_ac']) : null;
$energy_ac = isset($_POST['energy_ac']) ? floatval($_POST['energy_ac']) : null;
$frequency = isset($_POST['frequency']) ? floatval($_POST['frequency']) : null;
$power_factor = isset($_POST['power_factor']) ? floatval($_POST['power_factor']) : null;
$relay_lamp = isset($_POST['relay_lamp']) ? intval($_POST['relay_lamp']) : 0;
$device_id = isset($_POST['device_id']) ? intval($_POST['device_id']) : null;

if ($temperature === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Temperature harus diisi']);
    exit;
}

if ($power_ac === null && $voltage_ac !== null && $current_ac !== null) {
    $power_ac = round($voltage_ac * $current_ac, 2);
}

$stmt = $conn->prepare("INSERT INTO dryer_monitoring (device_id, temperature, humidity, voltage_ac, current_ac, power_ac, energy_ac, frequency, power_factor, relay_lamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iddddddddi", $device_id, $temperature, $humidity, $voltage_ac, $current_ac, $power_ac, $energy_ac, $frequency, $power_factor, $relay_lamp);

if ($stmt->execute()) {
    if ($device_id) {
        $conn->query("UPDATE devices SET last_seen = NOW() WHERE id = $device_id");
    }
    echo json_encode(['status' => 'success', 'message' => 'Data rumah pengering berhasil disimpan', 'id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
}

$stmt->close();
$conn->close();
