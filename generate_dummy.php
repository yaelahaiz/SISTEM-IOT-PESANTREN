<?php
require_once 'config/database.php';

echo "Generating dummy data for the last 24 hours...\n";

// Bersihkan data lama agar grafik terlihat rapi
$conn->query("TRUNCATE TABLE solar_monitoring");
$conn->query("TRUNCATE TABLE dryer_monitoring");
$conn->query("TRUNCATE TABLE cattle_monitoring");
$conn->query("TRUNCATE TABLE permaculture_monitoring");

$now = time();
$records = 48; // 48 data poin (setiap 30 menit selama 24 jam)
$interval = 30 * 60; // 30 menit dalam detik

$energySolar = 1000;
$energyDryer = 5000;

for ($i = $records; $i >= 0; $i--) {
    $timestamp = $now - ($i * $interval);
    $dateStr = date('Y-m-d H:i:s', $timestamp);
    
    // 1. Solar Data
    $tempS = rand(280, 450) / 10; // 28.0 - 45.0
    $humS = rand(400, 700) / 10;  // 40.0 - 70.0
    $voltS = rand(115, 142) / 10; // 11.5 - 14.2
    $currS = rand(10, 50) / 10;   // 1.0 - 5.0
    $powS = $voltS * $currS;
    $energySolar += ($powS * 0.5); // Nambah tiap 30 menit
    
    $stmt1 = $conn->prepare("INSERT INTO solar_monitoring (device_id, temperature, humidity, voltage, current_amp, power, energy, recorded_at) VALUES (2, ?, ?, ?, ?, ?, ?, ?)");
    $stmt1->bind_param("dddddds", $tempS, $humS, $voltS, $currS, $powS, $energySolar, $dateStr);
    $stmt1->execute();
    
    // 2. Dryer Data
    $tempD = rand(400, 650) / 10; // 40.0 - 65.0
    $humD = rand(200, 400) / 10;  // 20.0 - 40.0
    $voltD = rand(2100, 2300) / 10; // 210.0 - 230.0
    $currD = rand(20, 60) / 10;   // 2.0 - 6.0
    $pf = rand(85, 99) / 100;     // 0.85 - 0.99
    $powD = $voltD * $currD * $pf;
    $energyDryer += ($powD * 0.5 / 1000); // kWh (dummy approach)
    $freq = rand(495, 505) / 10;  // 49.5 - 50.5
    $lamp = rand(0, 1);
    
    $stmt2 = $conn->prepare("INSERT INTO dryer_monitoring (device_id, temperature, humidity, voltage_ac, current_ac, power_ac, energy_ac, frequency, power_factor, relay_lamp, recorded_at) VALUES (3, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt2->bind_param("ddddddddis", $tempD, $humD, $voltD, $currD, $powD, $energyDryer, $freq, $pf, $lamp, $dateStr);
    $stmt2->execute();
    
    // 3. Cattle Data
    $levelC = rand(400, 1200) / 10; // 40.0 - 120.0 cm
    $volC = M_PI * pow(50, 2) * $levelC / 1000; // vol tabung D=100cm
    $gasC = rand(100, 500) / 10; // 10.0 - 50.0 kPa
    $moistRaw = rand(1500, 3500);
    $moistPct = rand(300, 800) / 10; // 30.0 - 80.0 %
    
    $stmt3 = $conn->prepare("INSERT INTO cattle_monitoring (device_id, liquid_level, liquid_volume, gas_pressure, soil_moisture_raw, soil_moisture_percent, recorded_at) VALUES (4, ?, ?, ?, ?, ?, ?)");
    $stmt3->bind_param("ddddds", $levelC, $volC, $gasC, $moistRaw, $moistPct, $dateStr);
    $stmt3->execute();
    
    // 4. Permaculture Data
    $ph = rand(55, 75) / 10; // 5.5 - 7.5
    
    $stmt4 = $conn->prepare("INSERT INTO permaculture_monitoring (device_id, soil_ph, recorded_at) VALUES (5, ?, ?)");
    $stmt4->bind_param("ds", $ph, $dateStr);
    $stmt4->execute();
}

echo "Berhasil membuat 48 baris data baru untuk setiap sensor dalam 24 jam terakhir.\n";
?>