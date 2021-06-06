//https://github.com/whoim2/esp32cam-timelaps-php-gallery
#include "esp_http_server.h"
#include "esp_http_client.h"
#include "esp_camera.h"
#include "Arduino.h"
#include <WiFi.h>
// Time
#include "time.h"
#include "lwip/err.h"
#include "lwip/apps/sntp.h"
// MicroSD
#include "driver/sdmmc_host.h"
#include "driver/sdmmc_defs.h"
#include "sdmmc_cmd.h"
#include "esp_vfs_fat.h"


char ssid[] = "wifi";
char password[] = "password";
#define POST_URL "http://yoursite.com/3dprint/"
#define NEW_URL "http://yoursite.com/3dprint/?new"
#define FRAMESIZE FRAMESIZE_UXGA
//#define USE_STREAMING 
/*FRAMESIZE_96X96,    // 96x96
FRAMESIZE_QQVGA,    // 160x120
FRAMESIZE_QCIF,     // 176x144
FRAMESIZE_HQVGA,    // 240x176
FRAMESIZE_240X240,  // 240x240
FRAMESIZE_QVGA,     // 320x240
FRAMESIZE_CIF,      // 400x296
FRAMESIZE_HVGA,     // 480x320
FRAMESIZE_VGA,      // 640x480
FRAMESIZE_SVGA,     // 800x600
FRAMESIZE_XGA,      // 1024x768
FRAMESIZE_HD,       // 1280x720
FRAMESIZE_SXGA,     // 1280x1024
FRAMESIZE_UXGA,     // 1600x1200
// 3MP Sensors
FRAMESIZE_FHD,      // 1920x1080
FRAMESIZE_P_HD,     //  720x1280
FRAMESIZE_P_3MP,    //  864x1536
FRAMESIZE_QXGA,     // 2048x1536
// 5MP Sensors
FRAMESIZE_QHD,      // 2560x1440
FRAMESIZE_WQXGA,    // 2560x1600
FRAMESIZE_P_FHD,    // 1080x1920
FRAMESIZE_QSXGA,    // 2560x1920
*/

#define TRIGGER_STATE HIGH //HIGH or LOW
#define TRIGGER_TIME_LIMIT 5000 //minimal timeout between triggers
#define SERIAL_DEBUG
#define TRIGGER_PIN 12


long trigger_millis = 0;
static esp_err_t cam_err;
static esp_err_t card_err;
char strftime_buf[64];
int file_number = 0;
bool internet_connected = false;
struct tm timeinfo;
time_t now;
bool new_flag = true;

// CAMERA_MODEL_AI_THINKER
#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27
#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22
camera_fb_t * fb = NULL;

#define PART_BOUNDARY "123456789000000000000987654321"
static const char* _STREAM_CONTENT_TYPE = "multipart/x-mixed-replace;boundary=" PART_BOUNDARY;
static const char* _STREAM_BOUNDARY = "\r\n--" PART_BOUNDARY "\r\n";
static const char* _STREAM_PART = "Content-Type: image/jpeg\r\nContent-Length: %u\r\n\r\n";
char * part_buf[64];
httpd_handle_t camera_httpd = NULL;
httpd_handle_t stream_httpd = NULL;
esp_err_t res = ESP_OK;

void setup()
{
  #ifdef SERIAL_DEBUG
  Serial.begin(115200);
  #endif
  pinMode(TRIGGER_PIN, INPUT);
  pinMode(33, OUTPUT); //led
  digitalWrite(33, LOW); //led on



  // SD camera init
  card_err = init_sdcard();

  delay(1000);

  if (init_wifi()) { // Connected to WiFi
    internet_connected = true;
    #ifdef SERIAL_DEBUG
    Serial.print("Internet connected, ip: ");
    Serial.println(WiFi.localIP());
    #endif
    init_time();
    time(&now);
    setenv("TZ", "GMT0BST,M3.5.0/01,M10.5.0/02", 1);
    tzset();   
  }

  camera_config_t config;
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = Y2_GPIO_NUM;
  config.pin_d1 = Y3_GPIO_NUM;
  config.pin_d2 = Y4_GPIO_NUM;
  config.pin_d3 = Y5_GPIO_NUM;
  config.pin_d4 = Y6_GPIO_NUM;
  config.pin_d5 = Y7_GPIO_NUM;
  config.pin_d6 = Y8_GPIO_NUM;
  config.pin_d7 = Y9_GPIO_NUM;
  config.pin_xclk = XCLK_GPIO_NUM;
  config.pin_pclk = PCLK_GPIO_NUM;
  config.pin_vsync = VSYNC_GPIO_NUM;
  config.pin_href = HREF_GPIO_NUM;
  config.pin_sscb_sda = SIOD_GPIO_NUM;
  config.pin_sscb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn = PWDN_GPIO_NUM;
  config.pin_reset = RESET_GPIO_NUM;
  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;
  //init with high specs to pre-allocate larger buffers
  //if (psramFound()) {
    config.frame_size = FRAMESIZE;
    config.jpeg_quality = 10;
    config.fb_count = 2;

  // camera init
  cam_err = esp_camera_init(&config);
  if (cam_err != ESP_OK) {
    #ifdef SERIAL_DEBUG
    Serial.printf("Camera init failed with error 0x%x", cam_err);
    #endif
    return;
  }
  sensor_t * s = esp_camera_sensor_get();
  s->set_framesize(s, FRAMESIZE);

  #ifdef USE_STREAMING
  startCameraServer();
  #endif
  if(cam_err == ESP_OK && card_err == ESP_OK) digitalWrite(33, HIGH); //led off if all ok
  #ifdef SERIAL_DEBUG
  Serial.println("ready");
  #endif
}

bool init_wifi()
{
  int connAttempts = 0;
  #ifdef SERIAL_DEBUG
  Serial.println("\r\nConnecting to: " + String(ssid));
  #endif
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED ) {
    delay(500);
    #ifdef SERIAL_DEBUG
    Serial.print(".");
    #endif
    if (connAttempts > 10) return false;
    connAttempts++;
  }
  return true;
}

void init_time()
{
  sntp_setoperatingmode(SNTP_OPMODE_POLL);
  sntp_setservername(0, "pool.ntp.org");
  sntp_init();
  // wait for time to be set
  time_t now = 0;
  timeinfo = { 0 };
  int retry = 0;
  const int retry_count = 10;
  while (timeinfo.tm_year < (2020 - 1900) && ++retry < retry_count) {
    #ifdef SERIAL_DEBUG
    Serial.printf("Waiting for system time to be set... (%d/%d)\n", retry, retry_count);
    #endif
    delay(2000);
    time(&now);
    localtime_r(&now, &timeinfo);
    #ifdef SERIAL_DEBUG
    if(timeinfo.tm_year > (2020 - 1900)) Serial.println("time set");
    #endif
  }
  #ifdef SERIAL_DEBUG
  if(timeinfo.tm_year < (2020 - 1900)) Serial.println("err to set time");
  #endif
}

esp_err_t _http_event_handler(esp_http_client_event_t *evt)
{
  switch (evt->event_id) {
    case HTTP_EVENT_ERROR:
      #ifdef SERIAL_DEBUG
      Serial.println("HTTP_EVENT_ERROR");
      #endif
      break;
    case HTTP_EVENT_ON_CONNECTED:
      #ifdef SERIAL_DEBUG
      Serial.println("HTTP_EVENT_ON_CONNECTED");
      #endif
      break;
    case HTTP_EVENT_HEADER_SENT:
      #ifdef SERIAL_DEBUG
      Serial.println("HTTP_EVENT_HEADER_SENT");
      #endif
      break;
    case HTTP_EVENT_ON_HEADER:
      #ifdef SERIAL_DEBUG
      Serial.println();
      Serial.printf("HTTP_EVENT_ON_HEADER, key=%s, value=%s", evt->header_key, evt->header_value);
      #endif
      break;
    case HTTP_EVENT_ON_DATA:
      #ifdef SERIAL_DEBUG
      Serial.println();
      Serial.printf("HTTP_EVENT_ON_DATA, len=%d", evt->data_len);
      #endif
      if (!esp_http_client_is_chunked_response(evt->client)) {
        // Write out data
        // printf("%.*s", evt->data_len, (char*)evt->data);
      }
      break;
    case HTTP_EVENT_ON_FINISH:
      #ifdef SERIAL_DEBUG
      Serial.println("");
      Serial.println("HTTP_EVENT_ON_FINISH");
      #endif
      break;
    case HTTP_EVENT_DISCONNECTED:
      #ifdef SERIAL_DEBUG
      Serial.println("HTTP_EVENT_DISCONNECTED");
      #endif
      break;
  }
  return ESP_OK;
}

static esp_err_t init_sdcard()
{
  esp_err_t ret = ESP_FAIL;
  sdmmc_host_t host = SDMMC_HOST_DEFAULT();
  sdmmc_slot_config_t slot_config = SDMMC_SLOT_CONFIG_DEFAULT();
  slot_config.width = 1; // 1-line mode so SD card data line 1 not used and therefore stops led flash
  esp_vfs_fat_sdmmc_mount_config_t mount_config = {
    .format_if_mount_failed = false,
    .max_files = 1,
  };
  sdmmc_card_t *card;

  #ifdef SERIAL_DEBUG
  Serial.println("Mounting SD card...");
  #endif
  ret = esp_vfs_fat_sdmmc_mount("/sdcard", &host, &slot_config, &mount_config, &card);

  if (ret == ESP_OK) {
    #ifdef SERIAL_DEBUG
    Serial.println("SD card mount successfully!");
    #endif
    return ESP_OK;
  }  else  {
    #ifdef SERIAL_DEBUG
    Serial.printf("Failed to mount SD card VFAT filesystem. Error: %s", esp_err_to_name(ret));
    #endif
    return ESP_FAIL;
  }
}

static esp_err_t save_photo_numbered()
{
  file_number++;
  #ifdef SERIAL_DEBUG
  Serial.print("Taking picture: ");
  Serial.print(file_number);
  #endif
  //camera_fb_t *fb = esp_camera_fb_get();

  //char *filename = (char*)malloc(21 + sizeof(int));
  char *filename = (char*)malloc(21 + sizeof(file_number));
  sprintf(filename, "/sdcard/capture_%d.jpg", file_number);

  Serial.println(filename);
  FILE *file = fopen(filename, "w");
  if (file != NULL)  {
    size_t err = fwrite(fb->buf, 1, fb->len, file);
    #ifdef SERIAL_DEBUG
    Serial.printf("File saved: %s\n", filename);
    #endif
  }  else  {
    #ifdef SERIAL_DEBUG
    Serial.println("Could not open file");
    #endif
  }
  fclose(file);
  //esp_camera_fb_return(fb);
  free(filename);
  return true; // didn't need this before ???
}

static esp_err_t save_photo_dated()
{
  //camera_fb_t *fb = esp_camera_fb_get();

  time(&now);
  localtime_r(&now, &timeinfo);
  strftime(strftime_buf, sizeof(strftime_buf), "%F_%H_%M_%S", &timeinfo);

  char *filename = (char*)malloc(21 + sizeof(strftime_buf));
  sprintf(filename, "/sdcard/capture_%s.jpg", strftime_buf);

  #ifdef SERIAL_DEBUG
  Serial.println(filename);
  #endif
  FILE *file = fopen(filename, "w");
  if (file != NULL)  {
    size_t err = fwrite(fb->buf, 1, fb->len, file);
    #ifdef SERIAL_DEBUG
    Serial.printf("File saved: %s\n", filename);
    #endif

  }  else  {
    #ifdef SERIAL_DEBUG
    Serial.println("Could not open file");
    #endif
  }
  fclose(file);
  //esp_camera_fb_return(fb);
  free(filename);
  return true; // didn't need this before ???
}

void save_photo()
{
  if (timeinfo.tm_year < (2016 - 1900) || internet_connected == false) { // if no internet or time not set
    save_photo_numbered(); // filenames in numbered order
  } else {
    save_photo_dated(); // filenames with date and time
  }
}

static esp_err_t take_send_photo()
{
  #ifdef SERIAL_DEBUG
  Serial.println("Sending picture...");
  #endif
  //camera_fb_t * fb = NULL;
  //esp_err_t res = ESP_OK;
  //fb = esp_camera_fb_get();
  
  if (!fb) {
    #ifdef SERIAL_DEBUG
    Serial.println("Camera capture failed");
    #endif
    return ESP_FAIL;
  }

  esp_http_client_handle_t http_client;
  
  esp_http_client_config_t config_client = {0};
  if(new_flag) {
    config_client.url = NEW_URL;
    new_flag = false;
  } else {
    config_client.url = POST_URL;
  }
  config_client.event_handler = _http_event_handler;
  config_client.method = HTTP_METHOD_POST;

  http_client = esp_http_client_init(&config_client);

  esp_http_client_set_post_field(http_client, (const char *)fb->buf, fb->len);

  esp_http_client_set_header(http_client, "Content-Type", "image/jpg");

  esp_err_t err = esp_http_client_perform(http_client);
  if (err == ESP_OK) {
    #ifdef SERIAL_DEBUG
    Serial.print("esp_http_client_get_status_code: ");
    Serial.println(esp_http_client_get_status_code(http_client));
    #endif
  }

  esp_http_client_cleanup(http_client);

  esp_camera_fb_return(fb);
}

static esp_err_t process_camera_feed(httpd_req_t *req)
{
  #ifdef SERIAL_DEBUG
  Serial.println("Starting camera feed");
  #endif
  res = httpd_resp_set_type(req, _STREAM_CONTENT_TYPE);
  while (true) {

    //  Serial.print(".");
    //fb = esp_camera_fb_get();

    if (res == ESP_OK) {
      size_t hlen = snprintf((char *)part_buf, 64, _STREAM_PART, fb->len);
      res = httpd_resp_send_chunk(req, (const char *)part_buf, hlen);
    }
    if (res == ESP_OK) {
      res = httpd_resp_send_chunk(req, (const char *)fb->buf, fb->len);
    }
    if (res == ESP_OK) {
      res = httpd_resp_send_chunk(req, _STREAM_BOUNDARY, strlen(_STREAM_BOUNDARY));
    }

    esp_camera_fb_return(fb);
    //fb = NULL;
  }
  return res;
}

static esp_err_t index_handler(httpd_req_t *req) {
  httpd_resp_set_type(req, "text/html");
  return httpd_resp_send(req, (const char *)"hello", 6);
}

#ifdef USE_STREAMING
void startCameraServer()
{
  httpd_config_t config = HTTPD_DEFAULT_CONFIG();

  httpd_uri_t index_uri = {
    .uri       = "/",
    .method    = HTTP_GET,
    .handler   = index_handler,
    .user_ctx  = NULL
  };

  httpd_uri_t stream_uri = {
    .uri       = "/stream",
    .method    = HTTP_GET,
    .handler   = process_camera_feed,
    .user_ctx  = NULL
  };

  Serial.printf("Starting web server on port: '%d'\n", config.server_port);
  if (httpd_start(&camera_httpd, &config) == ESP_OK) {
    httpd_register_uri_handler(camera_httpd, &index_uri);
  }

  config.server_port += 1;
  config.ctrl_port += 1;
  #ifdef SERIAL_DEBUG
  Serial.printf("Starting stream server on port: '%d'\n", config.server_port);
  #endif
  if (httpd_start(&stream_httpd, &config) == ESP_OK) {
    httpd_register_uri_handler(stream_httpd, &stream_uri);
  }
}
#endif

void loop()
{
    //add in a delay to avoid repeat triggers
    if (digitalRead(TRIGGER_PIN) == TRIGGER_STATE) {
      if(millis() - trigger_millis > TRIGGER_TIME_LIMIT) {
        digitalWrite(33, LOW); //led on
        #ifdef SERIAL_DEBUG
        Serial.println("Capture triggered");
        #endif
        fb = esp_camera_fb_get();
        save_photo();
        take_send_photo();
        trigger_millis = millis();
        esp_camera_fb_return(fb);
        fb = NULL;
        digitalWrite(33, HIGH); //led off
      } else {
          for (int i=0; i <= 5; i++) { //flash if time low
            digitalWrite(33, LOW);
            delay(100);
            digitalWrite(33, HIGH);
            delay(100);
          }
      }
    }
    delay(50);
}
