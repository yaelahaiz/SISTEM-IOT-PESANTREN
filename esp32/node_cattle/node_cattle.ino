/**
 * ESP32 Node Kandang Sapi / Biodigester
 * =======================================
 * Membaca data dari:
 * - Ultrasonic HC-SR04 / HR-004 (tinggi cairan biodigester)
 * - Sensor Tekanan Gas (analog input)
 * - Soil Moisture Sensor V1.2 (kelembaban tanah)
 * 
 * Menghitung volume cairan berdasarkan tinggi tabung biodigester (silinder)
 * Mengirim data ke ESP32 Gateway via ESP-NOW
 * 
 * LIBRARY YANG DIBUTUHKAN:
 * - Tidak ada library tambahan (hanya library bawaan ESP32)
 * 
 * WIRING PIN:
 * - HC-SR04 TRIG   -> GPIO 12
 * - HC-SR04 ECHO   -> GPIO 14
 * - HC-SR04 VCC    -> 5V
 * - HC-SR04 GND    -> GND
 * - Pressure Sensor -> GPIO 34 (ADC, input only)
 * - Soil Moisture   -> GPIO 35 (ADC, input only)
 * 
 * CATATAN:
 * - GPIO 34, 35 adalah input-only pin, cocok untuk ADC
 * - Echo pin HC-SR04 output 5V, gunakan voltage divider (2 resistor)
 *   Echo -> R1(1k) -> GPIO14 -> R2(2k) -> GND
 * - Kalibrasi sensor tekanan dan soil moisture di bagian konfigurasi
 * 
 * RUMUS VOLUME BIODIGESTER (Silinder):
 * V = pi * (d/2)^2 * h_cairan / 1000 (Liter)
 * h_cairan = tinggi_tabung - jarak_sensor_ke_permukaan
 * 
 * CARA UPLOAD:
 * 1. Buka Arduino IDE
 * 2. Pilih Board: ESP32 Dev Module
 * 3. Ubah MAC_GATEWAY dan parameter kalibrasi
 * 4. Upload program
 * 5. Buka Serial Monitor 115200
 */

#include <esp_now.h>
#include <WiFi.h>

// ===== KONFIGURASI - UBAH SESUAI KEBUTUHAN =====

// MAC Address ESP32 Gateway
uint8_t MAC_GATEWAY[] = {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x00};

// Pin konfigurasi
#define TRIG_PIN        12      // Ultrasonic Trigger
#define ECHO_PIN        14      // Ultrasonic Echo
#define PRESSURE_PIN    34      // Sensor tekanan gas (ADC)
#define MOISTURE_PIN    35      // Soil moisture sensor (ADC)

// Parameter biodigester (cm)
#define TANK_HEIGHT     150.0   // Tinggi tabung biodigester (cm)
#define TANK_DIAMETER   100.0   // Diameter tabung biodigester (cm)
#define SENSOR_OFFSET   5.0     // Jarak sensor dari atas tabung (cm)

// Kalibrasi sensor tekanan gas
// Sesuaikan dengan spesifikasi sensor yang digunakan
// Contoh: sensor 0-5V, 0-100 kPa
#define PRESSURE_ADC_MIN    0       // Nilai ADC minimum (tanpa tekanan)
#define PRESSURE_ADC_MAX    4095    // Nilai ADC maximum (tekanan max)
#define PRESSURE_KPA_MIN    0.0     // Tekanan minimum (kPa)
#define PRESSURE_KPA_MAX    100.0   // Tekanan maximum (kPa)

// Kalibrasi soil moisture
// Nilai ADC saat tanah KERING (sensor di udara) - biasanya tinggi
#define MOISTURE_DRY        4095
// Nilai ADC saat tanah BASAH (sensor dalam air) - biasanya rendah
#define MOISTURE_WET        1000

// Interval pengiriman
#define SEND_INTERVAL       30000   // 30 detik

// Jumlah sampling untuk rata-rata
#define NUM_SAMPLES         5

// ===== AKHIR KONFIGURASI =====

// Struktur data dikirim ke gateway (harus sama dengan gateway)
typedef struct struct_cattle {
    float liquid_level;
    float liquid_volume;
    float gas_pressure;
    int soil_moisture_raw;
    float soil_moisture_percent;
} struct_cattle;

struct_cattle cattleData;
unsigned long lastSend = 0;

// Callback
void OnDataSent(const uint8_t *mac_addr, esp_now_send_status_t status) {
    Serial.print("Status kirim: ");
    Serial.println(status == ESP_NOW_SEND_SUCCESS ? "BERHASIL" : "GAGAL");
}

void setup() {
    Serial.begin(115200);
    Serial.println("\n=== ESP32 Node Kandang Sapi / Biodigester ===");
    
    // Setup ultrasonic
    pinMode(TRIG_PIN, OUTPUT);
    pinMode(ECHO_PIN, INPUT);
    Serial.println("Ultrasonic HC-SR04 initialized");
    
    // ADC setup
    analogReadResolution(12);   // 12-bit ADC (0-4095)
    analogSetAttenuation(ADC_11db); // Range input 0-3.3V
    Serial.println("ADC initialized (12-bit)");
    
    // Print konfigurasi
    Serial.printf("Biodigester: tinggi=%.0fcm, diameter=%.0fcm\n", TANK_HEIGHT, TANK_DIAMETER);
    Serial.printf("Kalibrasi Pressure: ADC %d-%d -> %.1f-%.1f kPa\n", 
                   PRESSURE_ADC_MIN, PRESSURE_ADC_MAX, PRESSURE_KPA_MIN, PRESSURE_KPA_MAX);
    Serial.printf("Kalibrasi Moisture: ADC dry=%d, wet=%d\n", MOISTURE_DRY, MOISTURE_WET);
    
    // WiFi STA mode
    WiFi.mode(WIFI_STA);
    WiFi.disconnect();
    
    Serial.print("MAC Address Node: ");
    Serial.println(WiFi.macAddress());
    
    // Init ESP-NOW
    if (esp_now_init() != ESP_OK) {
        Serial.println("Error: ESP-NOW init gagal!");
        return;
    }
    
    esp_now_register_send_cb(OnDataSent);
    
    // Register gateway
    esp_now_peer_info_t peerInfo;
    memset(&peerInfo, 0, sizeof(peerInfo));
    memcpy(peerInfo.peer_addr, MAC_GATEWAY, 6);
    peerInfo.channel = 0;
    peerInfo.encrypt = false;
    
    if (esp_now_add_peer(&peerInfo) != ESP_OK) {
        Serial.println("Error: Gagal menambahkan peer gateway!");
        return;
    }
    
    Serial.println("Setup selesai. Mulai monitoring...\n");
}

/**
 * Membaca jarak dari sensor ultrasonic HC-SR04
 * @return Jarak dalam cm, -1 jika gagal
 */
float readUltrasonic() {
    float totalDistance = 0;
    int validReadings = 0;
    
    for (int i = 0; i < NUM_SAMPLES; i++) {
        // Trigger pulse
        digitalWrite(TRIG_PIN, LOW);
        delayMicroseconds(2);
        digitalWrite(TRIG_PIN, HIGH);
        delayMicroseconds(10);
        digitalWrite(TRIG_PIN, LOW);
        
        // Baca echo (timeout 30ms = ~500cm max)
        long duration = pulseIn(ECHO_PIN, HIGH, 30000);
        
        if (duration > 0) {
            float distance = duration * 0.034 / 2.0; // cm
            if (distance > 0 && distance < 500) {
                totalDistance += distance;
                validReadings++;
            }
        }
        delay(50);
    }
    
    if (validReadings > 0) {
        return totalDistance / validReadings;
    }
    return -1;
}

/**
 * Hitung tinggi cairan berdasarkan jarak sensor ke permukaan
 */
float calculateLiquidLevel(float sensorDistance) {
    // Tinggi cairan = Tinggi tabung - Offset sensor - Jarak ke permukaan
    float level = TANK_HEIGHT - SENSOR_OFFSET - sensorDistance;
    
    // Clamp ke range valid
    if (level < 0) level = 0;
    if (level > TANK_HEIGHT) level = TANK_HEIGHT;
    
    return level;
}

/**
 * Hitung volume biodigester (silinder) dalam Liter
 */
float calculateVolume(float liquidLevel) {
    float radius = TANK_DIAMETER / 2.0;
    float volume_cm3 = PI * radius * radius * liquidLevel;
    return volume_cm3 / 1000.0; // Convert cm³ ke Liter
}

/**
 * Baca sensor tekanan gas (analog)
 */
float readPressure() {
    long total = 0;
    for (int i = 0; i < NUM_SAMPLES; i++) {
        total += analogRead(PRESSURE_PIN);
        delay(10);
    }
    int avgADC = total / NUM_SAMPLES;
    
    // Map ADC ke kPa
    float pressure = mapFloat(avgADC, PRESSURE_ADC_MIN, PRESSURE_ADC_MAX, 
                              PRESSURE_KPA_MIN, PRESSURE_KPA_MAX);
    
    // Clamp
    if (pressure < PRESSURE_KPA_MIN) pressure = PRESSURE_KPA_MIN;
    if (pressure > PRESSURE_KPA_MAX) pressure = PRESSURE_KPA_MAX;
    
    Serial.printf("Tekanan Gas -> ADC: %d, Tekanan: %.1f kPa\n", avgADC, pressure);
    return pressure;
}

/**
 * Baca sensor kelembaban tanah
 */
void readSoilMoisture() {
    long total = 0;
    for (int i = 0; i < NUM_SAMPLES; i++) {
        total += analogRead(MOISTURE_PIN);
        delay(10);
    }
    int avgADC = total / NUM_SAMPLES;
    
    cattleData.soil_moisture_raw = avgADC;
    
    // Map ke persentase (kering=0%, basah=100%)
    float percent = mapFloat(avgADC, MOISTURE_DRY, MOISTURE_WET, 0.0, 100.0);
    
    // Clamp 0-100
    if (percent < 0) percent = 0;
    if (percent > 100) percent = 100;
    
    cattleData.soil_moisture_percent = percent;
    
    Serial.printf("Soil Moisture -> ADC: %d, Kelembaban: %.1f%%\n", avgADC, percent);
}

/**
 * Map float (seperti Arduino map() tapi untuk float)
 */
float mapFloat(float x, float in_min, float in_max, float out_min, float out_max) {
    return (x - in_min) * (out_max - out_min) / (in_max - in_min) + out_min;
}

void sendData() {
    esp_err_t result = esp_now_send(MAC_GATEWAY, (uint8_t *)&cattleData, sizeof(cattleData));
    
    Serial.println("--- Mengirim data ke Gateway ---");
    Serial.printf("  Tinggi Cairan: %.1f cm\n", cattleData.liquid_level);
    Serial.printf("  Volume Cairan: %.2f L\n", cattleData.liquid_volume);
    Serial.printf("  Tekanan Gas: %.1f kPa\n", cattleData.gas_pressure);
    Serial.printf("  Moisture Raw: %d\n", cattleData.soil_moisture_raw);
    Serial.printf("  Moisture: %.1f%%\n", cattleData.soil_moisture_percent);
    Serial.println("--------------------------------\n");
}

void loop() {
    unsigned long currentMillis = millis();
    
    if (currentMillis - lastSend >= SEND_INTERVAL) {
        lastSend = currentMillis;
        
        // Baca ultrasonic
        float distance = readUltrasonic();
        if (distance >= 0) {
            cattleData.liquid_level = calculateLiquidLevel(distance);
            cattleData.liquid_volume = calculateVolume(cattleData.liquid_level);
            Serial.printf("Ultrasonic -> Jarak: %.1fcm, Level: %.1fcm, Volume: %.2fL\n", 
                          distance, cattleData.liquid_level, cattleData.liquid_volume);
        } else {
            Serial.println("Warning: Gagal membaca ultrasonic");
        }
        
        delay(100);
        
        // Baca tekanan gas
        cattleData.gas_pressure = readPressure();
        
        delay(100);
        
        // Baca soil moisture
        readSoilMoisture();
        
        delay(100);
        
        // Kirim ke gateway
        sendData();
    }
}
