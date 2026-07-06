/**
 * ESP32 Node Panel Surya
 * ========================
 * Membaca data dari:
 * - DHT22 (suhu & kelembaban panel surya)
 * - PZEM-017 (tegangan & arus DC baterai via RS485)
 * 
 * Mengirim data ke ESP32 Gateway via ESP-NOW
 * 
 * LIBRARY YANG DIBUTUHKAN:
 * 1. DHT sensor library by Adafruit (Install via Library Manager)
 * 2. Adafruit Unified Sensor (Install via Library Manager)
 * 3. ModbusMaster by Doc Walker (Install via Library Manager)
 * 
 * WIRING PIN:
 * - DHT22 Data   -> GPIO 4
 * - DHT22 VCC    -> 3.3V
 * - DHT22 GND    -> GND
 * - PZEM-017 TX  -> GPIO 16 (RX2 ESP32)
 * - PZEM-017 RX  -> GPIO 17 (TX2 ESP32)
 * - Pull-up 10k pada data DHT22
 * - MAX485: DE+RE -> GPIO 5 (untuk kontrol arah komunikasi RS485)
 * 
 * CARA UPLOAD:
 * 1. Buka Arduino IDE
 * 2. Pilih Board: ESP32 Dev Module
 * 3. Install library yang diperlukan
 * 4. Ubah MAC_GATEWAY sesuai MAC ESP32 Gateway
 * 5. Upload program
 * 6. Buka Serial Monitor 115200 untuk monitoring
 */

#include <esp_now.h>
#include <WiFi.h>
#include <DHT.h>
#include <ModbusMaster.h>

// ===== KONFIGURASI - UBAH SESUAI KEBUTUHAN =====

// MAC Address ESP32 Gateway (ubah sesuai gateway Anda)
uint8_t MAC_GATEWAY[] = {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x00};

// Pin konfigurasi
#define DHT_PIN         4       // Pin data DHT22
#define DHT_TYPE        DHT22   // Tipe sensor DHT
#define RS485_RX        16      // RX2 - Terima data dari PZEM
#define RS485_TX        17      // TX2 - Kirim data ke PZEM
#define RS485_DE_RE     5       // Pin kontrol arah RS485 (DE + RE)

// PZEM-017 Modbus Address (default 0x01)
#define PZEM_ADDR       0x01

// Interval pengiriman data (ms)
#define SEND_INTERVAL   30000   // 30 detik

// ===== AKHIR KONFIGURASI =====

// Struktur data yang dikirim ke gateway (harus sama dengan gateway)
typedef struct struct_solar {
    float temperature;
    float humidity;
    float voltage;
    float current;
    float power;
    float energy;
} struct_solar;

struct_solar solarData;

// Objek sensor
DHT dht(DHT_PIN, DHT_TYPE);
ModbusMaster pzem;

unsigned long lastSend = 0;
bool sendSuccess = false;

// Callback saat data terkirim
void OnDataSent(const uint8_t *mac_addr, esp_now_send_status_t status) {
    sendSuccess = (status == ESP_NOW_SEND_SUCCESS);
    Serial.print("Status kirim: ");
    Serial.println(sendSuccess ? "BERHASIL" : "GAGAL");
}

// Kontrol arah RS485: sebelum transmit
void preTransmission() {
    digitalWrite(RS485_DE_RE, HIGH);
}

// Kontrol arah RS485: setelah transmit
void postTransmission() {
    digitalWrite(RS485_DE_RE, LOW);
}

void setup() {
    Serial.begin(115200);
    Serial.println("\n=== ESP32 Node Panel Surya ===");
    
    // Setup RS485 direction control
    pinMode(RS485_DE_RE, OUTPUT);
    digitalWrite(RS485_DE_RE, LOW); // Default: receive mode
    
    // Init DHT22
    dht.begin();
    Serial.println("DHT22 initialized");
    
    // Init PZEM-017 via Serial2
    Serial2.begin(9600, SERIAL_8N2, RS485_RX, RS485_TX);
    pzem.begin(PZEM_ADDR, Serial2);
    pzem.preTransmission(preTransmission);
    pzem.postTransmission(postTransmission);
    Serial.println("PZEM-017 initialized (Modbus)");
    
    // Init WiFi dalam mode STA (diperlukan untuk ESP-NOW)
    WiFi.mode(WIFI_STA);
    WiFi.disconnect();
    
    // Print MAC Address sendiri
    Serial.print("MAC Address Node: ");
    Serial.println(WiFi.macAddress());
    
    // Init ESP-NOW
    if (esp_now_init() != ESP_OK) {
        Serial.println("Error: ESP-NOW init gagal!");
        return;
    }
    
    esp_now_register_send_cb(OnDataSent);
    
    // Register gateway sebagai peer
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

void readDHT22() {
    float temp = dht.readTemperature();
    float hum = dht.readHumidity();
    
    if (isnan(temp) || isnan(hum)) {
        Serial.println("Warning: Gagal membaca DHT22");
        // Gunakan nilai terakhir yang valid (tidak update)
    } else {
        solarData.temperature = temp;
        solarData.humidity = hum;
        Serial.printf("DHT22 -> Suhu: %.1f°C, Kelembaban: %.1f%%\n", temp, hum);
    }
}

void readPZEM017() {
    // PZEM-017 register: 
    // 0x0000 = Voltage (0.01V resolution)
    // 0x0001 = Current (0.01A resolution)
    // 0x0002-0x0003 = Power (0.1W resolution)
    // 0x0004-0x0005 = Energy (1Wh resolution)
    
    uint8_t result = pzem.readInputRegisters(0x0000, 6);
    
    if (result == pzem.ku8MBSuccess) {
        solarData.voltage = pzem.getResponseBuffer(0) * 0.01;    // Volt
        solarData.current = pzem.getResponseBuffer(1) * 0.01;    // Ampere
        
        // Power: 2 register (32-bit), resolusi 0.1W
        uint32_t powerRaw = (pzem.getResponseBuffer(3) << 16) | pzem.getResponseBuffer(2);
        solarData.power = powerRaw * 0.1;
        
        // Energy: 2 register (32-bit), resolusi 1Wh
        uint32_t energyRaw = (pzem.getResponseBuffer(5) << 16) | pzem.getResponseBuffer(4);
        solarData.energy = (float)energyRaw;
        
        Serial.printf("PZEM-017 -> V: %.2fV, I: %.2fA, P: %.1fW, E: %.0fWh\n", 
                       solarData.voltage, solarData.current, solarData.power, solarData.energy);
    } else {
        Serial.printf("Warning: Gagal membaca PZEM-017 (error: 0x%02X)\n", result);
        // Jika PZEM tidak terbaca, bisa set ke 0 atau biarkan nilai lama
        // solarData.voltage = 0;
        // solarData.current = 0;
    }
}

void sendData() {
    esp_err_t result = esp_now_send(MAC_GATEWAY, (uint8_t *)&solarData, sizeof(solarData));
    
    Serial.println("--- Mengirim data ke Gateway ---");
    Serial.printf("  Suhu: %.1f°C\n", solarData.temperature);
    Serial.printf("  Kelembaban: %.1f%%\n", solarData.humidity);
    Serial.printf("  Tegangan: %.2f V\n", solarData.voltage);
    Serial.printf("  Arus: %.2f A\n", solarData.current);
    Serial.printf("  Daya: %.1f W\n", solarData.power);
    Serial.printf("  Energi: %.0f Wh\n", solarData.energy);
    Serial.println("--------------------------------\n");
}

void loop() {
    unsigned long currentMillis = millis();
    
    if (currentMillis - lastSend >= SEND_INTERVAL) {
        lastSend = currentMillis;
        
        // Baca semua sensor
        readDHT22();
        delay(100);
        readPZEM017();
        delay(100);
        
        // Kirim ke gateway
        sendData();
    }
}
