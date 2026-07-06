/**
 * ESP32 Node Permaculture
 * =========================
 * Membaca data dari:
 * - Sensor pH Tanah RS485 / Modbus
 * 
 * Mengirim data ke ESP32 Gateway via ESP-NOW
 * 
 * LIBRARY YANG DIBUTUHKAN:
 * 1. ModbusMaster by Doc Walker (Install via Library Manager)
 * 
 * WIRING PIN:
 * - Sensor pH RS485 (via MAX485 module):
 *   - MAX485 RO  -> GPIO 16 (RX2 ESP32)
 *   - MAX485 DI  -> GPIO 17 (TX2 ESP32)
 *   - MAX485 DE  -> GPIO 5 (Tied together with RE)
 *   - MAX485 RE  -> GPIO 5 (Tied together with DE)
 *   - MAX485 A   -> Sensor RS485 A+
 *   - MAX485 B   -> Sensor RS485 B-
 *   - MAX485 VCC -> 3.3V
 *   - MAX485 GND -> GND
 *   - Sensor VCC -> 5-24V (sesuai spesifikasi sensor pH)
 *   - Sensor GND -> GND
 * 
 * CATATAN SENSOR pH RS485:
 * - Kebanyakan sensor pH tanah RS485 menggunakan protokol Modbus RTU
 * - Default address biasanya 0x01
 * - Default baud rate biasanya 9600 atau 4800
 * - Register pH biasanya di address 0x0006 atau 0x0000
 * - Resolusi biasanya 0.01 pH (nilai register / 100)
 * - Sesuaikan MODBUS_ADDR, PH_REGISTER, dan PH_RESOLUTION
 * 
 * PENGUJIAN TANPA SENSOR:
 * Jika sensor belum tersedia, mode simulasi akan mengirim data dummy.
 * Set SIMULATION_MODE = true untuk testing.
 * 
 * CARA UPLOAD:
 * 1. Buka Arduino IDE
 * 2. Pilih Board: ESP32 Dev Module
 * 3. Install library ModbusMaster
 * 4. Ubah MAC_GATEWAY sesuai MAC ESP32 Gateway
 * 5. Sesuaikan parameter sensor pH
 * 6. Upload program
 * 7. Buka Serial Monitor 115200
 */

#include <esp_now.h>
#include <WiFi.h>
#include <ModbusMaster.h>

// ===== KONFIGURASI - UBAH SESUAI KEBUTUHAN =====

// MAC Address ESP32 Gateway
uint8_t MAC_GATEWAY[] = {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x00};

// Pin konfigurasi RS485
#define RS485_RX        16      // RX2
#define RS485_TX        17      // TX2
#define RS485_DE_RE     5       // Direction control

// Konfigurasi sensor pH Modbus
#define MODBUS_ADDR     0x01    // Alamat Modbus sensor pH
#define MODBUS_BAUD     9600    // Baud rate (cek datasheet sensor)
#define PH_REGISTER     0x0006  // Register address untuk nilai pH
#define PH_NUM_REGS     1       // Jumlah register yang dibaca
#define PH_RESOLUTION   100.0   // Pembagi nilai register (misal 650 / 100 = 6.50 pH)

// Mode simulasi (set true jika sensor belum tersedia)
#define SIMULATION_MODE false

// Interval pengiriman
#define SEND_INTERVAL   30000   // 30 detik

// Batas validasi pH
#define PH_MIN          0.0
#define PH_MAX          14.0

// ===== AKHIR KONFIGURASI =====

// Struktur data dikirim ke gateway (harus sama dengan gateway)
typedef struct struct_perm {
    float soil_ph;
} struct_perm;

struct_perm permData;
ModbusMaster phSensor;
unsigned long lastSend = 0;
int readFailCount = 0;

// Callback
void OnDataSent(const uint8_t *mac_addr, esp_now_send_status_t status) {
    Serial.print("Status kirim: ");
    Serial.println(status == ESP_NOW_SEND_SUCCESS ? "BERHASIL" : "GAGAL");
}

// RS485 direction control
void preTransmission() {
    digitalWrite(RS485_DE_RE, HIGH);
    delayMicroseconds(50);
}

void postTransmission() {
    delayMicroseconds(50);
    digitalWrite(RS485_DE_RE, LOW);
}

void setup() {
    Serial.begin(115200);
    Serial.println("\n=== ESP32 Node Permaculture ===");
    
    if (!SIMULATION_MODE) {
        // Setup RS485
        pinMode(RS485_DE_RE, OUTPUT);
        digitalWrite(RS485_DE_RE, LOW); // Default receive mode
        
        // Init Modbus
        Serial2.begin(MODBUS_BAUD, SERIAL_8N1, RS485_RX, RS485_TX);
        phSensor.begin(MODBUS_ADDR, Serial2);
        phSensor.preTransmission(preTransmission);
        phSensor.postTransmission(postTransmission);
        
        Serial.printf("pH Sensor initialized (Modbus addr: 0x%02X, baud: %d)\n", MODBUS_ADDR, MODBUS_BAUD);
        Serial.printf("Register: 0x%04X, Resolution: 1/%.0f\n", PH_REGISTER, PH_RESOLUTION);
    } else {
        Serial.println("*** MODE SIMULASI AKTIF - Data dummy ***");
    }
    
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
 * Baca pH dari sensor Modbus RS485
 * @return nilai pH (0-14), atau -1 jika gagal
 */
float readPhSensor() {
    if (SIMULATION_MODE) {
        // Simulasi: pH antara 5.5 - 7.5 dengan variasi kecil
        float basePh = 6.5;
        float variation = (random(-100, 100)) / 100.0;
        float simPh = basePh + variation;
        Serial.printf("SIMULASI -> pH: %.2f\n", simPh);
        return simPh;
    }
    
    // Baca register dari sensor pH via Modbus
    uint8_t result = phSensor.readHoldingRegisters(PH_REGISTER, PH_NUM_REGS);
    
    if (result == phSensor.ku8MBSuccess) {
        uint16_t rawValue = phSensor.getResponseBuffer(0);
        float ph = rawValue / PH_RESOLUTION;
        
        // Validasi range
        if (ph >= PH_MIN && ph <= PH_MAX) {
            readFailCount = 0;
            Serial.printf("pH Sensor -> Raw: %d, pH: %.2f\n", rawValue, ph);
            return ph;
        } else {
            Serial.printf("Warning: pH diluar range (raw=%d, ph=%.2f)\n", rawValue, ph);
            
            // Coba baca dari Input Register jika Holding Register gagal
            result = phSensor.readInputRegisters(PH_REGISTER, PH_NUM_REGS);
            if (result == phSensor.ku8MBSuccess) {
                rawValue = phSensor.getResponseBuffer(0);
                ph = rawValue / PH_RESOLUTION;
                if (ph >= PH_MIN && ph <= PH_MAX) {
                    Serial.printf("pH Sensor (InputReg) -> Raw: %d, pH: %.2f\n", rawValue, ph);
                    return ph;
                }
            }
        }
    } else {
        readFailCount++;
        Serial.printf("Warning: Gagal membaca pH sensor (error: 0x%02X, fail #%d)\n", result, readFailCount);
        
        // Jika sudah gagal banyak kali, kemungkinan konfigurasi salah
        if (readFailCount >= 10) {
            Serial.println("HINT: Cek wiring, baud rate, dan Modbus address sensor pH!");
            Serial.println("HINT: Coba ubah PH_REGISTER ke 0x0000 atau 0x0002");
            Serial.println("HINT: Coba ubah MODBUS_BAUD ke 4800");
            readFailCount = 0; // Reset counter
        }
    }
    
    return -1; // Gagal
}

void sendData() {
    esp_err_t result = esp_now_send(MAC_GATEWAY, (uint8_t *)&permData, sizeof(permData));
    
    Serial.println("--- Mengirim data ke Gateway ---");
    Serial.printf("  pH Tanah: %.2f\n", permData.soil_ph);
    Serial.println("--------------------------------\n");
}

void loop() {
    unsigned long currentMillis = millis();
    
    if (currentMillis - lastSend >= SEND_INTERVAL) {
        lastSend = currentMillis;
        
        float ph = readPhSensor();
        
        if (ph >= 0) {
            permData.soil_ph = ph;
            sendData();
        } else {
            Serial.println("Skip pengiriman: data pH tidak valid\n");
        }
    }
}
