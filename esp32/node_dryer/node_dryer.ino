/**
 * ESP32 Node Rumah Pengering
 * ============================
 * Membaca data dari:
 * - DHT22 (suhu & kelembaban rumah pengering)
 * - PZEM-016 (tegangan, arus, daya AC output inverter via RS485)
 * - Relay 5V (kontrol lampu rumah pengering)
 * 
 * Menerima perintah relay dari Gateway via ESP-NOW
 * Mengirim data sensor + status relay ke Gateway via ESP-NOW
 * 
 * LIBRARY YANG DIBUTUHKAN:
 * 1. DHT sensor library by Adafruit
 * 2. Adafruit Unified Sensor
 * 3. ModbusMaster by Doc Walker
 * 
 * WIRING PIN:
 * - DHT22 Data    -> GPIO 4
 * - DHT22 VCC     -> 3.3V
 * - DHT22 GND     -> GND
 * - PZEM-016 TX   -> GPIO 16 (RX2 ESP32)
 * - PZEM-016 RX   -> GPIO 17 (TX2 ESP32)
 * - MAX485 DE+RE  -> GPIO 5
 * - Relay Signal   -> GPIO 26
 * - Relay VCC      -> 5V (dari VIN atau pin 5V)
 * - Relay GND      -> GND
 * 
 * CATATAN:
 * - PZEM-016 untuk AC, PZEM-017 untuk DC
 * - Relay menggunakan active LOW (LOW = ON, HIGH = OFF) pada kebanyakan modul relay
 * - Pull-up 10k pada data DHT22
 * 
 * CARA UPLOAD:
 * 1. Buka Arduino IDE
 * 2. Pilih Board: ESP32 Dev Module
 * 3. Install library yang diperlukan
 * 4. Ubah MAC_GATEWAY sesuai MAC ESP32 Gateway
 * 5. Upload program
 * 6. Buka Serial Monitor 115200
 */

#include <esp_now.h>
#include <WiFi.h>
#include <DHT.h>
#include <ModbusMaster.h>

// ===== KONFIGURASI - UBAH SESUAI KEBUTUHAN =====

// MAC Address ESP32 Gateway
uint8_t MAC_GATEWAY[] = {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x00};

// Pin konfigurasi
#define DHT_PIN         4
#define DHT_TYPE        DHT22
#define RS485_RX        16
#define RS485_TX        17
#define RS485_DE_RE     5
#define RELAY_PIN       26      // Pin relay lampu

// PZEM-016 Modbus Address
#define PZEM_ADDR       0x01

// Interval pengiriman
#define SEND_INTERVAL   30000   // 30 detik

// Relay mode: true = active LOW (kebanyakan modul relay)
#define RELAY_ACTIVE_LOW true

// ===== AKHIR KONFIGURASI =====

// Struktur data dikirim ke gateway (harus sama dengan gateway)
typedef struct struct_dryer {
    float temperature;
    float humidity;
    float voltage_ac;
    float current_ac;
    float power_ac;
    float energy_ac;
    float frequency;
    float pf;
    int relay_lamp;
} struct_dryer;

// Struktur perintah relay dari gateway
typedef struct struct_relay_cmd {
    int relay_pin;
    int status;
} struct_relay_cmd;

struct_dryer dryerData;

DHT dht(DHT_PIN, DHT_TYPE);
ModbusMaster pzem;

unsigned long lastSend = 0;
int relayState = 0; // 0 = OFF, 1 = ON

// Callback ESP-NOW saat data terkirim
void OnDataSent(const uint8_t *mac_addr, esp_now_send_status_t status) {
    Serial.print("Status kirim: ");
    Serial.println(status == ESP_NOW_SEND_SUCCESS ? "BERHASIL" : "GAGAL");
}

// Callback ESP-NOW saat menerima data (perintah relay dari gateway)
void OnDataRecv(const uint8_t *mac, const uint8_t *incomingData, int len) {
    if (len == sizeof(struct_relay_cmd)) {
        struct_relay_cmd cmd;
        memcpy(&cmd, incomingData, sizeof(cmd));
        
        Serial.printf("Terima perintah relay: pin=%d, status=%d\n", cmd.relay_pin, cmd.status);
        
        if (cmd.relay_pin == RELAY_PIN) {
            relayState = cmd.status;
            setRelay(relayState);
            Serial.printf("Relay lampu: %s\n", relayState ? "ON" : "OFF");
        }
    }
}

void setRelay(int state) {
    if (RELAY_ACTIVE_LOW) {
        digitalWrite(RELAY_PIN, state ? LOW : HIGH);
    } else {
        digitalWrite(RELAY_PIN, state ? HIGH : LOW);
    }
}

// RS485 direction control
void preTransmission() {
    digitalWrite(RS485_DE_RE, HIGH);
}

void postTransmission() {
    digitalWrite(RS485_DE_RE, LOW);
}

void setup() {
    Serial.begin(115200);
    Serial.println("\n=== ESP32 Node Rumah Pengering ===");
    
    // Setup relay
    pinMode(RELAY_PIN, OUTPUT);
    setRelay(0); // Default OFF
    Serial.println("Relay initialized (OFF)");
    
    // Setup RS485
    pinMode(RS485_DE_RE, OUTPUT);
    digitalWrite(RS485_DE_RE, LOW);
    
    // Init DHT22
    dht.begin();
    Serial.println("DHT22 initialized");
    
    // Init PZEM-016
    Serial2.begin(9600, SERIAL_8N2, RS485_RX, RS485_TX);
    pzem.begin(PZEM_ADDR, Serial2);
    pzem.preTransmission(preTransmission);
    pzem.postTransmission(postTransmission);
    Serial.println("PZEM-016 initialized (Modbus)");
    
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
    esp_now_register_recv_cb(OnDataRecv);
    
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

void readDHT22() {
    float temp = dht.readTemperature();
    float hum = dht.readHumidity();
    
    if (isnan(temp) || isnan(hum)) {
        Serial.println("Warning: Gagal membaca DHT22");
    } else {
        dryerData.temperature = temp;
        dryerData.humidity = hum;
        Serial.printf("DHT22 -> Suhu: %.1f°C, Kelembaban: %.1f%%\n", temp, hum);
    }
}

void readPZEM016() {
    // PZEM-016 registers (AC meter):
    // 0x0000 = Voltage (0.1V)
    // 0x0001 = Current Low (0.001A)
    // 0x0002 = Current High
    // 0x0003 = Power Low (0.1W)
    // 0x0004 = Power High
    // 0x0005 = Energy Low (1Wh)
    // 0x0006 = Energy High
    // 0x0007 = Frequency (0.1Hz)
    // 0x0008 = Power Factor (0.01)
    
    uint8_t result = pzem.readInputRegisters(0x0000, 9);
    
    if (result == pzem.ku8MBSuccess) {
        dryerData.voltage_ac = pzem.getResponseBuffer(0) * 0.1;
        
        uint32_t currentRaw = (pzem.getResponseBuffer(2) << 16) | pzem.getResponseBuffer(1);
        dryerData.current_ac = currentRaw * 0.001;
        
        uint32_t powerRaw = (pzem.getResponseBuffer(4) << 16) | pzem.getResponseBuffer(3);
        dryerData.power_ac = powerRaw * 0.1;
        
        uint32_t energyRaw = (pzem.getResponseBuffer(6) << 16) | pzem.getResponseBuffer(5);
        dryerData.energy_ac = (float)energyRaw;
        
        dryerData.frequency = pzem.getResponseBuffer(7) * 0.1;
        dryerData.pf = pzem.getResponseBuffer(8) * 0.01;
        
        Serial.printf("PZEM-016 -> V: %.1fV, I: %.3fA, P: %.1fW, E: %.0fWh, F: %.1fHz, PF: %.2f\n",
                       dryerData.voltage_ac, dryerData.current_ac, dryerData.power_ac,
                       dryerData.energy_ac, dryerData.frequency, dryerData.pf);
    } else {
        Serial.printf("Warning: Gagal membaca PZEM-016 (error: 0x%02X)\n", result);
    }
}

void sendData() {
    dryerData.relay_lamp = relayState;
    
    esp_err_t result = esp_now_send(MAC_GATEWAY, (uint8_t *)&dryerData, sizeof(dryerData));
    
    Serial.println("--- Mengirim data ke Gateway ---");
    Serial.printf("  Suhu: %.1f°C\n", dryerData.temperature);
    Serial.printf("  Kelembaban: %.1f%%\n", dryerData.humidity);
    Serial.printf("  Tegangan AC: %.1f V\n", dryerData.voltage_ac);
    Serial.printf("  Arus AC: %.3f A\n", dryerData.current_ac);
    Serial.printf("  Daya AC: %.1f W\n", dryerData.power_ac);
    Serial.printf("  Energi: %.0f Wh\n", dryerData.energy_ac);
    Serial.printf("  Frekuensi: %.1f Hz\n", dryerData.frequency);
    Serial.printf("  Power Factor: %.2f\n", dryerData.pf);
    Serial.printf("  Relay Lampu: %s\n", relayState ? "ON" : "OFF");
    Serial.println("--------------------------------\n");
}

void loop() {
    unsigned long currentMillis = millis();
    
    if (currentMillis - lastSend >= SEND_INTERVAL) {
        lastSend = currentMillis;
        
        readDHT22();
        delay(100);
        readPZEM016();
        delay(100);
        
        sendData();
    }
}
