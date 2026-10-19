# WebKontrol

Framework veya Composer gerektirmeyen, PDO SQLite tabanlı site erişilebilirlik paneli. PHP 8.1+ ve `pdo_sqlite`, `curl` eklentileri gerekir. Linux, macOS veya Windows üzerinde uygun PHP kurulumu ile çalışır.

## Ücretsiz gerçek Chromium kontrolü — önerilen mod

cPanel sunucusunun kendi dış IP'sine erişimi engelliyse **GitHub Actions** üzerindeki Chromium tarayıcısıyla sitelerinizi açabilirsiniz. Kontrol ve ekran görüntüsü aynı tarayıcı oturumunda yapılır; cPanel yalnızca panel/API/SQLite için kullanılır. Bu modda thum.io veya ücretli tarayıcı servisi kullanılmaz.

**Ücretsiz kullanım koşulu:** public GitHub deposunda standart GitHub-hosted runner kullanın. Private depo Actions kotasını tüketir ve ücret doğurabilir; ücretli veya büyük runner seçmeyin. GitHub'ın güncel kullanım koşulları ve limitleri geçerlidir. Workflow'da yalnızca `ubuntu-latest` standart runner kullanılır. Tarayıcı bağımlılıkları için Composer gerekmez; cPanel'e Node veya Chromium kurulmaz.

### Mevcut cPanel kurulumunda etkinleştirme

1. Güncel `src/app.php`, `src/browser.php`, `public/index.php`, `public/style.css`, `public/browser-api.php`, `bin/check.php` dosyalarını sunucuya yükleyin. Proje kökündeki yeni `.htaccess` kurallarını da yükleyin; mevcut kök `.htaccess` dosyanız varsa kuralları birleştirin, kullanıcı ayarlarını silmeyin. **`public/.htaccess` ve parolanızı, `var/` dizinini silmeyin.** Tarayıcı modu açılırken veri dizinine `Require all denied` koruması eklenir; mevcut koruma dosyası değiştirilmez. Tercihen alan adının document root'unu doğrudan `public/` yapın. `.git`, kaynak ve veri dizinlerini internetten erişilebilir bırakmayın.
2. Panelde **Ücretsiz gerçek tarayıcı kontrolü → Tarayıcı modunu etkinleştir** seçin. Benzersiz bir `MONITOR_API_TOKEN` üretilir ve mevcut siteler kontrol kuyruğuna alınır. Anahtar panel parolasından ayrıdır; sunucuda `var/browser-token` dosyasında tutulur. Bu dosyayı Git'e eklemeyin veya paylaşmayın.
3. GitHub → `gok-turk/webkontrol` → **Settings → Secrets and variables → Actions → Secrets → New repository secret**: ad `MONITOR_API_TOKEN`, değer panelde gösterilen anahtar. Anahtarı sohbet veya kaynak koduna yazmayın.
4. Aynı bölümde **Variables → New repository variable**: ad `MONITOR_PANEL_URL`, değer `https://kontrol.mg.web.tr/webkontrol/public/`. Workflow bu adresi varsayılan olarak da kullanır; farklı kurulumda tam panel adresini ve sondaki `/` işaretini belirtin. HTTPS zorunludur.
5. GitHub → **Actions → Browser monitoring → Run workflow → main → Run workflow**. Actions kapalıysa repository ayarlarından etkinleştirin. Sonuç geldikçe paneli yenileyin; kartta **Kontrol kaynağı: GitHub Chromium** görünür. Önceki sunucu kontrolleri geçmişten silinmez ve yeni sonuç gelene kadar son durum olarak kalabilir.

### Zamanlama ve manuel kontroller

Workflow yaklaşık **15 dakikada bir** çalışır ve sadece zamanı gelmiş/kuyruğa alınmış siteleri kontrol eder. Siteye özel varsayılan aralık 24 saattir; hatalar 5 dakika sonra yeniden uygun hale gelir fakat bir sonraki workflow çalışmasında işlenir. GitHub zamanlaması gecikebilir, dakik çalışmayı garanti etmez. **Şimdi kontrol et / Tümünü kontrol et / Kesinti olanları kontrol et** düğmeleri kuyruğa ekler. Hemen başlatmak için GitHub'daki **Run workflow** düğmesini kullanın. Paneldeki düğmelerin GitHub workflow'unu doğrudan tetiklemesi için ek GitHub yazma anahtarı gerekmemesi amaçlanmıştır.

Bir çalışmada üç tarayıcı sekmesi paralel, toplam yaklaşık 8 dakikalık kontrol bütçesi kullanılır. Büyük listeler sonraki workflow turunda devam edebilir. İşler 10 dakika süreli nonce/kilitle atanır; kesilen işler sonra yeniden alınabilir. Aynı workflow grubundan birden fazla çalışma paralel yürütülmez. GitHub public depolarda uzun süre repository etkinliği olmazsa zamanlanmış workflow'ları otomatik kapatabilir (genellikle 60 gün); Actions ekranından kontrol edip yeniden açın.

Bu modda mevcut cPanel cron'u sessizce çıkar; yerel cURL sonuçları tarayıcı sonuçlarını ezmez. Cron'u kaldırmak zorunlu değildir. Sunucudan kontrol moduna dönerseniz eski cron yeniden kullanılacaktır. API anahtarını yenilemek gerekirse tarayıcı modunu kapatın, `var/browser-token` dosyasını Dosya Yöneticisi'nden silin, modu tekrar açın ve GitHub secret'ını yeni anahtarla güncelleyin.

### Başarı kriteri ve resimler

Chromium HTTPS sertifikasını doğrulayarak sayfayı açar; Ana belgenin yanıtı alındıktan sonra görüntülenebilir içerik ve son ana belge HTTP 200–399 sonucu aranır; DOMContentLoaded olayının bütün scriptleri beklemesi başarı şartı değildir. Taşıma veya boş sayfa hatası bir kez yeni oturumda tekrar denenir. HTTP 4xx/5xx sonuçları başarılı sayılmaz. WAF, giriş duvarı veya bot engeli tarayıcı kontrolünü de engelleyebilir. Sayfanın açılması ödeme, form ve diğer tüm işlevlerin çalıştığını kanıtlamaz.

İlk tarayıcı kontrolünde önceki thum.io resminin yerine yeni JPEG alınır; sonra resimler en fazla 24 saatte bir yenilenir. Daha kısa aralıklı durum kontrollerinde resim tekrar alınmayabilir. Resim hatası erişilebilirlik sonucunu değiştirmez; sonraki kontrol sırasında tekrar denenir. Ziyaretler GitHub IP'sinden yapılır; kayıtlı domainler ve sayfa içeriği GitHub üzerinde işlenir. Resimler ve sonuçlar cPanel'e geri gönderilir; public Actions artifact'ı olarak yayımlanmaz.

### API ve tarayıcı güvenliği

`public/browser-api.php` sadece anahtarlı POST isteklerini kabul eder; iş nonce'ı ve süresi denetlenir. Eski iş sonuçları reddedilir; aynı işin başarıyla kaydedilmiş sonucu tekrar gelirse yeni kayıt oluşturmadan onaylanır. Anahtar yalnızca panel API'sine gönderilir; ziyaret edilen sitelere veya Chromium işlem ortamına aktarılmaz. Runner'daki HTTP/HTTPS proxy her bağlantıda DNS'i çözümleyip genel IP'ye sabitler; özel/yerel ağlar, metadata adresleri ve 80/443 dışındaki portlar engellenir. Chromium bağlamları siteler arasında ayrıdır; TLS doğrulaması kapatılmaz.

### Testler

```sh
php tests/browser.php
cd browser
npm ci --ignore-scripts
npx playwright install --with-deps chromium
npm test
```

Tarayıcı entegrasyon testi fixture için yerel 80 portunu kullanır; bu port boş ve test kullanıcısı için kullanılabilir olmalıdır. Sistem Chromium'u olan ortamda `CHROMIUM_PATH=/usr/bin/chromium npm test` kullanılabilir. Gerçek cPanel ve GitHub bağlantısı, yukarıdaki secret yapılandırıldıktan sonra ilk **Run workflow** ile ayrıca doğrulanmalıdır.

## Başlatma

```sh
export MONITOR_PASSWORD='uzun-ve-benzersiz-bir-parola'
php -S 127.0.0.1:8080 -t public
```

Tarayıcıda 8080 portunu açın ve belirlediğiniz parola ile giriş yapın. Parolayı kaynak koduna eklemeyin. Windows PowerShell'de değişkeni `$env:MONITOR_PASSWORD='...'` ile tanımlayabilirsiniz.

Üretimde PHP destekli HTTPS sunucusunun belge kökünü **yalnızca `public/`** dizinine ayarlayın. PHP'nin yerleşik sunucusu yerel geliştirme içindir. `src/`, `bin/` ve SQLite dosyalarını internete açmayın. Veri dizini varsayılan olarak `var/`; PHP işlemi burada yazabilmelidir. İsterseniz `MONITOR_DATA_DIR` ortam değişkeniyle web kökü dışında mutlak bir dizin belirleyin. SQLite dosyasını ve varsa WAL/SHM dosyalarını tutarlı bir SQLite yedeği ile yedekleyin.

## cPanel kontrol modunda cron

Linux cron örneği (PHP ve proje yollarını uyarlayın):

```cron
* * * * * /usr/bin/php /tam/yol/webkontrol/bin/check.php >> /tam/yol/webkontrol/var/cron.log 2>&1
```

Windows Görev Zamanlayıcı'da `php.exe` ile `bin/check.php` dosyasını dakikada bir çalıştırabilirsiniz. Sürekli açık panel gerekmez. Her sitenin aralığı ayrı tutulur (1–1440 dakika); cron dakikalık çalıştığından kontroller yaklaşık zamanlıdır. Çok sayıda yavaş site seri işleme nedeniyle gecikebilir. CLI ile web işlemi aynı `MONITOR_DATA_DIR` dizinini kullanmalıdır. Paneldeki “Şimdi kontrol et” tek siteyi hemen kontrol eder. Çakışan kontroller SQLite üzerinde süreli kilitle engellenir.

## Sonuçların anlamı

- HTTP 200–399: erişilebilir; 400–599, DNS/TLS/bağlantı hatası veya zaman aşımı: kesinti / hata.
- Kontroller GET isteği yapar; gövdenin ilk yaklaşık 1 MB'ından sonrası indirilmez. TLS doğrulaması açıktır.
- Her IP denemesinde 5 saniye bağlantı, 15 saniye istek zaman aşımı; toplam HTTP denemeleri için 75 saniyelik bütçe; en fazla 5 yönlendirme.
- Yalnızca internete açık HTTP/HTTPS adresleri ve 80/443 portları desteklenir. Yerel/özel IP adresleri engellenir; DNS sonucu bağlantıya sabitlenir. Doğrudan internet çıkışı gerekir; proxy üzerinden özel ağ izleme amaçlanmaz.
- Erişilebilirlik yüzdesi son 30 gündeki başarılı kontrol örneklerinin oranıdır; gerçek kesinti süresi değildir. Kontrol yapılmayan süreler başarılı kabul edilmez. Geçmiş 90 gün saklanır. Son 20 kontrol panelde görünür.
- Bir HTTP yanıtı sitenin tüm işlevlerinin sağlıklı olduğunu kanıtlamaz. Bakım, WAF ve bot engeli de hata sonucu verebilir.

## Toplu ekleme ve varsayılanlar

Panelde **Toplu domain ekle** alanına her satırda bir domain veya URL olacak şekilde 1000 satıra kadar yapıştırabilirsiniz. `example.com`, `www.example.com` ve tam HTTP/HTTPS adresleri kabul edilir. Şemasız adreslere HTTPS eklenir. Site adı otomatik olarak domain üzerinden üretilir; başındaki `www.` kaldırılır. İstek URL'sindeki `www.` korunur çünkü bazı siteler bunu gerektirir. Aynı normalize edilmiş URL tekrar eklenmez; hatalı satırlar raporlanır, geçerli olanlar kaydedilir.

Yeni sitelerde varsayılan kontrol aralığı **1440 dakika (24 saat)**. Önceden eklenmiş sitelerin özel aralıkları değiştirilmez; karttaki aralık alanından düzenlenebilir.

## cPanel kontrol modunda küçük resimler

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

## Toplu ekleme hatalarını giderme

Eski cPanel SQLite sürümleriyle uyumluluk için toplu ekleme `INSERT OR IGNORE` kullanır; SQLite 3.24+ gerektiren UPSERT sözdizimine ihtiyaç duymaz. Tekrar URL'ler atlanır. Bir işlem başarısız olursa form metni korunur ve veritabanı hatası yönetici panelinde gösterilir. Yazma izni hatasında `var/` dizininin, SQLite dosyasının ve varsa WAL/SHM dosyalarının PHP kullanıcısına ait olduğunu kontrol edin; 777 izni vermeyin. Cron ve web PHP işlemlerinin aynı kullanıcı ve veri diziniyle çalışması gerekir.

## Geçici DNS ve bağlantı hataları

A ve AAAA kayıtları ayrı sorgulanır; bir kayıt türünün başarısız olması diğerini geçersiz kılmaz. Doğrudan sorgular sonuç vermezse sistemin IPv4 çözümleyicisi denenir. Bulunan bütün IP'ler özel ağ kontrolünden geçer. IPv4 adresleri önce denenir; bağlantı/TLS/taşıma hatasında en fazla dört farklı IP denenir. Geçerli bir HTTP yanıtı alındığında diğer IP'lerle bu yanıt gizlenmez; HTTP 4xx/5xx hataları hata olarak kalır. TLS doğrulaması korunur; HTTPS başarısızlığında HTTP'ye otomatik düşürülmez.

Kesinti/hata durumunda sonraki kontrol normal aralık veya 5 dakikadan kısa olanıdır. Başarılı sonuçtan sonra normal aralığa dönülür. Bu, geçici hataların 24 saat panelde kalmasını önler; cron çalışmalıdır. DNS sorgularının süresi işletim sistemi çözümleyicisine bağlıdır. Sunucunun DNS/IPv6/443 çıkış sorunları veya hedef güvenlik duvarı engelleri ayrıca hosting sağlayıcısı tarafından incelenmelidir.

## Toplu yeniden kontrol

Aşağıdaki cron açıklaması cPanel kontrol modu içindir; tarayıcı modunda aynı kuyruğu GitHub Actions işler.

Panelin üstündeki **Tümünü kontrol et** tüm kayıtları; **Kesinti olanları kontrol et** yalnızca son durumu `down` olanları, seçili liste filtresinden bağımsız kontrol kuyruğuna alır. Bu düğmeler HTTP isteğinde yüzlerce siteyi kontrol etmez. Dakikalık cron kuyruğu küçük resimlerden önce işler; bekleyen kayıt sayısı panelde görünür. Sonuçları **Sonuçları yenile** bağlantısıyla takip edin. Var olan işlem kilitleri korunur; toplu kontrol sitelerin normal kontrol aralığını değiştirmez.

## Bağımsız DNS yedeği ve bağlantı tanısı

Yerel DNS ve sistem çözümleyicisi sonuç vermezse TLS doğrulamalı DNS-over-HTTPS sorgusu yapılır: önce `cloudflare-dns.com`, sonra `dns.google`. Yalnızca domain adı ve kayıt türü gönderilir. Yerel DNS IP'sine taşıma/bağlantı/TLS hatası alınırsa bağımsız DNS sonucu ile yeni IP'ler de denenir. HTTPS DNS toplam yaklaşık 10 saniye ile sınırlıdır; A/AAAA yanıtları aynı özel ağ/IP kontrollerinden geçer. Sabit sağlayıcı IP'leri DNS arızası sırasında sağlayıcıya erişimi destekler. Sunucunun bu sağlayıcılara HTTPS çıkışı açık olmalıdır. HTTP 4xx/5xx yanıtları, TLS sorunları ve güvenlik duvarı engelleri resim var diye başarılı kabul edilmez.

Ekran görüntüsü thum.io sunucusundan, erişilebilirlik kontrolü sizin PHP sunucunuzdan yapılır. Bu yüzden ekran görüntüsü mevcutken doğrudan kontrol başarısız olabilir. Ayrıca resim önceki bir zamanda alınmış olabilir. Script kendi sunucunuzun siteye erişimini ölçer; tarayıcınızın veya tüm internetin erişimini değil. HTTPS adresi eklenen bir sitede yalnızca HTTP çalışması da kesinti/hata sonucu verir; HTTPS başarısızlığında güvenliği düşürerek HTTP'ye geçilmez.

cPanel Terminal veya SSH üzerinden sorunlu adres için tanı komutu:

```sh
/usr/local/bin/php /home/KULLANICI/kontrol.mg.web.tr/webkontrol/bin/diagnose.php https://ornek.com
```

Bu salt okunur komut PHP/cURL/TLS sürümlerini, yerel/yedek ve bağımsız DNS sonuçlarını ve gerçek bağlantı hatasını gösterir; veritabanını değiştirmez. Terminal yoksa aynı komutu geçici bir cron göreviyle `>> /home/KULLANICI/kontrol.mg.web.tr/webkontrol/var/diagnose.log 2>&1` yönlendirmesiyle çalıştırıp ardından bu tanı görevini kaldırabilirsiniz. Çalışma sunucusunda hata sürüyorsa bu çıktı, hosting sağlayıcısının DNS, güvenlik duvarı, IPv6 veya kendi sunucusuna dış IP üzerinden erişim (hairpin) sorununu ayırmasına yardımcı olur. PHP'nin web ve cron sürümleri farklı olabilir.

Bu güncellemede sunucuya `src/app.php`, `public/index.php`, `public/style.css`, `bin/check.php` ve `bin/diagnose.php` dosyalarını yükleyin. Mevcut `var/` ve `.htaccess` korunmalıdır. Kayıtlı cron komutu aynı kalır.

## GitHub → panel bağlantı zaman aşımı

`UND_ERR_CONNECT_TIMEOUT` veya sonuç gönderiminde `fetch failed` hedef sitenin kapalı olduğunu göstermez; GitHub runner'ı panel API'sine ulaşamamıştır. Güncel worker IPv4 ve TLS doğrulamalı HTTPS bağlantısı kullanır, TLS bağlantısını yeniden kullanır ve panel isteklerini saniyede en fazla bir tane olacak şekilde seri gönderir. Geçici ağ hataları ve HTTP 429/500/502/503/504 yanıtları en fazla dört kez denenir; kimlik doğrulama ve sertifika hataları bu şekilde gizlenmez.

Kaydedilmiş bir sonucun yanıtı bağlantıda kaybolursa aynı nonce ile tekrar gönderim geçmişte ikinci kayıt oluşturmaz. Bir yükleme başarısız olduğunda diğer atanmış işler tamamlanmaya çalışılır; panel sürekli erişilemezse yeni iş alınmaz. Kaydedilemeyen sonuçlar siteyi otomatik olarak kapalı yapmaz; önceki durum korunur ve 10 dakikalık iş kilidi dolunca sonraki workflow işi yeniden alır. Çalışma özetinde kaydedilen ve tamamlanamayan sonuç sayıları görünür. Kalıcı hosting firewall/IP veya bağlantı limiti engelini tekrar denemeler aşamaz; bu durumda hosting panel alan adının 443 portuna GitHub runner erişimini incelemelidir.

Bu düzeltmeyi uygulamak için cPanel'de **`src/app.php` ve `src/browser.php`** dosyalarını yenileyin. Anahtar ve mevcut ayarlar değişmez. GitHub'da **Actions → Browser monitoring → Run workflow → main** ile yeni çalışma başlatın; eski çalışmadaki **Re-run jobs** eski commit'i kullanabileceği için güncel kodu test etmez.

## Workflow başarılı ama hata filtresindeki kartlar eski görünüyorsa

Bir çalışma bütün listeyi bitirmek zorunda değildir: `Completed browser checks: 78` yalnızca o turda 78 sonucun kaydedildiğini gösterir. Worker süre bütçesinden sonra kalan kayıtlar sonraki tura kalır. Ayrıca panel **Kesinti / hata** filtresindeyken başarılı duruma geçen siteler listeden çıkar; eski hatalı kartların görünmeye devam etmesi tek başına kaydın çalışmadığını göstermez. **İzlenen site** kartıyla tüm sonuçları görebilirsiniz.

Panelde **Tarayıcı kontrolü: X / toplam** sayacı hangi sitelere gerçekten Chromium sonucu geldiğini gösterir. Henüz tarayıcı sonucu gelmeyen cPanel kayıtları **Tarayıcı sonucu bekleniyor** diye işaretlenir; eski hata ayrıntısı kapalı bir açıklamada korunur. Özet durum sayıları son kayıtları içerir ve yeni sonuç geldikçe değişir. Kuyruk manuel bekleyen işleri, aralarında eski hatalı kayıtları ve paneldeki en yeni siteleri önceliklendirir. İlerlemeyi görmek için sayfayı yenileyin; **Tümünü kontrol et** düğmesine her yenilemede yeniden basmayın.

Bu görünüm ve öncelik güncellemesi için `src/browser.php`, `public/index.php`, `public/style.css` dosyalarını cPanel'de yenileyin. `var/` ve anahtar korunur.

## Sayfa görünürken DOMContentLoaded zaman aşımı

Bazı sitelerde engellenen/geciken bir script, içerik ekranda görünse bile `DOMContentLoaded` olayını geciktirir. Güncel Chromium kontrolü önce ana belgenin HTTP yanıtını (`commit`, 20 saniye) bekler, ardından en fazla 20 saniye içinde görünür metin veya görsel içerik arar. Görünür sayfayı yalnızca tamamlanmayan script yüzünden kesinti olarak işaretlemez. Sayfa boş kalırsa veya son ana belge 4xx/5xx verirse hata sonucu korunur. JavaScript yönlendirmesinden sonraki ana belge yanıtı da izlenir. Ekran görüntüsü hatası ayrı kaydedilir.

Proxy tüneli kurulamadığında DNS (ENOTFOUND/EAI_AGAIN), bağlantı reddi veya TCP zaman aşımı bilgisi varsa sonuç hata metnine eklenir. Bu değişiklikler sadece GitHub tarafındadır; cPanel dosyası yüklemeden güncel `main` ile yeni **Run workflow** başlatın.
