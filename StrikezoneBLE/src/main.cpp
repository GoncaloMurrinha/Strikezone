#include <Arduino.h>
#include <NimBLEDevice.h>

// IDs configuráveis
static const uint8_t  ARENA_ID  = 1;
static const uint8_t  FLOOR_ID  = 0;
static const uint8_t  ZONE_ID   = 1;
static const uint16_t BEACON_ID = 1;

static const char* DEVICE_NAME = "SZ-A1-Z1-B1";
static const char* SERVICE_UUID_STR = "12345678-1234-5678-1234-56789abcdef0";

static const esp_power_level_t TX_POWER = ESP_PWR_LVL_P9;

#define LED_PIN 2

// Manufacturer Data
std::string buildManufacturerData() {
    std::string data;
    data.reserve(8);

    data.push_back(0xFF);
    data.push_back(0xFF);

    data.push_back(0x01);

    data.push_back(ARENA_ID);
    data.push_back(FLOOR_ID);
    data.push_back(ZONE_ID);

    uint8_t hi = (uint8_t)((BEACON_ID >> 8) & 0xFF);
    uint8_t lo = (uint8_t)(BEACON_ID & 0xFF);

    data.push_back(hi);
    data.push_back(lo);

    return data;
}

void setup() {
    Serial.begin(115200);
    delay(300);

    pinMode(LED_PIN, OUTPUT);
    digitalWrite(LED_PIN, LOW);

    Serial.println("\nStrikeZone – Beacon BLE (NimBLE-Arduino)");

    NimBLEDevice::init(DEVICE_NAME);
    NimBLEDevice::setPower(TX_POWER);

    NimBLEAdvertising* adv = NimBLEDevice::getAdvertising();

    NimBLEAdvertisementData adData;
    adData.setFlags(0x06);
    adData.addServiceUUID(NimBLEUUID(SERVICE_UUID_STR));
    adData.setManufacturerData(buildManufacturerData());

    adv->setAdvertisementData(adData);

    // IMPORTANTE: linha correta para NÃO usar scan response
    NimBLEAdvertisementData empty;
    adv->setScanResponseData(empty);

    adv->setMinInterval(160);
    adv->setMaxInterval(320);

    if (adv->start()) {
        Serial.println("Advertising iniciado com sucesso!");
    } else {
        Serial.println("ERRO ao iniciar advertising!");
    }
}

void loop() {
    static unsigned long t = 0;

    if (millis() - t > 2000) {
        t = millis();
        Serial.println("[Beacon] a anunciar...");
    }

    digitalWrite(LED_PIN, HIGH);
    delay(50);
    digitalWrite(LED_PIN, LOW);
    delay(950);
}
