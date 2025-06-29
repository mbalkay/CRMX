# Gelişmiş Günlük E-posta Bildirim Sistemi

## Genel Bakış

Insurance CRM sistemi artık gelişmiş günlük e-posta bildirim sistemi ile donatılmıştır. Bu sistem, temsilciler ve yöneticiler için her sabah 8:00'de kişiselleştirilmiş günlük özet e-postaları gönderir.

## Özellikler

### 🎯 Temsilci Bildirimleri
- **Kişiselleştirilmiş İçerik**: Her temsilci sadece kendi müşterilerine ait verileri görür
- **Bugünkü Görevler**: Gün içinde tamamlanması gereken görevler
- **Yaklaşan Poliçe Yenilemeleri**: 30 gün içinde yenilenecek poliçeler
- **Yaklaşan Görevler**: 7 gün içinde tamamlanması gereken görevler
- **Performans İstatistikleri**: Bu ay ve bu hafta performans özeti
- **Hızlı Erişim Linkleri**: Panele direkt erişim linkleri

### 👔 Yönetici Bildirimleri
- **Sistem Genel Bakış**: Tüm sistemdeki kritik veriler
- **Kritik Uyarılar**: Gecikmiş görevler ve acil durumlar
- **Temsilci Performansı**: Tüm temsilcilerin performans özeti
- **Bekleyen İşlemler**: Sistem genelindeki bekleyen görevler
- **Poliçe Yenilemeleri**: Tüm yaklaşan poliçe yenilemeleri

## Teknik Detaylar

### Dosya Yapısı
```
includes/notifications/
├── class-enhanced-email-notifications.php     # Ana bildirim sınıfı
├── class-notification-scheduler.php           # Zamanlama sınıfı
└── email-templates/
    ├── email-base-template.php               # Temel HTML şablonu
    ├── representative-daily-summary.php      # Temsilci şablonu
    └── manager-daily-report.php             # Yönetici şablonu
```

### Cron Zamanlaması
- **Zamanlama**: Her gün sabah 8:00
- **Cron Hook**: `insurance_crm_daily_email_notifications`
- **Özelleştirilmiş Zaman**: `daily_8am` (24 saatlik)

### Ayarlar
- **Admin Ayarları**: WordPress Admin > Insurance CRM Ayarları > Bildirimler
- **Kullanıcı Tercihleri**: Temsilci Paneli > Ayarlar > Bildirimler
- **Test Fonksiyonu**: Admin panelinden test e-postası gönderme

## Kullanım

### Admin Ayarları
1. WordPress Admin paneline giriş yapın
2. Insurance CRM Ayarları > Bildirimler sekmesine gidin
3. "Günlük E-posta Bildirimleri" seçeneğini etkinleştirin
4. "Test Günlük E-posta Gönder" butonu ile test edin

### Kullanıcı Ayarları
1. Temsilci paneline giriş yapın
2. Ayarlar > Bildirimler sekmesine gidin
3. "📊 Günlük E-posta Özeti" seçeneğini işaretleyin
4. Ayarları kaydedin

## E-posta Şablonları

### Responsive Tasarım
- Mobil uyumlu HTML e-posta şablonları
- Modern gradient tasarım
- Şirket logosu ve kurumsal renk paleti
- Dark mode desteği

### İçerik Bölümleri

#### Temsilci E-postası
1. **Karşılama ve Tarih**
2. **Hızlı İstatistik Kartları**
3. **Bugünkü Görevler**
4. **Yaklaşan Poliçe Yenilemeleri**
5. **Yaklaşan Görevler**
6. **Performans İstatistikleri**
7. **Hızlı Erişim Linkleri**
8. **Motivasyon Mesajı**

#### Yönetici E-postası
1. **Sistem Genel Bakış**
2. **Kritik Uyarılar**
3. **Bugünkü Öncelikli Görevler**
4. **Yaklaşan Poliçe Yenilemeleri**
5. **Temsilci Performans Tablosu**
6. **Bekleyen Görevler Dağılımı**
7. **Yönetim Paneli Linkleri**
8. **Günlük Özet**

## Güvenlik ve Performans

### Güvenlik
- Nonce kontrolü ile CSRF koruması
- Kullanıcı yetki kontrolü
- SQL injection koruması
- E-posta validation

### Performans
- Veritabanı sorgu optimizasyonu
- Bellek kullanımı optimizasyonu
- Hata yakalama ve loglama
- İstatistik toplama

## Veritabanı

### Gerekli Tablolar
- `wp_insurance_crm_representatives`
- `wp_insurance_crm_customers`
- `wp_insurance_crm_policies`
- `wp_insurance_crm_tasks`

### Otomatik Sütun Ekleme
Sistem otomatik olarak gerekli `representative_id` sütunlarını kontrol eder ve ekler:
- `policies` tablosuna `representative_id`
- `tasks` tablosuna `representative_id`

### Ayar Anahtarları
- `insurance_crm_settings['daily_email_notifications']`
- `crm_daily_email_notifications` (user meta)
- `insurance_crm_last_daily_email_run`
- `insurance_crm_notification_stats`

## İstatistikler ve Loglama

### Bildirim İstatistikleri
- Günlük gönderim sayısı
- Hata sayısı
- Son çalışma zamanı
- 30 günlük geçmiş

### Log Kayıtları
- Başarılı gönderimler
- Hata durumları
- Sistem durumu
- Performans verileri

## Troubleshooting

### Ortak Sorunlar
1. **E-postalar gönderilmiyor**
   - WordPress e-posta ayarlarını kontrol edin
   - SMTP plugin kullanın
   - Günlük e-posta ayarının aktif olduğundan emin olun

2. **Cron çalışmıyor**
   - WordPress cron sistemini kontrol edin
   - Hosting provider cron ayarlarını kontrol edin
   - WP-Cron Alternative plugin kullanın

3. **Şablonlar düzgün görünmüyor**
   - E-posta istemcisi uyumluluğunu kontrol edin
   - Responsive tasarım test edin
   - Inline CSS kullanın

### Debug Modları
- WordPress debug log'ları etkinleştirin
- Insurance CRM log görüntüleyicisini kullanın
- Browser developer tools ile test edin

## Özelleştirme

### Şablon Özelleştirme
E-posta şablonları `includes/notifications/email-templates/` dizininde bulunur ve özelleştirilebilir.

### Zamanlama Değişikliği
`class-notification-scheduler.php` dosyasında zamanlama ayarları değiştirilebilir.

### Yeni Bildirim Türleri
Sistem genişletilebilir tasarımda olup yeni bildirim türleri eklenebilir.

## Sürüm Bilgileri

- **Sürüm**: 1.2.0
- **Geliştirici**: Anadolu Birlik
- **Tarih**: 2025
- **Uyumluluk**: WordPress 5.0+ | PHP 7.4+

## Destek

Sorunlar ve öneriler için:
- GitHub Issues
- Teknik destek ekibi
- Dokümantasyon güncellemeleri