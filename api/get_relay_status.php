<?php
/**
 * API: Get Relay Status
 * ESP32 gateway membaca status relay dari server
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
if (!validateApiKey($conn, $apiKey)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid API key']);
    exit;
}

$relayId = isset($_GET['relay_id']) ? intval($_GET['relay_id']) : null;

if ($relayId) {
    $stmt = $conn->prepare("SELECT id, relay_name, relay_pin, status, device_id, updated_at FROM relay_control WHERE id = ?");
    $stmt->bind_param("i", $relayId);
    $stmt->execute();
    $result = $stmt->get_result();
    $relays = [];
    while ($row = $result->fetch_assoc()) {
        $relays[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query("SELECT id, relay_name, relay_pin, status, device_id, updated_at FROM relay_control");
    $relays = [];
    while ($row = $result->fetch_assoc()) {
        $relays[] = $row;
    }
}

echo json_encode(['status' => 'success', 'relays' => $relays]);
$conn->close();
