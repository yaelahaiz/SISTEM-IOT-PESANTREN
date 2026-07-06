<?php
/**
 * API: Update Relay Status
 * Admin atau ESP32 mengubah status relay
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Cek auth: admin session ATAU API key
$authorized = false;
if (isLoggedIn()) {
    $authorized = true;
} else {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';
    if (validateApiKey($conn, $apiKey)) {
        $authorized = true;
    }
}

if (!$authorized) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$relay_id = isset($_POST['relay_id']) ? intval($_POST['relay_id']) : null;
$status = isset($_POST['status']) ? intval($_POST['status']) : null;

if ($relay_id === null || $status === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'relay_id dan status harus diisi']);
    exit;
}

$status = ($status == 1) ? 1 : 0;

$stmt = $conn->prepare("UPDATE relay_control SET status = ? WHERE id = ?");
$stmt->bind_param("ii", $status, $relay_id);

if ($stmt->execute()) {
    $statusText = $status ? 'ON' : 'OFF';
    echo json_encode(['status' => 'success', 'message' => "Relay berhasil di-$statusText"]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah relay']);
}

$stmt->close();
$conn->close();
