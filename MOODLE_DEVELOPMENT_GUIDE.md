# Moodle Geliştirme Rehberi: local/recognition Örneği

## 1. Temel Moodle Modül Yapısı

### Kritik Dosyalar ve Görevleri

1. **version.php**
   - Her Moodle modülünün olmazsa olmazıdır
   - Modül versiyonu, gerekli Moodle versiyonu ve plugin bilgilerini içerir
   - Veritabanı güncellemeleri için versiyon numarası kullanılır

2. **lib.php**
   - Modülün ana kütüphane dosyasıdır
   - Temel fonksiyonlar ve hook'lar burada tanımlanır
   - Moodle core ile entegrasyon noktalarını içerir

3. **db/ dizini**
   - **access.php**: Yetkilendirme tanımlamaları
   - **install.xml**: Veritabanı şeması
   - **upgrade.php**: Versiyon yükseltme işlemleri
   - **events.php**: Event tanımlamaları

4. **classes/ dizini**
   - Nesne yönelimli kod organizasyonu
   - Event sınıfları
   - Task sınıfları
   - External API sınıfları

5. **lang/ dizini**
   - Çoklu dil desteği için dil dosyaları
   - Her dil için ayrı dizin (örn: en/, tr/)

6. **templates/ dizini**
   - Mustache template dosyaları
   - Frontend görünüm şablonları

7. **amd/ dizini**
   - JavaScript modülleri
   - RequireJS kullanarak modüler JS yapısı

## 2. Moodle Geliştirme Prensipleri

### 2.1 Güvenlik

1. **Form İşlemleri**
   - Her form mutlaka sesskey kontrolü içermeli
   - Required_capability kontrolleri yapılmalı
   - XSS koruması için clean_param kullanılmalı

2. **SQL Güvenliği**
   - Global $DB objesi kullanılmalı
   - Asla raw SQL kullanılmamalı
   - $DB->get_record(), $DB->get_records() gibi metodlar tercih edilmeli

### 2.2 Kodlama Standartları

1. **Naming Conventions**
   - Fonksiyonlar: local_recognition_function_name
   - Sınıflar: \local_recognition\class_name
   - Değişkenler: snake_case kullanımı

2. **Dökümantasyon**
   - PHPDoc blokları kullanılmalı
   - Fonksiyon ve sınıf açıklamaları detaylı olmalı
   - @param, @return gibi etiketler kullanılmalı

### 2.3 Veritabanı İşlemleri

1. **XMLDB Editör**
   - Veritabanı şeması için XMLDB kullanımı
   - install.xml dosyasının önemi
   - Upgrade işlemleri için versiyon kontrolü

2. **DB API Kullanımı**
   ```php
   // Örnek kullanımlar:
   $record = $DB->get_record('recognition', ['id' => $id]);
   $records = $DB->get_records('recognition', ['status' => 1]);
   $DB->insert_record('recognition', $dataobject);
   ```

### 2.4 Frontend Geliştirme

1. **Template Sistemi**
   - Mustache template engine kullanımı
   - Context hazırlama
   - Partial template kullanımı

2. **JavaScript Modülleri**
   ```javascript
   // AMD modül örneği
   define(['jquery'], function($) {
       return {
           init: function() {
               // Modül başlatma kodu
           }
       };
   });
   ```

### 2.5 Event ve Observer Sistemi

#### 2.5.1 Event Sistemi

1. **Event Sınıfı Oluşturma**
   ```php
   namespace local_pluginname\event;
   
   class user_action_completed extends \core\event\base {
       protected function init() {
           $this->data['crud'] = 'c'; // create, read, update, delete
           $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
           $this->data['objecttable'] = 'local_pluginname_records';
       }
       
       public static function create_from_record($record) {
           $event = self::create(array(
               'context' => context_system::instance(),
               'objectid' => $record->id,
               'relateduserid' => $record->userid,
               'other' => array(
                   'extra' => $record->extra_data
               )
           ));
           return $event;
       }
       
       public function get_description() {
           return "Kullanıcı {$this->relateduserid} tarafından aksiyon tamamlandı.";
       }
       
       public function get_url() {
           return new \moodle_url('/local/pluginname/view.php', 
               array('id' => $this->objectid));
       }
   }
   ```

2. **Event Özellikleri**
   - **crud**: Event'in tipini belirtir
     - 'c': create (oluşturma)
     - 'r': read (okuma)
     - 'u': update (güncelleme)
     - 'd': delete (silme)
   
   - **edulevel**: Eğitimsel seviye
     - LEVEL_TEACHING: Öğretim aktivitesi
     - LEVEL_PARTICIPATING: Katılım aktivitesi
     - LEVEL_OTHER: Diğer aktiviteler

   - **objecttable**: Event'in ilişkili olduğu veritabanı tablosu

3. **Event Trigger Etme**
   ```php
   // Event oluşturma ve tetikleme
   $event = \local_pluginname\event\user_action_completed::create_from_record($record);
   $event->trigger();
   
   // veya direkt olarak
   \local_pluginname\event\user_action_completed::create($eventdata)->trigger();
   ```

#### 2.5.2 Observer Sistemi

1. **Observer Tanımlama (db/events.php)**
   ```php
   $observers = array(
       array(
           'eventname' => '\core\event\user_created',
           'callback' => '\local_pluginname\observer::user_created',
           'priority' => 9999,
           'internal' => false
       ),
       array(
           'eventname' => '\local_pluginname\event\user_action_completed',
           'callback' => '\local_pluginname\observer::action_completed',
           'internal' => true,
           'includefile' => '/local/pluginname/classes/observer.php'
       )
   );
   ```

   **Observer Parametreleri:**
   - **eventname**: Dinlenecek event'in tam adı
   - **callback**: Event tetiklendiğinde çağrılacak fonksiyon
   - **priority**: Observer'ın çalışma önceliği (yüksek sayı = erken çalışma)
   - **internal**: Plugin içi event ise true
   - **includefile**: Observer sınıfının bulunduğu dosya (opsiyonel)

2. **Observer Sınıfı**
   ```php
   namespace local_pluginname;
   
   class observer {
       /**
        * Yeni kullanıcı oluşturulduğunda çalışır
        *
        * @param \core\event\user_created $event
        */
       public static function user_created(\core\event\user_created $event) {
           global $DB;
           
           // Event verilerine erişim
           $userid = $event->objectid;
           $user = $DB->get_record('user', array('id' => $userid));
           
           // Event'in context'ine erişim
           $context = $event->get_context();
           
           // Extra verilere erişim
           $otherdata = $event->other;
           
           // İşlem yapma
           self::process_new_user($user, $context);
       }
       
       /**
        * Kullanıcı aksiyonu tamamlandığında çalışır
        *
        * @param \local_pluginname\event\user_action_completed $event
        */
       public static function action_completed($event) {
           // Event verilerini logla
           $logdata = array(
               'userid' => $event->relateduserid,
               'action' => 'completed',
               'objectid' => $event->objectid,
               'extra' => $event->other['extra']
           );
           
           // Log kaydı oluştur
           self::log_action($logdata);
           
           // Bildirim gönder
           self::send_notification($event);
       }
       
       /**
        * Aksiyon loglarını kaydet
        */
       private static function log_action($data) {
           global $DB;
           $DB->insert_record('local_pluginname_logs', $data);
       }
       
       /**
        * Bildirim gönder
        */
       private static function send_notification($event) {
           $message = new \core\message\message();
           $message->component = 'local_pluginname';
           $message->name = 'action_notification';
           $message->userfrom = core_user::get_support_user();
           $message->userto = $event->relateduserid;
           $message->subject = get_string('notification_subject', 'local_pluginname');
           $message->fullmessage = get_string('notification_message', 'local_pluginname');
           $message->fullmessageformat = FORMAT_MARKDOWN;
           $message->smallmessage = '';
           $message->notification = 1;
           
           message_send($message);
       }
   }
   ```

3. **Best Practices**
   - Her event için ayrı bir sınıf oluşturun
   - Event verilerini doğru şekilde validate edin
   - Observer'ları modüler ve tek sorumluluk prensibine uygun yazın
   - Ağır işlemleri asenkron yapın (adhoc task kullanın)
   - Event ve observer'ları iyi dokümante edin

4. **Yaygın Kullanım Senaryoları**
   - Kullanıcı işlemleri (oluşturma, güncelleme, silme)
   - Kurs işlemleri (oluşturma, güncelleme, silme)
   - Öğrenci kaydı işlemleri
   - Not değişiklikleri
   - İçerik değişiklikleri
   - Sistem ayarları değişiklikleri
   - Özel iş akışları ve otomasyonlar

5. **Debugging ve Troubleshooting**
   ```php
   // Event'i debug modunda trigger et
   $event = \local_pluginname\event\user_action_completed::create_from_record($record);
   $event->set_debug();
   $event->trigger();
   
   // Event verilerini kontrol et
   debugging('Event data: ' . json_encode($event->get_data()));
   
   // Observer çalışmasını logla
   debugging('Observer called for event: ' . $event->eventname);
   ```

### 2.6 Cache ve Performance Optimizasyonu

1. **Cache Tanımlama**
   ```php
   // db/caches.php
   $definitions = array(
       'userdata' => array(
           'mode' => cache_store::MODE_APPLICATION,
           'simplekeys' => true,
           'staticacceleration' => true,
           'staticaccelerationsize' => 30
       )
   );
   ```

2. **Cache Kullanımı**
   ```php
   function local_pluginname_get_user_data($userid) {
       // Cache instance
       $cache = cache::make('local_pluginname', 'userdata');
       
       // Cache'den veri alma
       $data = $cache->get($userid);
       if ($data === false) {
           // Cache'de yoksa hesapla ve cache'e ekle
           $data = calculate_user_data($userid);
           $cache->set($userid, $data);
       }
       
       return $data;
   }
   ```

### 2.7 Güvenlik ve Yetkilendirme

1. **Capability Tanımlama**
   ```php
   // db/access.php
   $capabilities = array(
       'local/pluginname:manage' => array(
           'riskbitmask' => RISK_CONFIG,
           'captype' => 'write',
           'contextlevel' => CONTEXT_SYSTEM,
           'archetypes' => array(
               'manager' => CAP_ALLOW
           )
       )
   );
   ```

2. **Yetki Kontrolü**
   ```php
   function local_pluginname_check_access() {
       global $USER, $COURSE;
       
       $context = context_course::instance($COURSE->id);
       if (!has_capability('local/pluginname:manage', $context)) {
           return false;
       }
       
       return true;
   }
   ```

### 2.8 Best Practices ve Öneriler

1. **Hook Kullanım Prensipleri**
   - Hook'ları mümkün olduğunca erken register edin
   - Performans için gereksiz hook çağrılarından kaçının
   - Hook'ların çalışma sırasını (priority) doğru ayarlayın

2. **Core Müdahale Stratejisi**
   - Her zaman event-based yaklaşımı tercih edin
   - Direct DB manipulation yerine API kullanın
   - Cache mekanizmasını etkin kullanın

3. **Kod Organizasyonu**
   - Hook'ları lib.php'de tanımlayın
   - Karmaşık işlemleri classes/ altında sınıflara ayırın
   - Event observer'ları ayrı bir namespace'de tutun

## 3. Best Practices

1. **Modüler Kod Yazımı**
   - Tek sorumluluk prensibi
   - Kod tekrarından kaçınma
   - Bağımlılıkların minimize edilmesi

2. **Performans**
   - Gereksiz DB sorgularından kaçınma
   - Caching mekanizmalarının kullanımı
   - Lazy loading uygulaması

3. **Hata Yönetimi**
   - try-catch blokları
   - Anlamlı hata mesajları
   - Loglama mekanizması

4. **Testing**
   - PHPUnit test yazımı
   - Behat testleri
   - Code coverage önemi

## 4. Debugging

1. **Debugging Teknikleri**
   - debugging = true ayarı
   - xdebug kullanımı
   - Console logging

2. **Common Issues**
   - Yetki sorunları
   - Cache problemleri
   - JavaScript hataları

## 5. Deployment

1. **Version Control**
   - Git kullanımı
   - Branch stratejisi
   - Commit mesajları

2. **Release Süreci**
   - Version numaralandırma
   - Upgrade testleri
   - Release notları

## 6. Mülakat Soruları ve Cevapları

### Temel Moodle Kavramları

1. **S: Moodle'da bir plugin geliştirirken hangi temel dosyalar gereklidir?**
   - C: En temel gerekli dosyalar:
     - version.php (Plugin versiyonu ve metadata)
     - lib.php (Ana fonksiyonlar)
     - db/install.xml (Veritabanı yapısı)
     - db/access.php (Yetkilendirmeler)
     - lang/en/plugintype_pluginname.php (Dil dosyası)

2. **S: Moodle'da capabilities nasıl çalışır?**
   - C: Capabilities, Moodle'ın yetkilendirme sistemidir:
     - db/access.php'de tanımlanır
     - has_capability() ile kontrol edilir
     - Context levels ile hiyerarşik yapı oluşturulur
     - Roller (roles) capabilities üzerine inşa edilir

3. **S: Moodle'da bir form nasıl oluşturulur?**
   - C: Moodle forms:
     - moodleform sınıfından extend edilir
     - definition() metodunda form elemanları tanımlanır
     - Form elemanları için $mform->addElement() kullanılır
     - Validation için validation() metodu override edilir

### Veritabanı ve Güvenlik

4. **S: Moodle'da veritabanı işlemleri nasıl yapılır?**
   - C: Global $DB objesi kullanılır:
     ```php
     $record = $DB->get_record('table', ['field' => 'value']);
     $records = $DB->get_records('table', $conditions);
     $DB->insert_record('table', $dataobject);
     $DB->update_record('table', $dataobject);
     ```

5. **S: SQL injection'dan korunmak için neler yapılmalıdır?**
   - C: Güvenli veritabanı kullanımı için:
     - Asla raw SQL kullanılmamalı
     - $DB metodları tercih edilmeli
     - Parametreler için placeholders kullanılmalı
     - clean_param() ile input temizlenmeli

### Frontend ve JavaScript

6. **S: Moodle'da JavaScript nasıl organize edilir?**
   - C: AMD (Asynchronous Module Definition) kullanılır:
     ```javascript
     define(['jquery', 'core/ajax'], function($, ajax) {
         return {
             init: function() {
                 // Module initialization
             }
         };
     });
     ```

7. **S: Moodle template sistemi nasıl çalışır?**
   - C: Mustache template engine kullanılır:
     - templates/ dizininde .mustache dosyaları
     - PHP'den context data hazırlanır
     - $OUTPUT->render_from_template() ile render edilir

### Event ve Notification

8. **S: Moodle'da custom event nasıl oluşturulur?**
   - C: Event sistemi için:
     - classes/event/ altında event sınıfı oluşturulur
     - \core\event\base'den extend edilir
     - init(), get_data(), get_description() metodları implement edilir
     - Event trigger edilir: event::create($data)->trigger();

9. **S: Observer pattern Moodle'da nasıl kullanılır?**
   - C: Observer kullanımı:
     - db/events.php'de observer tanımlanır
     - Observer sınıfı oluşturulur
     - Statik observe metodu implement edilir

### Performance ve Debugging

10. **S: Moodle'da performans optimizasyonu için neler yapılabilir?**
    - C: Performans iyileştirmeleri:
      - get_records yerine get_records_sql kullanımı
      - Gereksiz DB sorgularından kaçınma
      - Caching API kullanımı
      - Lazy loading implementasyonu

11. **S: Moodle'da debugging nasıl yapılır?**
    - C: Debugging teknikleri:
      - config.php'de debugging aktif edilir
      - debugging() fonksiyonu kullanılır
      - error_log() ile loglama yapılır
      - xdebug entegrasyonu kullanılır

### Plugin Geliştirme

12. **S: Yeni bir Moodle plugin'i geliştirirken izlenecek adımlar nelerdir?**
    - C: Plugin geliştirme adımları:
      1. Plugin tipi belirlenir (local, mod, block vs.)
      2. Temel dosya yapısı oluşturulur
      3. version.php hazırlanır
      4. Veritabanı tabloları tanımlanır
      5. Capabilities belirlenir
      6. Ana fonksiyonlar yazılır
      7. Dil dosyaları hazırlanır
      8. Test edilir

13. **S: Moodle web servisleri nasıl geliştirilir?**
    - C: Web servis geliştirme:
      - classes/external/ altında servis sınıfı oluşturulur
      - execute(), get_parameters(), get_returns() metodları implement edilir
      - db/services.php'de servis tanımlanır
      - Yetkilendirme için capabilities eklenir

### Moodle Upgrade Process

14. **S: Plugin versiyonu nasıl yükseltilir?**
    - C: Upgrade süreci:
      1. version.php'de versiyon numarası artırılır
      2. db/upgrade.php'de upgrade fonksiyonu yazılır
      3. Yeni capabilities eklenir
      4. Veritabanı değişiklikleri XMLDB ile yapılır
      5. Upgrade fonksiyonunda savepoint kullanılır

15. **S: XMLDB Editor ne işe yarar ve nasıl kullanılır?**
    - C: XMLDB Editor:
      - Veritabanı şeması oluşturmak için kullanılır
      - Site administration > Development altında bulunur
      - Tablo, alan ve index tanımlamaları yapılır
      - install.xml otomatik oluşturulur

### Code Quality ve Testing

16. **S: Moodle'da unit testing nasıl yapılır?**
    - C: PHPUnit testing:
      - tests/ dizininde test sınıfları oluşturulur
      - advanced_testcase'den extend edilir
      - setUp() ve tearDown() metodları kullanılır
      - assert metodları ile testler yazılır

17. **S: Moodle coding style nedir ve neden önemlidir?**
    - C: Coding style:
      - PSR standartları temel alınır
      - moodle-local_codechecker ile kontrol edilir
      - Okunabilirlik ve maintainability için önemli
      - Consistent kod yapısı sağlar

### Practical Skills

18. **S: Bir Moodle plugin'i nasıl debug edilir?**
    - C: Debug stratejisi:
      1. debugging = true ayarlanır
      2. error_log kullanılır
      3. xdebug breakpoint'leri konur
      4. Browser console kontrol edilir
      5. PHP error log incelenir

19. **S: Cache API nasıl kullanılır?**
    - C: Cache kullanımı:
      ```php
      // Cache tanımlama
      $cache = cache::make('local_recognition', 'mycache');
      
      // Cache kullanımı
      $cache->set('key', $data);
      $data = $cache->get('key');
      ```

20. **S: Moodle'da tipik bir AJAX isteği nasıl yapılır?**
    - C: AJAX implementasyonu:
      ```php
      // PHP tarafı (external function)
      public static function get_data($param) {
          return ['data' => $result];
      }
      
      // JavaScript tarafı
      require(['core/ajax'], function(ajax) {
          ajax.call([{
              methodname: 'local_recognition_get_data',
              args: { param: value },
              done: function(response) {
                  console.log(response);
              },
              fail: function(ex) {
                  console.log(ex);
              }
          }]);
      });
      ```

Bu sorular ve cevaplar, bir Moodle developer mülakatında karşılaşabileceğiniz temel konuları kapsamaktadır. Her bir cevabı detaylı olarak incelemeniz ve pratik yapmanız önerilir.

## Sonuç

Bu rehber, Moodle geliştirme sürecinin temel prensiplerini ve best practice'lerini local/recognition modülü üzerinden açıklamaktadır. Moodle'ın geniş ekosisteminde başarılı bir geliştirici olmak için bu prensipleri anlamak ve uygulamak önemlidir.

## 7. Moodle Hook Sistemi ve Core Müdahale Etmeden Geliştirme

### 7.1 Temel Hook'lar ve Kullanımları

1. **before_http_headers**
   ```php
   function local_pluginname_before_http_headers() {
       // Sayfa yüklenmeden önce çalışır
       // HTTP header'lar gönderilmeden önce kontrol/müdahale için ideal
       // Örnek: Kullanıcı limiti kontrolü, yetkilendirme
   }
   ```

2. **after_config**
   ```php
   function local_pluginname_after_config() {
       // Config.php yüklendikten hemen sonra çalışır
       // Sistem ayarlarına erken erişim gerektiğinde kullanılır
       // Örnek: Sistem genelinde kısıtlamalar, global ayarlar
   }
   ```

3. **before_standard_html_head**
   ```php
   function local_pluginname_before_standard_html_head() {
       // HTML head bölümü oluşturulmadan önce çalışır
       // CSS, JS ekleme/değiştirme için ideal
       // Örnek: Form modifikasyonları, UI değişiklikleri
   }
   ```

4. **extend_navigation**
   ```php
   function local_pluginname_extend_navigation(global_navigation $navigation) {
       // Navigasyon menüsünü modifiye etmek için
       // Örnek: Özel menü öğeleri ekleme
       $node = $navigation->add(
           get_string('menuitem', 'local_pluginname'),
           new moodle_url('/local/pluginname/view.php')
       );
   }
   ```

### 7.2 Core'a Müdahale Etmeden Kontrol Stratejileri

1. **Sayfa Erişim Kontrolü**
   ```php
   function local_pluginname_before_require_login() {
       global $PAGE;
       
       // URL kontrolü
       $url = $PAGE->url->get_path();
       if (strpos($url, '/user/editadvanced.php') !== false) {
           // Erişim kontrolü
           if (!local_pluginname_check_access()) {
               redirect(new moodle_url('/'));
           }
       }
   }
   ```

2. **Form Modifikasyonu**
   ```php
   function local_pluginname_before_standard_html_head() {
       global $PAGE;
       
       // Form sayfası kontrolü
       if ($PAGE->pagetype === 'admin-user-user_bulk_upload') {
           // JavaScript ile form kontrolü
           $PAGE->requires->js_call_amd(
               'local_pluginname/form_modifier',
               'init'
           );
       }
   }
   ```

3. **Kullanıcı İşlemleri Kontrolü**
   ```php
   class local_pluginname_observer {
       public static function user_created(\core\event\user_created $event) {
           // Kullanıcı oluşturma sonrası kontrol
           $data = $event->get_data();
           if (!local_pluginname_validate_user($data['objectid'])) {
               // İşlemi geri al veya modifiye et
           }
       }
   }
   ```

### 7.3 Event-Based Kontrol Mekanizması

1. **Event Observer Tanımlama**
   ```php
   // db/events.php
   $observers = array(
       array(
           'eventname' => '\core\event\user_created',
           'callback' => '\local_pluginname\observer::user_created',
           'priority' => 9999
       )
   );
   ```

2. **Observer Sınıfı**
   ```php
   namespace local_pluginname;
   
   class observer {
       public static function user_created(\core\event\user_created $event) {
           global $DB;
           
           // Event verilerine erişim
           $userid = $event->objectid;
           $user = $DB->get_record('user', array('id' => $userid));
           
           // Kontrol ve müdahale
           if (!self::validate_user_creation($user)) {
               // İşlemi engelle veya modifiye et
           }
       }
   }
   ```

### 7.4 Cache ve Performance Optimizasyonu

1. **Cache Tanımlama**
   ```php
   // db/caches.php
   $definitions = array(
       'userdata' => array(
           'mode' => cache_store::MODE_APPLICATION,
           'simplekeys' => true,
           'staticacceleration' => true,
           'staticaccelerationsize' => 30
       )
   );
   ```

2. **Cache Kullanımı**
   ```php
   function local_pluginname_get_user_data($userid) {
       // Cache instance
       $cache = cache::make('local_pluginname', 'userdata');
       
       // Cache'den veri alma
       $data = $cache->get($userid);
       if ($data === false) {
           // Cache'de yoksa hesapla ve cache'e ekle
           $data = calculate_user_data($userid);
           $cache->set($userid, $data);
       }
       
       return $data;
   }
   ```

### 7.5 Güvenlik ve Yetkilendirme

1. **Capability Tanımlama**
   ```php
   // db/access.php
   $capabilities = array(
       'local/pluginname:manage' => array(
           'riskbitmask' => RISK_CONFIG,
           'captype' => 'write',
           'contextlevel' => CONTEXT_SYSTEM,
           'archetypes' => array(
               'manager' => CAP_ALLOW
           )
       )
   );
   ```

2. **Yetki Kontrolü**
   ```php
   function local_pluginname_check_access() {
       global $USER, $COURSE;
       
       $context = context_course::instance($COURSE->id);
       if (!has_capability('local/pluginname:manage', $context)) {
           return false;
       }
       
       return true;
   }
   ```

### 7.6 Best Practices ve Öneriler

1. **Hook Kullanım Prensipleri**
   - Hook'ları mümkün olduğunca erken register edin
   - Performans için gereksiz hook çağrılarından kaçının
   - Hook'ların çalışma sırasını (priority) doğru ayarlayın

2. **Core Müdahale Stratejisi**
   - Her zaman event-based yaklaşımı tercih edin
   - Direct DB manipulation yerine API kullanın
   - Cache mekanizmasını etkin kullanın

3. **Kod Organizasyonu**
   - Hook'ları lib.php'de tanımlayın
   - Karmaşık işlemleri classes/ altında sınıflara ayırın
   - Event observer'ları ayrı bir namespace'de tutun

Bu bölüm, Moodle'da core'a müdahale etmeden nasıl geliştirme yapılabileceğini ve hook sisteminin etkin kullanımını göstermektedir. Örnekler gerçek dünya senaryolarına dayanmaktadır ve best practice'leri içermektedir.
