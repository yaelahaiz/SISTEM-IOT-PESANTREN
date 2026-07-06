-- ============================================================
-- Database IoT Pesantren
-- Sistem Monitoring Panel Surya, Rumah Pengering, Kandang Sapi,
-- Biodigester, Permaculture & Pencatatan Penjualan
-- ============================================================

CREATE DATABASE IF NOT EXISTS iot_pesantren
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE iot_pesantren;

-- ============================================================
-- 1. Tabel Users (Admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    role ENUM('admin','viewer') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. Tabel Devices (Perangkat ESP32)
-- ============================================================
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_name VARCHAR(100) NOT NULL,
    device_type ENUM('gateway','node_solar','node_dryer','node_cattle','node_permaculture') NOT NULL,
    mac_address VARCHAR(17),
    location VARCHAR(200),
    api_key VARCHAR(64) UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    last_seen TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 3. Tabel Sensors (Registry Sensor)
-- ============================================================
CREATE TABLE IF NOT EXISTS sensors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sensor_name VARCHAR(100) NOT NULL,
    sensor_type VARCHAR(50) NOT NULL,
    unit VARCHAR(20),
    location VARCHAR(200),
    device_id INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 4. Tabel Solar Monitoring (Panel Surya)
-- ============================================================
CREATE TABLE IF NOT EXISTS solar_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT,
    temperature FLOAT COMMENT 'Suhu panel surya DHT22 (°C)',
    humidity FLOAT COMMENT 'Kelembaban DHT22 (%)',
    voltage FLOAT COMMENT 'Tegangan baterai PZEM-017 (V)',
    current_amp FLOAT COMMENT 'Arus baterai PZEM-017 (A)',
    power FLOAT COMMENT 'Daya (W)',
    energy FLOAT COMMENT 'Energi (Wh)',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_solar_time (recorded_at),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 5. Tabel Dryer Monitoring (Rumah Pengering)
-- ============================================================
CREATE TABLE IF NOT EXISTS dryer_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT,
    temperature FLOAT COMMENT 'Suhu rumah pengering DHT22 (°C)',
    humidity FLOAT COMMENT 'Kelembaban DHT22 (%)',
    voltage_ac FLOAT COMMENT 'Tegangan AC PZEM-016 (V)',
    current_ac FLOAT COMMENT 'Arus AC PZEM-016 (A)',
    power_ac FLOAT COMMENT 'Daya AC (W)',
    energy_ac FLOAT COMMENT 'Energi AC (Wh)',
    frequency FLOAT COMMENT 'Frekuensi (Hz)',
    power_factor FLOAT COMMENT 'Power Factor',
    relay_lamp TINYINT(1) DEFAULT 0 COMMENT 'Status lampu 0=OFF 1=ON',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dryer_time (recorded_at),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 6. Tabel Cattle Monitoring (Kandang Sapi & Biodigester)
-- ============================================================
CREATE TABLE IF NOT EXISTS cattle_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT,
    liquid_level FLOAT COMMENT 'Tinggi cairan biodigester (cm)',
    liquid_volume FLOAT COMMENT 'Volume cairan (Liter)',
    gas_pressure FLOAT COMMENT 'Tekanan gas biodigester (kPa)',
    soil_moisture_raw INT COMMENT 'ADC mentah soil moisture',
    soil_moisture_percent FLOAT COMMENT 'Kelembaban tanah (%)',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cattle_time (recorded_at),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 7. Tabel Permaculture Monitoring
-- ============================================================
CREATE TABLE IF NOT EXISTS permaculture_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT,
    soil_ph FLOAT COMMENT 'pH tanah',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_perm_time (recorded_at),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 8. Tabel Relay Control
-- ============================================================
CREATE TABLE IF NOT EXISTS relay_control (
    id INT AUTO_INCREMENT PRIMARY KEY,
    relay_name VARCHAR(100) NOT NULL,
    relay_pin INT,
    status TINYINT(1) DEFAULT 0 COMMENT '0=OFF 1=ON',
    device_id INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 9. Tabel Sales (Penjualan)
-- ============================================================
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(200) NOT NULL,
    capital DECIMAL(12,2) NOT NULL COMMENT 'Modal',
    revenue DECIMAL(12,2) NOT NULL COMMENT 'Harga jual / uang didapat',
    profit DECIMAL(12,2) GENERATED ALWAYS AS (revenue - capital) STORED COMMENT 'Keuntungan',
    sale_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sale_date (sale_date)
) ENGINE=InnoDB;

-- ============================================================
-- 10. Tabel Settings (Pengaturan Sistem)
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- INSERT DATA DEFAULT
-- ============================================================

-- Admin user (password: password)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@pesantren.id', 'admin');
-- NOTE: Hash di atas untuk 'password'. Login dengan username: admin, password: password
-- Untuk mengubah password, gunakan halaman admin/settings.php atau jalankan:
-- php -r "echo password_hash('passwordbaru', PASSWORD_BCRYPT);"

-- Devices
INSERT INTO devices (device_name, device_type, mac_address, location, api_key, is_active) VALUES
('Gateway Utama', 'gateway', 'AA:BB:CC:DD:EE:00', 'Ruang Server', 'GW_API_KEY_2024', 1),
('Node Panel Surya', 'node_solar', 'AA:BB:CC:DD:EE:01', 'Atap Panel Surya', 'SOLAR_API_KEY_2024', 1),
('Node Rumah Pengering', 'node_dryer', 'AA:BB:CC:DD:EE:02', 'Rumah Pengering', 'DRYER_API_KEY_2024', 1),
('Node Kandang Sapi', 'node_cattle', 'AA:BB:CC:DD:EE:03', 'Kandang Sapi', 'CATTLE_API_KEY_2024', 1),
('Node Permaculture', 'node_permaculture', 'AA:BB:CC:DD:EE:04', 'Kebun Permaculture', 'PERM_API_KEY_2024', 1);

-- Sensors
INSERT INTO sensors (sensor_name, sensor_type, unit, location, device_id) VALUES
('DHT22 Panel Surya', 'DHT22', '°C', 'Panel Surya', 2),
('PZEM-017 Baterai', 'PZEM-017', 'V/A', 'Panel Surya', 2),
('DHT22 Rumah Pengering', 'DHT22', '°C', 'Rumah Pengering', 3),
('PZEM-016 Inverter', 'PZEM-016', 'V/A', 'Output Inverter', 3),
('Relay Lampu', 'Relay 5V', 'ON/OFF', 'Rumah Pengering', 3),
('HC-SR04 Biodigester', 'Ultrasonic', 'cm', 'Biodigester', 4),
('Sensor Tekanan Gas', 'Pressure', 'kPa', 'Biodigester', 4),
('Soil Moisture V1.2', 'Soil Moisture', '%', 'Kandang Sapi', 4),
('Sensor pH RS485', 'pH Modbus', 'pH', 'Kebun Permaculture', 5);

-- Relay Control
INSERT INTO relay_control (relay_name, relay_pin, status, device_id) VALUES
('Lampu Rumah Pengering', 26, 0, 3);

-- Settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_name', 'Monitoring IoT Pesantren', 'Nama website'),
('biodigester_height', '150', 'Tinggi tabung biodigester (cm)'),
('biodigester_diameter', '100', 'Diameter tabung biodigester (cm)'),
('soil_dry_value', '4095', 'Nilai ADC tanah kering'),
('soil_wet_value', '1000', 'Nilai ADC tanah basah'),
('api_key_solar', 'SOLAR_API_KEY_2024', 'API Key untuk node panel surya'),
('api_key_dryer', 'DRYER_API_KEY_2024', 'API Key untuk node rumah pengering'),
('api_key_cattle', 'CATTLE_API_KEY_2024', 'API Key untuk node kandang sapi'),
('api_key_permaculture', 'PERM_API_KEY_2024', 'API Key untuk node permaculture');

-- ============================================================
-- DUMMY DATA MONITORING (30 hari terakhir)
-- ============================================================

-- Prosedur untuk generate data dummy
DELIMITER //

CREATE PROCEDURE generate_dummy_data()
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE curr_date DATETIME;
    DECLARE temp_solar FLOAT;
    DECLARE hum_solar FLOAT;
    DECLARE volt FLOAT;
    DECLARE curr FLOAT;
    DECLARE temp_dryer FLOAT;
    DECLARE hum_dryer FLOAT;
    DECLARE volt_ac FLOAT;
    DECLARE curr_ac FLOAT;
    DECLARE liq_level FLOAT;
    DECLARE gas_press FLOAT;
    DECLARE soil_raw INT;
    DECLARE soil_pct FLOAT;
    DECLARE ph_val FLOAT;
    
    WHILE i < 200 DO
        SET curr_date = DATE_SUB(NOW(), INTERVAL (200 - i) * 4 HOUR);
        
        -- Solar data (suhu 25-75°C, kelembaban 30-80%, tegangan 10-14V, arus 0.5-5A)
        SET temp_solar = 25 + (RAND() * 50);
        SET hum_solar = 30 + (RAND() * 50);
        SET volt = 10 + (RAND() * 4);
        SET curr = 0.5 + (RAND() * 4.5);
        
        INSERT INTO solar_monitoring (device_id, temperature, humidity, voltage, current_amp, power, energy, recorded_at)
        VALUES (2, ROUND(temp_solar, 1), ROUND(hum_solar, 1), ROUND(volt, 2), ROUND(curr, 2), 
                ROUND(volt * curr, 2), ROUND(volt * curr * 0.5, 2), curr_date);
        
        -- Dryer data (suhu 30-60°C, kelembaban 20-70%, AC 210-230V, arus 0.1-3A)
        SET temp_dryer = 30 + (RAND() * 30);
        SET hum_dryer = 20 + (RAND() * 50);
        SET volt_ac = 210 + (RAND() * 20);
        SET curr_ac = 0.1 + (RAND() * 2.9);
        
        INSERT INTO dryer_monitoring (device_id, temperature, humidity, voltage_ac, current_ac, power_ac, energy_ac, frequency, power_factor, relay_lamp, recorded_at)
        VALUES (3, ROUND(temp_dryer, 1), ROUND(hum_dryer, 1), ROUND(volt_ac, 1), ROUND(curr_ac, 2),
                ROUND(volt_ac * curr_ac, 2), ROUND(volt_ac * curr_ac * 0.3, 2), ROUND(49.5 + RAND(), 1),
                ROUND(0.7 + (RAND() * 0.3), 2), IF(RAND() > 0.5, 1, 0), curr_date);
        
        -- Cattle data (tinggi cairan 20-120cm, tekanan 10-200kPa, kelembaban tanah)
        SET liq_level = 20 + (RAND() * 100);
        SET gas_press = 10 + (RAND() * 190);
        SET soil_raw = 1000 + FLOOR(RAND() * 3095);
        SET soil_pct = ROUND((4095 - soil_raw) / (4095 - 1000) * 100, 1);
        
        INSERT INTO cattle_monitoring (device_id, liquid_level, liquid_volume, gas_pressure, soil_moisture_raw, soil_moisture_percent, recorded_at)
        VALUES (4, ROUND(liq_level, 1), ROUND(3.14159 * 50 * 50 * liq_level / 1000, 2), ROUND(gas_press, 1),
                soil_raw, soil_pct, curr_date);
        
        -- Permaculture data (pH 4-9)
        SET ph_val = 4 + (RAND() * 5);
        
        INSERT INTO permaculture_monitoring (device_id, soil_ph, recorded_at)
        VALUES (5, ROUND(ph_val, 1), curr_date);
        
        SET i = i + 1;
    END WHILE;
END //

DELIMITER ;

CALL generate_dummy_data();
DROP PROCEDURE IF EXISTS generate_dummy_data;

-- ============================================================
-- DUMMY DATA PENJUALAN (60 hari terakhir)
-- ============================================================
INSERT INTO sales (product_name, capital, revenue, sale_date, notes) VALUES
('Susu Sapi Segar', 15000, 25000, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Penjualan harian'),
('Susu Sapi Segar', 15000, 25000, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Penjualan harian'),
('Pupuk Organik 5kg', 20000, 35000, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '1 karung'),
('Keripik Singkong', 8000, 15000, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '10 bungkus'),
('Kompos 10kg', 10000, 25000, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '2 karung'),
('Biogas (tabung)', 30000, 50000, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '1 tabung kecil'),
('Susu Sapi Segar', 30000, 50000, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '2 liter'),
('Telur Ayam', 25000, 40000, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '1 papan'),
('Pupuk Organik 5kg', 40000, 70000, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '2 karung'),
('Keripik Tempe', 12000, 22000, DATE_SUB(CURDATE(), INTERVAL 8 DAY), '15 bungkus'),
('Susu Sapi Segar', 15000, 25000, DATE_SUB(CURDATE(), INTERVAL 9 DAY), NULL),
('Kompos 10kg', 20000, 50000, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '4 karung'),
('Madu Lebah', 50000, 85000, DATE_SUB(CURDATE(), INTERVAL 11 DAY), '1 botol 500ml'),
('Biogas (tabung)', 30000, 50000, DATE_SUB(CURDATE(), INTERVAL 12 DAY), NULL),
('Susu Sapi Segar', 45000, 75000, DATE_SUB(CURDATE(), INTERVAL 13 DAY), '3 liter'),
('Pupuk Organik 5kg', 20000, 35000, DATE_SUB(CURDATE(), INTERVAL 15 DAY), NULL),
('Keripik Singkong', 16000, 30000, DATE_SUB(CURDATE(), INTERVAL 16 DAY), '20 bungkus'),
('Telur Ayam', 50000, 80000, DATE_SUB(CURDATE(), INTERVAL 18 DAY), '2 papan'),
('Susu Sapi Segar', 15000, 25000, DATE_SUB(CURDATE(), INTERVAL 20 DAY), NULL),
('Kompos 10kg', 30000, 75000, DATE_SUB(CURDATE(), INTERVAL 21 DAY), '6 karung'),
('Madu Lebah', 100000, 170000, DATE_SUB(CURDATE(), INTERVAL 22 DAY), '2 botol'),
('Biogas (tabung)', 60000, 100000, DATE_SUB(CURDATE(), INTERVAL 25 DAY), '2 tabung'),
('Pupuk Organik 5kg', 60000, 105000, DATE_SUB(CURDATE(), INTERVAL 27 DAY), '3 karung'),
('Keripik Tempe', 24000, 44000, DATE_SUB(CURDATE(), INTERVAL 28 DAY), '30 bungkus'),
('Susu Sapi Segar', 30000, 50000, DATE_SUB(CURDATE(), INTERVAL 30 DAY), '2 liter'),
('Telur Ayam', 25000, 40000, DATE_SUB(CURDATE(), INTERVAL 32 DAY), '1 papan'),
('Kompos 10kg', 10000, 25000, DATE_SUB(CURDATE(), INTERVAL 35 DAY), NULL),
('Madu Lebah', 50000, 85000, DATE_SUB(CURDATE(), INTERVAL 38 DAY), '1 botol'),
('Biogas (tabung)', 30000, 50000, DATE_SUB(CURDATE(), INTERVAL 40 DAY), NULL),
('Susu Sapi Segar', 60000, 100000, DATE_SUB(CURDATE(), INTERVAL 42 DAY), '4 liter'),
('Pupuk Organik 5kg', 80000, 140000, DATE_SUB(CURDATE(), INTERVAL 45 DAY), '4 karung'),
('Keripik Singkong', 32000, 60000, DATE_SUB(CURDATE(), INTERVAL 48 DAY), '40 bungkus'),
('Telur Ayam', 75000, 120000, DATE_SUB(CURDATE(), INTERVAL 50 DAY), '3 papan'),
('Kompos 10kg', 40000, 100000, DATE_SUB(CURDATE(), INTERVAL 52 DAY), '8 karung'),
('Susu Sapi Segar', 15000, 25000, DATE_SUB(CURDATE(), INTERVAL 55 DAY), '1 liter'),
('Madu Lebah', 150000, 255000, DATE_SUB(CURDATE(), INTERVAL 58 DAY), '3 botol');

-- ============================================================
-- Login Admin Default:
-- Username: admin
-- Password: password
-- Ubah password melalui halaman admin Settings setelah login.
-- ============================================================
