# WebKontrol

Framework veya Composer gerektirmeyen, PDO SQLite tabanlı site erişilebilirlik paneli. PHP 8.1+ ve `pdo_sqlite`, `curl` eklentileri gerekir. Linux, macOS veya Windows üzerinde uygun PHP kurulumu ile çalışır.

## Başlatma

```sh
export MONITOR_PASSWORD='uzun-ve-benzersiz-bir-parola'
php -S 127.0.0.1:8080 -t public
```

Tarayıcıda 8080 portunu açın ve belirlediğiniz parola ile giriş yapın. Parolayı kaynak koduna eklemeyin. Windows PowerShell'de değişkeni `$env:MONITOR_PASSWORD='...'` ile tanımlayabilirsiniz.

Üretimde PHP destekli HTTPS sunucusunun belge kökünü **yalnızca `public/`** dizinine ayarlayın. PHP'nin yerleşik sunucusu yerel geliştirme içindir. `src/`, `bin/` ve SQLite dosyalarını internete açmayın. Veri dizini varsayılan olarak `var/`; PHP işlemi burada yazabilmelidir. İsterseniz `MONITOR_DATA_DIR` ortam değişkeniyle web kökü dışında mutlak bir dizin belirleyin. SQLite dosyasını ve varsa WAL/SHM dosyalarını tutarlı bir SQLite yedeği ile yedekleyin.

## Otomatik kontrol

Linux cron örneği (PHP ve proje yollarını uyarlayın):

```cron
* * * * * /usr/bin/php /tam/yol/webkontrol/bin/check.php >> /tam/yol/webkontrol/var/cron.log 2>&1
```

Windows Görev Zamanlayıcı'da `php.exe` ile `bin/check.php` dosyasını dakikada bir çalıştırabilirsiniz. Sürekli açık panel gerekmez. Her sitenin aralığı ayrı tutulur (1–1440 dakika); cron dakikalık çalıştığından kontroller yaklaşık zamanlıdır. Çok sayıda yavaş site seri işleme nedeniyle gecikebilir. CLI ile web işlemi aynı `MONITOR_DATA_DIR` dizinini kullanmalıdır. Paneldeki “Şimdi kontrol et” tek siteyi hemen kontrol eder. Çakışan kontroller SQLite üzerinde süreli kilitle engellenir.

## Sonuçların anlamı

- HTTP 200–399: erişilebilir; 400–599, DNS/TLS/bağlantı hatası veya zaman aşımı: kesinti / hata.
- Kontroller GET isteği yapar; gövdenin ilk yaklaşık 1 MB'ından sonrası indirilmez. TLS doğrulaması açıktır.
- Her bağlantıda 5 saniye bağlantı, 15 saniye toplam zaman aşımı; en fazla 5 yönlendirme.
- Yalnızca internete açık HTTP/HTTPS adresleri ve 80/443 portları desteklenir. Yerel/özel IP adresleri engellenir; DNS sonucu bağlantıya sabitlenir. Doğrudan internet çıkışı gerekir; proxy üzerinden özel ağ izleme amaçlanmaz.
- Erişilebilirlik yüzdesi son 30 gündeki başarılı kontrol örneklerinin oranıdır; gerçek kesinti süresi değildir. Kontrol yapılmayan süreler başarılı kabul edilmez. Geçmiş 90 gün saklanır. Son 20 kontrol panelde görünür.
- Bir HTTP yanıtı sitenin tüm işlevlerinin sağlıklı olduğunu kanıtlamaz. Bakım, WAF ve bot engeli de hata sonucu verebilir.

## Toplu ekleme ve varsayılanlar

Panelde **Toplu domain ekle** alanına her satırda bir domain veya URL olacak şekilde 1000 satıra kadar yapıştırabilirsiniz. `example.com`, `www.example.com` ve tam HTTP/HTTPS adresleri kabul edilir. Şemasız adreslere HTTPS eklenir. Site adı otomatik olarak domain üzerinden üretilir; başındaki `www.` kaldırılır. İstek URL'sindeki `www.` korunur çünkü bazı siteler bunu gerektirir. Aynı normalize edilmiş URL tekrar eklenmez; hatalı satırlar raporlanır, geçerli olanlar kaydedilir.

Yeni sitelerde varsayılan kontrol aralığı **1440 dakika (24 saat)**. Önceden eklenmiş sitelerin özel aralıkları değiştirilmez; karttaki aralık alanından düzenlenebilir.

## Otomatik küçük resimler

Küçük resim URL'si girilmez. Cron yeni sitelerin ana sayfa ekran görüntüsünü otomatik alır ve `var/thumbnails/` altında saklar. Başarılı indirmeden **24 saat sonra** yeniden ister; panel resimleri kendi sunucunuzdan, oturum kontrolüyle sunar. Sağlayıcı hatasında eski resim korunur, hata panelde gösterilir ve 1 saat sonra tekrar denenir. Ekran görüntüsü hatası sitenin erişilebilirlik sonucunu değiştirmez.

Standart cPanel'de Chromium kurulumu gerektirmemesi için varsayılan görüntü sağlayıcı **thum.io** (`https://image.thum.io`) kullanılır. Ana sayfa adresleri bu üçüncü tarafa gönderilir; hedef sitenin içerik görüntüsü sağlayıcı tarafından işlenir. Formdaki URL'nin yol ve sorgu parametreleri sağlayıcıya gönderilmez. Sağlayıcının kullanılabilirliği, önbelleği, ücretsiz kullanım/kota koşulları ve bot engelleri görüntüyü etkileyebilir; 100 sitenin günlük yenilenmesi için sağlayıcının güncel plan/kotalarını kontrol edin. Sağlayıcının gerçek görüntü tazeliği onun önbellek politikasına bağlıdır. Sunucunuz `image.thum.io:443` adresine çıkabilmelidir. Özel ağ siteleri desteklenmez.

Farklı bir uyumlu servise geçmek için `MONITOR_SCREENSHOT_ENDPOINT` tanımlanabilir. Bu HTTPS önekine hedef ana sayfa URL'si doğrudan eklenir ve cevap PNG/JPEG/GIF/WebP resmi olmalıdır (JSON değil). Varsayılan:

```text
https://image.thum.io/get/width/600/crop/400/noanimate/
```

Bu değer web sunucusunda değil **cron ortamında** tanımlanmalıdır; özel bir servis tanımlarsanız cron komutuna ortam değişkeni olarak ekleyin. Önek içine API anahtarı koymayın veya GitHub'a göndermeyin.

**Cron zorunludur:** panel açık olması otomatik iş başlatmaz. Dakikalık cron her sitenin 24 saatlik programını ve resim yenilemelerini işler. Aynı anda tek cron çalışır; yaklaşık 50 saniyelik iş bütçesinden sonra kuyruk sonraki cron çağrısında devam eder. Başlamış bir HTTP isteği kendi zaman aşımı dolana kadar bu bütçeyi aşabilir. Büyük toplu eklemelerde ilk sonuç ve resimlerin tamamlanması birkaç cron turu sürebilir.

## Mevcut cPanel kurulumunu güncelleme

1. `var/` dizininin/veritabanının tutarlı bir yedeğini alın.
2. `src/app.php`, `public/index.php`, `public/style.css` ve `bin/check.php` dosyalarını yeni sürümle değiştirin. Mevcut `.htaccess`, parola ayarları ve `var/` dizinini silmeyin.
3. Paneli açın; yeni SQLite alanları otomatik eklenir, kayıtlar korunur.
4. Yukarıdaki dakikalık cron görevini ekleyin. Hem kontroller hem küçük resimler aynı komutla çalışır. `var/cron.log` hata takibi içindir; uzun kullanımda log rotasyonu uygulayın.

## Doğrulama

```sh
php tests/smoke.php
php tests/features.php
find src public bin tests -name '*.php' -exec php -l {} \;
```

Smoke testi geçici SQLite veritabanıyla kayıt, kontrol, zamanlama, kilit, geçmiş ve silmeyi sınar. Dış servis gerekmez.

`tests/features.php` mevcut veritabanından yükseltmeyi ve 100 domainlik toplu eklemeyi sınar. Ekran görüntüsü indirme testleri için `MONITOR_SCREENSHOT_ENDPOINT` ile kontrollü bir HTTPS resim fixture'ı gerekir; normal çalışmada bu ağ testleri **UNRUN** olarak bildirilir. Fixture `invalid/` yolunda resim dışı bir yanıt döndürmelidir. Gerçek üçüncü taraf servis erişimi ayrıca sunucunuzda doğrulanmalıdır.
