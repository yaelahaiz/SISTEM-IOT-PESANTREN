<?php
/**
 * API: Insert Permaculture Data
 * Menerima data pH tanah dari ESP32 node permaculture
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

$soil_ph = isset($_POST['soil_ph']) ? floatval($_POST['soil_ph']) : null;
$device_id = isset($_POST['device_id']) ? intval($_POST['device_id']) : null;

if ($soil_ph === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'soil_ph harus diisi']);
    exit;
}

if ($soil_ph < 0 || $soil_ph > 14) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'pH harus antara 0-14']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO permaculture_monitoring (device_id, soil_ph) VALUES (?, ?)");
$stmt->bind_param("id", $device_id, $soil_ph);

if ($stmt->execute()) {
    if ($device_id) {
        $conn->query("UPDATE devices SET last_seen = NOW() WHERE id = $device_id");
    }
    echo json_encode(['status' => 'success', 'message' => 'Data permaculture berhasil disimpan', 'id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
}

$stmt->close();
$conn->close();
