<?php
require_once 'config/database.php';

$solarQuery = $conn->query("SELECT temperature, voltage, DATE_FORMAT(recorded_at, '%H:%i') as time_label FROM solar_monitoring ORDER BY id DESC LIMIT 15");
$solarLabels = []; $solarTemps = [];
if ($solarQuery) {
    while($row = $solarQuery->fetch_assoc()) {
        array_unshift($solarLabels, $row['time_label']);
        array_unshift($solarTemps, floatval($row['temperature']));
    }
}
echo "Labels: " . json_encode($solarLabels) . "\n";
echo "Temps: " . json_encode($solarTemps) . "\n";
?>