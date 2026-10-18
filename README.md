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

## Küçük resimler

Site eklerken isteğe bağlı küçük resim URL'si girebilirsiniz: kendi yüklediğiniz ekran görüntüsü veya güvendiğiniz bir ekran görüntüsü sağlayıcısının doğrudan resim URL'si. Görsel tarayıcıdan yüklenir; sağlayıcı ziyaretçinin IP adresini görür. **Otomatik ekran görüntüsü üretimi bu sürümde yoktur**; temel kurulumda harici servis veya tarayıcı bağımlılığı bulunmaz. Görsel eklenmezse yer tutucu gösterilir. Gizli anahtar içeren sağlayıcı URL'lerini kullanmayın.

## Doğrulama

```sh
php tests/smoke.php
find src public bin tests -name '*.php' -exec php -l {} \;
```

Smoke testi geçici SQLite veritabanıyla kayıt, kontrol, zamanlama, kilit, geçmiş ve silmeyi sınar. Dış servis gerekmez.
