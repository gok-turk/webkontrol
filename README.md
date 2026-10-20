# WebKontrol

**Kendi sitelerinizi tek panelden takip etmek için PHP ve PDO SQLite tabanlı site erişilebilirlik uygulaması.**

WebKontrol'e domainlerinizi ekleyebilir, kontrol aralıklarını belirleyebilir, son durumlarını ve ekran görüntülerini görebilirsiniz. Tek tek veya toplu site ekleme, durum filtreleri, liste/ikili/üçlü grid görünümü ve kontrol geçmişi bulunur.

Önerilen kurulumda siteler **GitHub Actions üzerindeki gerçek Chromium tarayıcısıyla** açılır. PHP paneli kendi hostinginizde çalışır; sonuçlar ve ekran görüntüleri SQLite veritabanına kaydedilir. cPanel sunucunuza Node.js veya Chromium kurmanız gerekmez.

- Kaynak kod: [github.com/gok-turk/webkontrol](https://github.com/gok-turk/webkontrol)
- Sorun ve öneriler: [GitHub Issues](https://github.com/gok-turk/webkontrol/issues)

> Bu uygulama periyodik erişilebilirlik kontrolü yapar. Kesintisiz, saniyelik veya SLA garantili bir izleme hizmeti değildir. Bir sayfanın açılması, ödeme ve form gibi bütün işlevlerin sağlıklı olduğunu kanıtlamaz.

## İçindekiler

1. [Özellikler](#özellikler)
2. [Çalışma modları ve ücretsiz kullanım](#çalışma-modları-ve-ücretsiz-kullanım)
3. [Gereksinimler](#gereksinimler)
4. [Kurulum: dosyalar, alan adı ve parola](#kurulum-dosyalar-alan-adı-ve-parola)
5. [GitHub üzerinde ücretsiz tarayıcı kontrolü](#github-üzerinde-ücretsiz-tarayıcı-kontrolü)
6. [Panel kullanımı](#panel-kullanımı)
7. [Zamanlama ve sonuçların anlamı](#zamanlama-ve-sonuçların-anlamı)
8. [Alternatif: cPanel cron ile kontrol](#alternatif-cpanel-cron-ile-kontrol)
9. [Ayarlar](#ayarlar)
10. [Sık karşılaşılan sorunlar](#sık-karşılaşılan-sorunlar)
11. [Güvenlik, yedekleme ve güncelleme](#güvenlik-yedekleme-ve-güncelleme)
12. [Geliştirme ve testler](#geliştirme-ve-testler)
13. [Mevcut olmayan özellikler ve lisans](#mevcut-olmayan-özellikler-ve-lisans)

## Özellikler

- Framework, Composer ve MySQL gerektirmeyen PHP paneli.
- PDO SQLite ile otomatik veritabanı oluşturma.
- Parolalı yönetici girişi ve formlarda CSRF koruması.
- Domain veya tam URL ile site ekleme.
- **Bir seferde 1000 satıra kadar toplu ekleme.**
- Site adını domainden otomatik üretme; isimdeki `www.` önekini kaldırma.
- Tekrar URL'leri atlama ve hatalı satırları raporlama.
- Site başına **1–1440 dakika** kontrol aralığı; yeni kayıtlarda varsayılan **24 saat**.
- Site bazında kontrol, tümünü veya yalnızca hatalı kayıtları yeniden kontrol kuyruğuna alma.
- Otomatik kontrolün yanlış hata verdiği siteler için 24 saatlik, açıkça etiketlenen manuel doğrulama.
- Erişilebilir / kesinti-hata / tüm siteler filtreleri.
- Liste, ikili grid ve üçlü grid görünümü; dar ekranlarda otomatik sütun azaltma.
- Görünüm ve filtre seçimini oturum boyunca hatırlama.
- HTTP durumu, kontrol süresi, hata açıklaması ve son kontrol zamanı.
- Son 30 günlük kontrol örneklerine göre erişilebilirlik yüzdesi.
- 90 günlük geçmiş saklama; kartlarda son 20 kontrolü gösterme.
- Otomatik küçük resimler ve son güncellenme zamanı.
- Gerçek Chromium sonuçlarını eski sunucu kontrollerinden ayıran kaynak etiketi.
- İş kuyruğu, süreli kilitler ve tekrar sonuçlarda çift kayıt oluşmasını önleme.

## Çalışma modları ve ücretsiz kullanım

| | GitHub Chromium — önerilen | PHP/cURL — alternatif |
|---|---|---|
| Kontrol nereden yapılır? | GitHub'ın standart runner'ından | Panelin kurulu olduğu sunucudan |
| Gerçek tarayıcı kullanır mı? | Evet, Playwright + Chromium | Hayır, HTTP GET isteği |
| Zamanlayıcı | GitHub Actions | cPanel cron / işletim sistemi görevi |
| Ekran görüntüsü | Aynı tarayıcı oturumundan | Harici resim sağlayıcısından, varsayılan thum.io |
| Hostingde Node.js / Chromium | Gerekmez | Gerekmez |
| Hostingin kendine bağlantı engelinden etkilenir mi? | Hedef kontrolleri farklı sunucudan yapılır | Evet, yanlış kesinti izlenimi oluşabilir |

### Gerçek tarayıcı modu nasıl çalışır?

```text
PHP paneli / SQLite → GitHub Chromium → sitelerinizi açar
         ↑                    ↓
         └── sonuçlar ve ekran görüntüleri ──┘
```

Runner panel API'sinden zamanı gelen işleri alır, siteleri Chromium ile açar ve sonuçları panelinize geri gönderir. Her site ayrı tarayıcı bağlamında kontrol edilir. Panel açık kalmak zorunda değildir; hostingin ve panel API'sinin erişilebilir olması gerekir.

### Gerçekten ücretsiz mi?

**Public GitHub deposunda standart GitHub-hosted runner kullanımı**, GitHub'ın güncel koşulları ve kullanım politikaları kapsamında ücretsizdir. Projedeki workflow standart `ubuntu-latest` runner kullanır.

- Private depolarda hesabın Actions kotası kullanılır; aşım ücret doğurabilir.
- Ücretli veya büyük runner seçmeyin.
- Hosting ve alan adı bu projeye dahil değildir.
- GitHub'ın fiyatlandırma ve kullanım koşulları değişebilir; [güncel koşulları kontrol edin](https://docs.github.com/en/billing/concepts/product-billing/github-actions).
- Ücretsiz kullanım, zamanlamanın veya erişilebilirliğin garanti edildiği anlamına gelmez.

## Gereksinimler

### PHP paneli için

- PHP **8.1 veya üzeri**; yeni kurulumda güncel, desteklenen PHP sürümü önerilir.
- `PDO`, **`pdo_sqlite`**, oturum ve JSON desteği.
- PHP kullanıcısının veri dizinine yazabilmesi.
- Geçerli sertifikayla erişilebilen **HTTPS panel adresi**.
- GitHub runner'larının panelinizin 443 portuna ve API dosyasına ulaşabilmesi.
- Apache/LiteSpeed üzerinde `.htaccess` desteği veya diğer sunucularda eşdeğer erişim kuralları.

MySQL veritabanı, Composer, root erişimi veya cPanel Terminal zorunlu değildir. SQLite dosyası ilk kullanımda oluşturulur.

PHP/cURL alternatifini kullanacaksanız ayrıca **PHP `curl` eklentisi**, dış ağ/DNS erişimi ve cron gerekir. Gerçek tarayıcı modunda hedefleri açan işlem hostingdeki PHP değil, GitHub'daki Chromium'dur.

### GitHub tarayıcı modu için

- GitHub hesabı ve kendi deponuzda Actions kullanma yetkisi.
- Projenin workflow dosyalarını içeren bir fork veya kopya depo.
- Ücretsiz standart runner kullanımı için public depo.
- Bir repository secret ve bir repository variable; aşağıda açıklanmıştır.

## Kurulum: dosyalar, alan adı ve parola

Aşağıdaki örneklerde `KULLANICI` cPanel kullanıcı adınızı, `kontrol.example.com` ise kendi panel alan adınızı temsil eder. Bunları aynen bırakmayın.

### 1. Projeyi kendi GitHub hesabınıza alın

[Proje sayfasında](https://github.com/gok-turk/webkontrol) **Fork** seçeneğini kullanabilirsiniz. ZIP ile indirecekseniz **Code → Download ZIP** yolunu izleyin.

GitHub Actions kullanabilmek için kendi GitHub deponuzda `.github/workflows/browser-monitor.yml`, `browser/` ve kilit dosyası da bulunmalıdır. Sadece PHP dosyalarını yeni bir depoya yüklemek tarayıcı görevini oluşturmaz. Fork sonrası Actions'ın ayrıca etkinleştirilmesi gerekebilir.

**GitHub deponuza `var/`, veritabanı, parola veya API anahtarı yüklemeyin.** Projede `.gitignore` bulunur; ancak web arayüzünden elle yüklerken de bu dosyaları seçmeyin.

### 2. Dosyaları hostinge yükleyin

cPanel → **Dosya Yöneticisi** üzerinden ZIP'i yükleyip açın. Önerilen yerleşim:

```text
/home/KULLANICI/webkontrol/
├── .htaccess                  # Proje dizinleri için Apache/LiteSpeed koruması
├── .github/workflows/         # GitHub'da bulunmalı
├── browser/                   # GitHub runner'ında çalışan tarayıcı kodu
├── bin/                       # Alternatif cron ve tanı komutları
├── public/                    # İnternete açık olması gereken tek dizin
│   ├── index.php
│   ├── browser-api.php
│   └── style.css
├── src/                       # PHP uygulama kodu
├── tests/                     # Geliştirici testleri
└── var/                       # İlk kullanımda oluşan, özel veri dizini
```

`browser/`, `.github/` ve testlerin PHP hostingde çalıştırılması gerekmez; tarayıcı modu için bunlar GitHub deponuzda kullanılır. Hostinge `public/`, `src/` ve alternatif kontrolü de kullanacaksanız `bin/` yüklenmelidir. Yalnızca `public/` yükleyip diğer PHP dosyalarını dışarıda bırakmayın.

### 3. Alan adını `public/` dizinine bağlayın

cPanel → **Alan Adları / Domains → Yönet / Manage** bölümünde belge kökünü mümkünse şöyle ayarlayın:

```text
/home/KULLANICI/webkontrol/public
```

Böylece panel:

```text
https://kontrol.example.com/
```

adresinden açılır. **SSL/TLS Status → AutoSSL** bölümünden HTTPS'i etkinleştirin.

Belge kökü değiştirilemiyorsa aşağıdaki gibi alt dizin kurulumu da kullanılabilir:

```text
https://kontrol.example.com/webkontrol/public/
```

Bu durumda projenin `.htaccess` korumasını koruyun ve `var/`, `src/`, `.git` gibi özel dizinlerin web üzerinden erişilemediğinden emin olun. Nginx `.htaccess` okumaz; korumayı sunucu yapılandırmasında kurmanız gerekir. Sadece `Options -Indexes` kullanmak özel dosyaların doğrudan indirilmesini engellemez.

**Temiz ana adres için tercih edilen yöntem belge kökünü değiştirmektir.** Hostinglere göre değişebilen genel yönlendirme kurallarını denemek yerine, kısıtlı hesaplarda sağlayıcınızdan belge kökü desteği isteyin veya çalışan alt dizin adresini kullanın.

### 4. PHP eklentilerini açın

cPanel → **Select PHP Version** veya **MultiPHP Manager** üzerinden uygun PHP sürümünü seçin. `PDO` ve `pdo_sqlite` etkin olmalıdır. Alternatif sunucu kontrolü için `curl` de etkin olmalıdır.

`could not find driver` hatası, çoğunlukla PHP'nin `pdo_sqlite` eklentisinin eksik olduğunu gösterir.

### 5. Panel parolasını belirleyin

Uygulama yönetici parolasını **`MONITOR_PASSWORD` ortam değişkeninden** okur. Bu değer sizin belirlediğiniz güçlü, benzersiz bir parola olmalıdır; kodda varsayılan yönetici parolası yoktur.

Apache/LiteSpeed ortamında hosting destekliyorsa `public/` içine `.htaccess` oluşturup şu satırı ekleyebilirsiniz:

```apache
SetEnv MONITOR_PASSWORD "BURAYA_KENDI_GUCLU_PAROLANIZI_YAZIN"
```

- Örnek metni kendi parolanızla değiştirin; bu örnekte boşluk veya çift tırnak kullanmayın.
- Mevcut `public/.htaccess` dosyanız varsa diğer PHP/hosting ayarlarını silmeyin.
- Parola içeren bu yerel dosyayı public GitHub deponuza göndermeyin.
- `SetEnv` desteği hostingin PHP çalıştırma şekline bağlıdır. PHP-FPM/FastCGI yapılandırmasında değişken PHP'ye aktarılmayabilir. Panel değişkeni göremiyorsa sağlayıcınızdan PHP sürecine ortam değişkeni tanımlamasını isteyin. Sıradan bir `.env` dosyası oluşturmak yeterli değildir; uygulama `.env` dosyası okumaz.

Uygulamaya kayıt olma veya e-posta ile parola sıfırlama akışı yoktur; parolayı bu sunucu ayarı üzerinden yönetirsiniz.

### 6. Veri dizini izinlerini kontrol edin

Varsayılan veriler proje kökündeki `var/` altında tutulur. Dizin otomatik oluşturulamazsa Dosya Yöneticisi'nden oluşturun. PHP kullanıcısının bu dizinde dosya oluşturma/değiştirme yetkisi olmalıdır.

PHP hesabınızın kullanıcısıyla çalışıyorsa `700` dizin izni genellikle uygundur. Dosya sahipliği ve hosting yapılandırması önemlidir; hatayı çözmek için körlemesine `777` vermeyin. SQLite WAL/SHM dosyaları için yalnızca veritabanı dosyasının değil, **dizinin de** yazılabilir olması gerekir.

Dilerseniz `MONITOR_DATA_DIR` ile web kökü dışında mutlak bir veri dizini belirleyebilirsiniz. Web işlemi ve varsa CLI/cron aynı veri dizinini kullanmalıdır.

### 7. İlk giriş

Panel adresinizi açın ve `MONITOR_PASSWORD` için belirlediğiniz parolayla giriş yapın. Veritabanı otomatik oluşturulur; SQL dosyası içe aktarmayın.

Bu aşamada henüz otomatik kontrol çalışmaz. Önerilen GitHub tarayıcı kurulumunu aşağıdaki adımlarla tamamlayın.

## GitHub üzerinde ücretsiz tarayıcı kontrolü

### 1. Kendi panel adresinizi GitHub'a kaydedin

**Kendi deponuzda** şu bölümü açın:

**Settings → Secrets and variables → Actions → Variables → New repository variable**

| Alan | Değer |
|---|---|
| Name | `MONITOR_PANEL_URL` |
| Value | Kendi panelinizin tam HTTPS adresi; sonunda `/` olmalı |

Ana adresten kurulum örneği:

```text
https://kontrol.example.com/
```

Alt dizin kurulum örneği:

```text
https://kontrol.example.com/webkontrol/public/
```

Bu adres, `index.php`, `style.css` ve **`browser-api.php` dosyasının aynı taban adresten erişildiği** panel adresidir. `/index.php` dosya adını eklemeyin.

**Her yeni kurulumda bu variable'ı mutlaka ekleyin.** Eski workflow sürümlerinde proje sahibine ait örnek panel adresi için bir varsayılan bulunabilir; o adresi kullanmayın. Panelde gösterilen eski örnek adres yerine kendi gerçek adresinizi yazın. Yeni anahtarınızı kaydetmeden önce bu ayarı tamamlayın.

### 2. Panelden tarayıcı modunu etkinleştirin

Panelde **Ücretsiz gerçek tarayıcı kontrolü → Tarayıcı modunu etkinleştir** seçin.

- Sunucu benzersiz bir API anahtarı üretir.
- Mevcut siteler kontrol kuyruğuna alınır.
- Yerel PHP/cURL kontrolleri devreden çıkar.
- Anahtar panelde kopyalamanız için gösterilir; `var/browser-token` içinde saklanır.
- `var/browser-enabled` dosyası modun etkin olduğunu belirtir.

API anahtarı **panel giriş parolanız değildir**. Anahtarın ekran görüntüsünü veya değerini forumda, Issues içinde ya da sohbette paylaşmayın.

### 3. API anahtarını GitHub Secret olarak ekleyin

**Settings → Secrets and variables → Actions → Secrets → New repository secret**

| Alan | Değer |
|---|---|
| Name | `MONITOR_API_TOKEN` |
| Secret | Panelin ürettiği anahtar |

**Secrets** ve **Variables** farklı sekmelerdir: anahtar Secret, panel adresi Variable olmalıdır. Bu kurulum repository ayarlarını kullanır; Environment secret oluşturmanız gerekmez.

### 4. İlk çalışmayı başlatın

1. GitHub'da **Actions** sekmesini açın.
2. Gerekirse workflow'ları etkinleştirin. Fork'larda başlangıçta kapalı olabilir.
3. Soldan **Browser monitoring** seçin.
4. Sağdaki **Run workflow** düğmesine basın.
5. Güncel varsayılan dalı, bu projede `main`, seçip **Run workflow** deyin.

**New workflow** farklı bir görev oluşturur; mevcut kontrolü başlatmak için kullanılmaz. Kod güncellendikten sonra yeni **Run workflow** başlatın; eski çalışmadaki **Re-run jobs** eski commit'i tekrar çalıştırabilir.

### 5. Sonucu doğrulayın

İlk çalışmada Chromium kurulacağı için birkaç dakika beklemek normaldir. Çalışmaya tıklayıp **monitor → Check sites in Chromium** adımını açın.

Örnek başarılı özet:

```text
Completed browser checks: 25; unsaved/unfinished: 0.
```

Bu, 25 kontrolün **kaydedildiğini** gösterir; 25 sitenin tamamının erişilebilir olduğu anlamına gelmez. Başarılı workflow içinde HTTP 404 gibi gerçek site hataları da kaydedilebilir.

Şu özet de mümkün:

```text
Completed browser checks: 0; unsaved/unfinished: 0.
```

Zamanı gelen/kuyruğa alınan iş yoksa 0 normaldir. Ayrıca secret eksik olduğunda kurulum ve kontrol adımları atlanabilir; sadece yeşil işarete bakmayın, **Check configuration** ve kontrol adımını inceleyin.

Panelde **Sonuçları yenile** seçeneğini kullanın. Güncellenen kartlarda **Kontrol kaynağı: GitHub Chromium** yazar. **Tarayıcı kontrolü: X / toplam** sayacı kaç siteye en az bir Chromium sonucu geldiğini gösterir; bütün sitelerin aynı anda veya bugün kontrol edildiği anlamına gelmez. Güncellik için karttaki **Son kontrol** zamanına bakın.

## Panel kullanımı

### Tek site ekleme

Domain veya tam adres yazabilirsiniz:

```text
example.com
www.example.com
https://example.com/
https://example.com/ozel-sayfa
```

Şemasız adrese `https://` eklenir. Site adı URL'nin host kısmından oluşturulur; adın başındaki `www.` kaldırılır. **İstek adresindeki `www.` korunur**, çünkü bazı siteler buna ihtiyaç duyar. `www.example.com` ile `example.com` bu nedenle ayrı URL olarak kaydedilebilir.

Belirli bir yol eklerseniz kontrol o URL'yi açar. Yalnızca ana sayfayı izlemek istiyorsanız domain/ana sayfa adresi ekleyin. Giriş bilgisi içeren URL'ler ve 80/443 dışındaki portlar kabul edilmez. Uluslararası domainlerde gerekirse ASCII/Punycode biçimini kullanın.

### Toplu domain ekleme

**Toplu domain ekle** alanını açın ve her satıra bir adres yapıştırın:

```text
example.com
www.example.net
https://example.org/
```

- En fazla **1000 dolu satır** eklenebilir.
- Boş satırlar atlanır.
- Aynı normalize edilmiş URL yeniden eklenmez.
- Hatalı satırlar raporlanır; geçerli satırlar kaydedilir.
- Yeni sitelerin varsayılan aralığı **1440 dakika / 24 saat** olur.
- Ekleme bildirimi eklenen ve tekrar olduğu için atlanan sayıları gösterir.

### Kontrol düğmeleri

| Düğme | Tarayıcı modunda davranışı |
|---|---|
| Şimdi kontrol et | Yalnızca ilgili siteyi kuyruğa alır |
| Tümünü kontrol et | Bütün siteleri, seçili filtreden bağımsız kuyruğa alır |
| Kesinti olanları kontrol et | Son kayıtlı durumu hata olan siteleri kuyruğa alır |
| Sonuçları yenile | Güncel kayıtları görmek için paneli yeniden açar |

**Paneldeki bu düğmeler GitHub workflow'unu doğrudan başlatmaz.** Sonraki otomatik çalışma kuyruğu işler. Hemen başlatmak için GitHub → Actions → Browser monitoring → Run workflow kullanın. Aynı düğmeye tekrar tekrar basmak işleri tekrar öne alabilir; ilerlemeyi görmek için **Sonuçları yenile** kullanın.

Kuyrukta manuel istenen işler, bunların arasında hatalı siteler ve panelde üstte gösterilen yeni kayıtlar önceliklendirilir.

### Manuel doğrulama: açık olarak işaretleme

Bir siteyi kendiniz tarayıcıda açıp erişilebilir olduğunu doğruladığınız halde otomatik kontrol hata veriyorsa, karttaki **Manuel doğruladım: açık** düğmesini kullanabilirsiniz.

- Site **24 saat** boyunca etkin manuel doğrulama ile erişilebilir gösterilir; sayaçlar ve filtreler buna göre değişir.
- Kartta **Manuel doğrulandı: açık**, doğrulama zamanı ve bitiş zamanı görünür. Bu, otomatik Chromium kontrolü gibi gösterilmez.
- Otomatik durum, hata, kontrol geçmişi ve erişilebilirlik yüzdesi değiştirilmez; manuel işlem sahte bir HTTP 200 veya başarılı otomatik kontrol örneği üretmez. Karttaki ölçümler önceki otomatik kontrole aittir.
- Sonraki otomatik kontrol 24 saat sonrasına planlanır. Bu süre boyunca yeni bir otomatik durum kontrolü yapılmaz; sitenin arada değişen durumunu bu manuel işaret gizleyebilir.
- Önceden başlamış bir tarayıcı işi manuel kararı ezemez.
- **Kesinti olanları kontrol et**, etkin manuel doğrulamaları atlar.
- **Şimdi kontrol et** ilgili sitenin; **Tümünü kontrol et** bütün sitelerin manuel doğrulamasını erken kaldırır ve yeniden kontrol ister.
- **Manuel doğrulamayı kaldır** seçeneği de bu kaydı otomatik kontrol kuyruğuna döndürür.
- Süre bitince ilk uygun otomatik çalışmada yeniden kontrol edilir. Yeni sonuç gelene kadar önceki otomatik durum tekrar görünür. Panelde güncel görünüm için sayfayı yenileyin.

Manuel doğrulama otomatik kontrol hatasının teknik nedenini düzeltmez; sizin kararınızı süreli ve ayrı bir kaynak olarak kaydeder. Özelliği mevcut kuruluma eklemek için `src/app.php`, `src/browser.php`, `public/index.php`, `public/style.css` dosyalarını güncelleyin; SQLite alanları otomatik eklenir. `var/` dizinini ve anahtarınızı koruyun.

### Filtreler ve görünüm

Üstteki kartlara tıklayın:

- **İzlenen site:** tüm siteler.
- **Erişilebilir:** son kaydı başarılı olanlar.
- **Kesinti / hata:** son kaydı hatalı olanlar.

Hata filtresinde bir site düzelince listeden çıkar. Bu, kaydın silindiği anlamına gelmez. Bütün siteleri görmek için **İzlenen site** kartına dönün.

**Liste / İkili grid / Üçlü grid** seçilebilir. Üçlü grid geniş ekranlarda üç, daha dar ekranlarda iki, telefonlarda tek sütuna iner. Tercihler oturum boyunca korunur.

### Ekran görüntüleri

Tarayıcı modunda küçük resim adresi girmeniz gerekmez. İlk Chromium kontrolünde ekran görüntüsü alınır. Sonraki durum kontrolleri daha sık yapılabilse de küçük resim normalde **24 saat dolduktan sonraki kontrolde** yenilenir.

Görüntü, kontrol edilen sayfanın ilk ekranına ait JPEG'dir; tam sayfa arşivi değildir. Çerez bildirimi veya açılır pencere görünebilir. Görüntü alınamazsa eski resim korunabilir; hata ayrı gösterilir. **Eski resmin görünmesi sitenin şu anda erişilebilir olduğunu kanıtlamaz.** Son kontrol ve küçük resim zamanları farklı olabilir.

## Zamanlama ve sonuçların anlamı

### 24 saatlik aralık ile 15 dakikalık workflow farklı şeylerdir

Workflow her saat UTC'nin `07`, `22`, `37`, `52` dakikalarında çalışacak şekilde tanımlıdır. GitHub bunu geciktirebilir veya yoğunlukta bazı çalışmaları atlayabilir. Gerçek kontrol, site için zaman geldikten sonraki uygun workflow turunda yapılır.

Örnek: Aralığı 24 saat olan bir site başarıyla kontrol edildiyse her 15 dakikada yeniden açılmaz. Workflow bu kaydın henüz zamanı gelmediğini görür ve atlar. Bir dakikalık site aralığı seçmek de GitHub modunda dakikada bir çalışmayı sağlamaz.

Hatalı sonuçta yeniden uygun hale gelme süresi **normal aralık ile 5 dakikadan kısa olanıdır**. Kontrolün fiilen yapılması yine bir sonraki workflow'a bağlıdır. Başarılı sonuçtan sonra normal aralık uygulanır.

Bir çalışmada yaklaşık 8 dakikalık iş bütçesi ve en fazla üç paralel tarayıcı kontrolü vardır. Yüzlerce yavaş site tek turda bitmeyebilir. Kalanlar sonraki turlarda devam eder; bu yüzden bir çalışmada 78 kontrol görmek 200 kayıtlı sitenin kaybolduğu anlamına gelmez.

GitHub, public depolarda uzun süre repository etkinliği olmazsa zamanlanmış workflow'ları devre dışı bırakabilir; güncel politikasında bu süre genellikle 60 gündür. Actions ekranından kontrol edip gerekirse yeniden etkinleştirin. Otomatik zamanlama için workflow dosyası varsayılan dalda olmalıdır.

### Başarı kriteri

Chromium kontrolü:

1. Ana belge yanıtını en fazla 20 saniye bekler.
2. Başarılı HTTP yanıtı sonrası en fazla 20 saniye içinde görünür metin veya görsel içerik arar.
3. Son ana belge HTTP durumu **200–399** ve içerik görünürse başarılı sayar.
4. Tamamlanmayan reklam/analiz scripti yüzünden `DOMContentLoaded` olayını beklemeyi tek başına başarı şartı yapmaz.
5. Taşıma veya boş içerik hatasında yeni tarayıcı bağlamıyla bir kez daha dener. HTTP 4xx/5xx yanıtları başarılı sayılmaz.

TLS doğrulaması açıktır; HTTPS hatasında HTTP'ye sessizce düşülmez. Yalnızca genel internete açık HTTP/HTTPS siteleri ve 80/443 portları desteklenir; localhost, özel ağ ve metadata adresleri engellenir.

GitHub IP'lerine uygulanan WAF/bot engeli, DNS, sertifika, yönlendirme veya bağlantı sorunları hata oluşturabilir. Tarayıcınızdan açılabilen bir site başka bir ağdan erişilemeyebilir. WebKontrol bütün dünya için erişilebilirlik garantisi vermez.

### Yüzde ve süreler

- **Erişilebilirlik yüzdesi**, son 30 gündeki başarılı kontrol örneklerinin toplam kontrol örneklerine oranıdır. Süreyle ağırlıklandırılmış uptime veya kesintinin kaç dakika sürdüğü değildir.
- Yeniden denemeler ve daha sık hata kontrolleri bu oranı etkileyebilir.
- Kontrol yapılmayan zamanlar başarılı sayılmaz.
- Geçmiş 90 gün saklanır; kartta son 20 kontrol görünür.
- Önceki PHP/cURL sonuçları tarayıcı moduna geçince geçmişten silinmez.
- **Yanıt** alanındaki tarayıcı süresi, sayfa açma/içerik bekleme ve varsa tekrar denemesini içerir; yalnızca TTFB veya ağ gecikmesi değildir.
- Paneldeki kayıt zamanları açıkça **UTC** olarak gösterilir; Türkiye saatinden üç saat geridedir.

## Alternatif: cPanel cron ile kontrol

Gerçek tarayıcı yerine PHP/cURL kullanmak isterseniz tarayıcı modunu etkinleştirmeyin veya panelden **Sunucudan kontrol moduna dön** seçin.

cPanel → **Cron İşleri** bölümünde beş zamanlama alanına da `*` yazın:

```text
Dakika: *
Saat: *
Gün: *
Ay: *
Haftanın günü: *
```

Komut örneği:

```sh
/usr/local/bin/php /home/KULLANICI/webkontrol/bin/check.php >> /home/KULLANICI/webkontrol/var/cron.log 2>&1
```

PHP ve proje yollarını kendi sunucunuza göre değiştirin. Bazı cPanel kurulumlarında PHP yolu `/opt/cpanel/ea-php82/root/usr/bin/php` gibi olabilir. CLI PHP'de de `pdo_sqlite` ve `curl` etkin olmalıdır. Cron web işlemiyle aynı veri dizinine ve uygun dosya sahipliğine erişmelidir.

Bu modda:

- HTTP GET ile 200–399 başarılı sayılır; JavaScript çalıştırılmaz.
- DNS yedeği, alternatif IP denemeleri, TLS doğrulaması ve süreli kilitler kullanılır.
- İndirme gövdesi yaklaşık 1 MB ile sınırlıdır; bağlantı başına zaman aşımı ve toplam deneme bütçesi vardır.
- Küçük resimler varsayılan olarak **thum.io** üzerinden alınır; domain ana sayfa adresi bu sağlayıcıya gönderilir.
- Sağlayıcının ücretsiz kota ve önbellek koşulları geçerlidir; ücretsiz veya her istekte taze resim garantisi verilmez.
- Panel açık olması otomatik kontrol başlatmaz; cron gereklidir.

Kontrolü PHP sunucusu, resmi başka sağlayıcı aldığından resim görünürken bağlantı hatası olabilir. Panel sunucusunun kendi dış IP'sine erişimi engelleniyorsa bu mod uygun olmayabilir; GitHub Chromium tercih edin.

**Tarayıcı modu açıkken `bin/check.php` sessizce çıkar.** Mevcut cron'u silmek zorunlu değildir; yerel kontroller Chromium sonuçlarını ezmez. Tarayıcı modunda hedef kontrolleri için cPanel cron'u gerekmez.

## Ayarlar

| Ad | Nerede ayarlanır? | Açıklama |
|---|---|---|
| `MONITOR_PASSWORD` | Hostingde PHP ortam değişkeni | Yönetici paneline giriş parolası |
| `MONITOR_DATA_DIR` | Hosting/CLI ortam değişkeni, isteğe bağlı | Mutlak veri dizini; varsayılan proje kökünde `var/` |
| `MONITOR_API_TOKEN` | GitHub **repository Secret** | Panelin ürettiği ayrı API anahtarı |
| `MONITOR_PANEL_URL` | GitHub **repository Variable** | Sonda `/` bulunan gerçek HTTPS panel adresi |
| `MONITOR_SCREENSHOT_ENDPOINT` | Alternatif cron ortamında, isteğe bağlı | PHP/cURL modundaki HTTPS resim sağlayıcısı öneki |

Son ayarın varsayılanı:

```text
https://image.thum.io/get/width/600/crop/400/noanimate/
```

Öneke ana sayfa URL'si eklenir; yanıt JSON değil PNG/JPEG/GIF/WebP resmi olmalıdır. Bu ayar GitHub Chromium modunda kullanılmaz. Anahtar içeren sağlayıcı adreslerini kaynak koduna yazmayın.

## Sık karşılaşılan sorunlar

| Belirti | Kontrol / çözüm |
|---|---|
| `MONITOR_PASSWORD` tanımlayın mesajı | Değişken PHP'ye aktarılmıyor. `.env` okunmaz; hostingin PHP ortam ayarını kontrol edin. |
| `could not find driver` | İlgili PHP sürümünde `pdo_sqlite` etkin olmalı. |
| SQLite yazma / readonly / unable to open hatası | `var/` dizini ve dosya sahipliği, disk alanı ve yazma izinlerini kontrol edin. `777` vermeyin. |
| Alan adında `Index of /` veya 404 | Document root `public/` dizini olmalı ya da tam alt dizin adresini kullanmalısınız. GitHub variable'ını değiştirmek web sunucusunun kökünü değiştirmez. |
| CSS gelmiyor | Panel URL'si, `public/style.css`, önbellek ve rewrite kurallarını kontrol edin. Ctrl+F5 deneyin. |
| Workflow görünmüyor | Fork/kopyada `.github/workflows/browser-monitor.yml` mevcut mu, varsayılan dalda mı ve Actions açık mı kontrol edin. |
| `Run workflow` görünmüyor | Doğru workflow sayfasına ve yetkili GitHub hesabıyla girin; `workflow_dispatch` tanımı ve Actions izni bulunmalı. |
| Workflow yeşil ama kontrol yok | Secret eksikse adımlar atlanabilir. `Check configuration` ve `Check sites in Chromium` loglarını okuyun. Zamanı gelen iş olmayabilir. |
| `Panel API HTTP 404 (claim)` | `MONITOR_PANEL_URL` yanlış olabilir veya `browser-api.php` yüklenmemiştir. Kök panel adresini düzeltmeden variable'ı o adrese taşımayın. |
| `Panel API HTTP 401` | GitHub Secret ile panel anahtarı uyuşmuyor; kopyalamayı ve adını kontrol edin. |
| `Panel API HTTP 503` | Tarayıcı modu kapalı olabilir; hosting/proxy de 503 üretebilir. Panel ayarını ve sunucu hata kayıtlarını inceleyin. |
| `Panel API HTTP 403` | Hosting WAF/ModSecurity/IP veya API erişim kurallarını inceleyin. |
| `fetch failed`, `ETIMEDOUT`, `UND_ERR_CONNECT_TIMEOUT` | GitHub runner panelin 443 portuna ulaşamıyor olabilir. Bu, hedef sitelerin kapalı olduğunu göstermez. Hosting erişim/rate-limit kurallarını inceleyin. |
| `net::ERR_TUNNEL_CONNECTION_FAILED` | Tarayıcı proxy'sinin hedefe DNS/TCP bağlantısı kurulamadı. Yeni sonuçtaki ek hata nedenini okuyun. |
| HTTP 404 / 403 olan site tarayıcımda açılıyor | Tam URL'yi karşılaştırın; GitHub IP'sine WAF/bot engeli veya farklı yönlendirme uygulanabilir. Bu yanıtlar yeşile çevrilmez. |
| Resim var ama hata görünüyor | Resim eski veya alternatif modda farklı sağlayıcıdan alınmış olabilir. Tarihe ve kontrol kaynağına bakın. |
| Workflow başarılı ama panel aynı görünüyor | Sayfayı yenileyin; güncel source/tarihe bakın. Hata filtresinde düzelen siteler görünmez. API'nin doğru panel kurulumuna gittiğini kontrol edin. |
| Bütün siteler tek turda bitmedi | Çalışma süre bütçesine takılmış olabilir; ilerleme sayacı ve sonraki turları takip edin. Sürekli tekrar kuyruklamayın. |
| Mailde “workflow failed” geldi | Bu, o çalışmanın başarısızlığıdır; görevin tümden kapatıldığını tek başına göstermez. Hatalı adımın logunu okuyun. |

### GitHub logları nasıl okunur?

**Actions → Browser monitoring → ilgili çalışma → monitor → Check sites in Chromium** bölümünü açın. `Process completed with exit code 1` neden değil, özet sonuçtur; birkaç satır üstündeki hata önemlidir.

Güncel API istemcisi IPv4, TLS bağlantı tekrar kullanımı ve seri istek gönderimi kullanır. Geçici ağ hatalarını ve HTTP 429/500/502/503/504 yanıtlarını en fazla dört kez dener. Kalıcı bir firewall engelini aşamaz. Kaydedilemeyen sonuçlarda eski site durumu korunur; iş kilidi dolunca yeniden alınabilir. Aynı kaydedilmiş sonucun tekrar gönderilmesi geçmişi çoğaltmaz.

Sorun bildirirken hata satırlarını ve kullandığınız sürümü/commit'i paylaşın; API anahtarını, panel parolasını veya parola görünen ayar ekranını paylaşmayın.

### Terminal olmadan tanı

Normal tarayıcı kurulumu için Terminal gerekmez. PHP/cURL modunda doğrudan sunucu erişimini incelemek gerekirse aşağıdaki CLI komutu vardır:

```sh
/usr/local/bin/php /home/KULLANICI/webkontrol/bin/diagnose.php https://example.com/
```

Terminal yoksa geçici cron göreviyle çıktıyı `var/diagnose.log` dosyasına yönlendirebilirsiniz. Bir kez çıktı aldıktan sonra **yalnızca geçici tanı cron'unu** kaldırın. Bu komut PHP/cURL/TLS, DNS ve sunucudan bağlantıyı gösterir; GitHub Chromium sonucunun yerine geçmez.

## Güvenlik, yedekleme ve güncelleme

### Güvenlik ve veri paylaşımı

- Güçlü panel parolası ve HTTPS kullanın; API anahtarını ayrı tutun.
- `var/`, SQLite, `browser-token`, oturumlar, `.git` ve kaynak dizinlerini internete açmayın. Belge kökü `public/` olmalıdır.
- `.htaccess` Apache/LiteSpeed içindir; Nginx'te eşdeğer kuralları kurun.
- Tarayıcı modu etkinleştirilirken veri dizinine mevcut değilse `Require all denied` koruması eklenir; mevcut kullanıcı dosyası değiştirilmez.
- API yalnızca doğru anahtarlı POST isteklerini, atanmış iş kimliğini ve süresini kontrol ederek kabul eder.
- Runner'daki proxy her bağlantıda adresi çözümleyip genel IP'ye sabitler; özel/yerel ağlar ve desteklenmeyen portlar engellenir.
- API anahtarı yalnızca panel API'sine gönderilir; hedef sitelere veya Chromium işlem ortamına verilmez.
- Chromium modunda domainler ve sayfa içerikleri GitHub runner'ında işlenir. Ekran görüntüleri panelinize gönderilir; workflow bunları public artifact olarak yayımlamaz.
- PHP/cURL modunda DNS yedeği gerekirse Cloudflare/Google HTTPS DNS'e domain sorgusu, küçük resim için thum.io'ya ana sayfa adresi gönderilir.
- Yalnızca sahibi olduğunuz veya kontrol etmeye yetkili olduğunuz genel internet sitelerini ekleyin.

### API anahtarını yenileme

1. Panelden tarayıcı modunu kapatın; devam eden workflow varsa bitmesini bekleyin veya durdurun.
2. Dosya Yöneticisi'nden yalnızca `var/browser-token` dosyasını silin.
3. Tarayıcı modunu tekrar açın; yeni anahtar üretilir.
4. GitHub repository secret `MONITOR_API_TOKEN` değerini yenisiyle değiştirin.
5. Yeni workflow başlatın. Veritabanını veya diğer `var/` dosyalarını silmeyin.

### Yedekleme

Veritabanı `var/monitor.sqlite`, resimler `var/thumbnails/` altında tutulur. Panelin yerel parola ayarını ve tarayıcı anahtarını da güvenli biçimde yedekleyin; bu yedekleri public GitHub'a yüklemeyin.

SQLite WAL modunda çalışır. Yazma devam ederken yalnızca `.sqlite` dosyasını kopyalamak tutarsız/eksik yedek üretebilir. SQLite uyumlu bir yedekleme yöntemi kullanın veya workflow/cron ve web üzerinden yazmaları durdurup veritabanı ile varsa WAL/SHM durumunu tutarlı şekilde yedekleyin.

### Güncelleme

1. Verileri ve yerel ayarları yedekleyin.
2. Yeni sürümün `public/`, `src/`, gerekiyorsa `bin/` dosyalarını yükleyin.
3. `var/` ve parola içeren yerel `.htaccess` dosyalarını **silmeyin/ezmeyin**. Proje kökü koruma kurallarını gerekiyorsa birleştirin.
4. GitHub fork/kopyanızdaki `browser/`, `browser/package-lock.json` ve workflow dosyasını da güncelleyin. GitHub'daki kodu değiştirmek hostinge otomatik dosya yüklemez; yalnızca hosting dosyalarını değiştirmek de runner kodunu güncellemez.
5. Paneli açın; mevcut SQLite şemasına gerekli alanlar otomatik eklenir.
6. Ctrl+F5 yapın ve güncel `main` üzerinden yeni **Run workflow** çalıştırın.

## Geliştirme ve testler

Yerel PHP paneli:

```sh
export MONITOR_PASSWORD='YALNIZCA_YEREL_TEST_ICIN_KENDI_PAROLANIZ'
php -S 127.0.0.1:8080 -t public
```

PowerShell için değişken biçimi `$env:MONITOR_PASSWORD='...'` şeklindedir. PHP'nin yerleşik web sunucusunu üretim hizmeti olarak kullanmayın. Localhost'taki panel, ek ağ yapılandırması olmadan GitHub runner'ına açık değildir; public API için HTTPS gerekir.

PHP testleri:

```sh
php tests/smoke.php
php tests/features.php
php tests/network.php
php tests/browser.php
php tests/browser-priority.php
php tests/manual.php
find src public bin tests -name '*.php' -exec php -l {} \;
```

Testler geçici veri dizinleri kullanır. `features.php` içindeki alternatif resim servisi HTTP testleri, kontrollü bir `MONITOR_SCREENSHOT_ENDPOINT` fixture'ı verilmezse **UNRUN** olarak bildirilir; bu, çalıştırılmış bir dış servis testi değildir.

Gerçek Chromium ve API istemcisi testleri:

```sh
cd browser
npm ci --ignore-scripts
npx playwright install --with-deps chromium
npm test
```

Sistemde Chromium varsa:

```sh
CHROMIUM_PATH=/usr/bin/chromium npm test
```

Tarayıcı fixture testi boş ve kullanılabilir bir yerel **80 portu** ister. Node.js 22, OpenSSL komutu ve Playwright bağımlılıklarını destekleyen test ortamı kullanın. Testler görünür içerik/DOM gecikmesi, boş sayfa, HTTP hata ve yönlendirme, özel IP engeli, API tekrar denemesi ve TLS doğrulaması gibi durumları sınar. Kendi hostinginiz ile GitHub arasındaki canlı erişim ayrıca ilk workflow ile doğrulanmalıdır.

## Mevcut olmayan özellikler ve lisans

Bu sürümde çok kullanıcılı üyelik, e-posta/SMS/Telegram kesinti bildirimi, halka açık durum sayfası, SLA raporu, giriş yapılmış sayfalara özel test veya bütün site işlevlerini doğrulayan senaryolar bulunmaz. GitHub'ın kendi workflow başarısızlık e-postası bir WebKontrol kesinti alarmı değildir.

Depoda şu an ayrı bir `LICENSE` dosyası bulunmamaktadır. Kullanım, yeniden dağıtım ve ticari kullanım için bir lisans türü varsaymayın; paylaşım koşullarını proje sahibi belirler. Bu README bir lisans beyanı yerine geçmez.
