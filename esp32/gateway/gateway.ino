// ===== KONFIGURASI - UBAH SESUAI KEBUTUHAN =====
#include <WiFi.h>
#include <esp_now.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

const char* WIFI_SSID = "NamaWiFi";
const char* WIFI_PASS = "PasswordWiFi";
const char* SERVER_URL = "http://192.168.1.100/SISTEM-IOT-PESANTREN/api/";
const char* API_KEY_SOLAR = "SOLAR_API_KEY_2024";
const char* API_KEY_DRYER = "DRYER_API_KEY_2024";
const char* API_KEY_CATTLE = "CATTLE_API_KEY_2024";
const char* API_KEY_PERM = "PERM_API_KEY_2024";
unsigned long SEND_INTERVAL = 30000; // 30 detik
unsigned long RELAY_CHECK_INTERVAL = 10000; // 10 detik

unsigned long lastSendTime = 0;
unsigned long lastRelayCheck = 0;

// MAC Address node ESP-NOW (Sesuaikan dengan MAC address masing-masing node)
uint8_t MAC_NODE_SOLAR[] = {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x01};
uint8_t MAC_NODE_DRYER[] = {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x02};
uint8_t MAC_NODE_CATTLE[] = {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x03};
uint8_t MAC_NODE_PERM[] = {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x04};

// Struktur Data
typedef struct struct_solar {
    float temperature;
    float humidity;
    float voltage;
    float current;
    float power;
    float energy;
} struct_solar;

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

typedef struct struct_cattle {
    float liquid_level;
    float liquid_volume;
    float gas_pressure;
    int soil_moisture_raw;
    float soil_moisture_percent;
} struct_cattle;

typedef struct struct_perm {
    float soil_ph;
} struct_perm;

typedef struct struct_relay_cmd {
    int relay_pin;
    int status;
} struct_relay_cmd;

// Variabel penampung data terbaru
struct_solar latest_solar;
struct_dryer latest_dryer;
struct_cattle latest_cattle;
struct_perm latest_perm;

bool hasNewSolar = false;
bool hasNewDryer = false;
bool hasNewCattle = false;
bool hasNewPerm = false;

// Callback ESP-NOW Receive
void OnDataRecv(const uint8_t * mac, const uint8_t *incomingData, int len) {
    // Identifikasi pengirim dari ukuran data atau header
    if (len == sizeof(struct_solar)) {
        memcpy(&latest_solar, incomingData, sizeof(latest_solar));
        hasNewSolar = true;
        Serial.println("Terima data Solar");
    } 
    else if (len == sizeof(struct_dryer)) {
        memcpy(&latest_dryer, incomingData, sizeof(latest_dryer));
        hasNewDryer = true;
        Serial.println("Terima data Dryer");
    }
    else if (len == sizeof(struct_cattle)) {
        memcpy(&latest_cattle, incomingData, sizeof(latest_cattle));
        hasNewCattle = true;
        Serial.println("Terima data Cattle");
    }
    else if (len == sizeof(struct_perm)) {
        memcpy(&latest_perm, incomingData, sizeof(latest_perm));
        hasNewPerm = true;
        Serial.println("Terima data Permaculture");
    }
}

void setup() {
    Serial.begin(115200);
    
    // Connect to WiFi
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    Serial.print("Connecting to WiFi");
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    Serial.println("\nConnected to WiFi");
    
    // Init ESP-NOW
    if (esp_now_init() != ESP_OK) {
        Serial.println("Error initializing ESP-NOW");
        return;
    }
    
    esp_now_register_recv_cb(OnDataRecv);
    
    // Register peers (opsional jika hanya terima)
    esp_now_peer_info_t peerInfo;
    peerInfo.channel = 0;  
    peerInfo.encrypt = false;
    
    memcpy(peerInfo.peer_addr, MAC_NODE_DRYER, 6);
    if (esp_now_add_peer(&peerInfo) != ESP_OK){
        Serial.println("Failed to add peer Dryer");
    }
}

void sendPostRequest(String endpoint, String apiKey, String payload) {
    if(WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        String url = String(SERVER_URL) + endpoint;
        
        http.begin(url);
        http.addHeader("Content-Type", "application/x-www-form-urlencoded");
        http.addHeader("X-API-Key", apiKey);
        
        int httpResponseCode = http.POST(payload);
        Serial.print("POST to " + endpoint + " | Code: ");
        Serial.println(httpResponseCode);
        http.end();
    }
}

void loop() {
    unsigned long currentMillis = millis();
    
    // Send data to server periodically
    if (currentMillis - lastSendTime >= SEND_INTERVAL) {
        lastSendTime = currentMillis;
        
        if (hasNewSolar) {
            String payload = "device_id=2&temperature=" + String(latest_solar.temperature) +
                           "&humidity=" + String(latest_solar.humidity) +
                           "&voltage=" + String(latest_solar.voltage) +
                           "&current_amp=" + String(latest_solar.current) +
                           "&power=" + String(latest_solar.power) +
                           "&energy=" + String(latest_solar.energy);
            sendPostRequest("insert_solar.php", API_KEY_SOLAR, payload);
            hasNewSolar = false;
        }
        
        if (hasNewDryer) {
            String payload = "device_id=3&temperature=" + String(latest_dryer.temperature) +
                           "&humidity=" + String(latest_dryer.humidity) +
                           "&voltage_ac=" + String(latest_dryer.voltage_ac) +
                           "&current_ac=" + String(latest_dryer.current_ac) +
                           "&power_ac=" + String(latest_dryer.power_ac) +
                           "&relay_lamp=" + String(latest_dryer.relay_lamp);
            sendPostRequest("insert_dryer.php", API_KEY_DRYER, payload);
            hasNewDryer = false;
        }
        
        if (hasNewCattle) {
            String payload = "device_id=4&liquid_level=" + String(latest_cattle.liquid_level) +
                           "&gas_pressure=" + String(latest_cattle.gas_pressure) +
                           "&soil_moisture_raw=" + String(latest_cattle.soil_moisture_raw);
            sendPostRequest("insert_cattle.php", API_KEY_CATTLE, payload);
            hasNewCattle = false;
        }
        
        if (hasNewPerm) {
            String payload = "device_id=5&soil_ph=" + String(latest_perm.soil_ph);
            sendPostRequest("insert_permaculture.php", API_KEY_PERM, payload);
            hasNewPerm = false;
        }
    }
    
    // Check relay status periodically
    if (currentMillis - lastRelayCheck >= RELAY_CHECK_INTERVAL) {
        lastRelayCheck = currentMillis;
        if(WiFi.status() == WL_CONNECTED) {
            HTTPClient http;
            String url = String(SERVER_URL) + "get_relay_status.php?api_key=" + API_KEY_DRYER;
            http.begin(url);
            int httpCode = http.GET();
            
            if (httpCode == HTTP_CODE_OK) {
                String payload = http.getString();
                // Parse JSON (Simplified: dalam prakteknya gunakan ArduinoJson)
                if (payload.indexOf("\"status\":1") > 0) {
                    struct_relay_cmd cmd = {26, 1};
                    esp_now_send(MAC_NODE_DRYER, (uint8_t *) &cmd, sizeof(cmd));
                    Serial.println("Send Relay: ON");
                } else if (payload.indexOf("\"status\":0") > 0) {
                    struct_relay_cmd cmd = {26, 0};
                    esp_now_send(MAC_NODE_DRYER, (uint8_t *) &cmd, sizeof(cmd));
                    Serial.println("Send Relay: OFF");
                }
            }
            http.end();
        }
    }
}
