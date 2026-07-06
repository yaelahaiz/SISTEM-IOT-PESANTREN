<?php
/**
 * API: Get Latest Monitoring Data
 * Endpoint public untuk AJAX refresh dashboard
 * 
 * GET parameter: type (solar, dryer, cattle, permaculture, all)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

$type = isset($_GET['type']) ? strtolower($_GET['type']) : 'all';
$response = ['status' => 'success', 'data' => []];

if ($type === 'solar' || $type === 'all') {
    $response['data']['solar'] = getLatestSolarData($conn);
}

if ($type === 'dryer' || $type === 'all') {
    $response['data']['dryer'] = getLatestDryerData($conn);
}

if ($type === 'cattle' || $type === 'all') {
    $response['data']['cattle'] = getLatestCattleData($conn);
}

if ($type === 'permaculture' || $type === 'all') {
    $response['data']['permaculture'] = getLatestPermacultureData($conn);
}

echo json_encode($response);
$conn->close();
