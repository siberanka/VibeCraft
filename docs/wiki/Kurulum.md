# Kurulum

Bu sayfa VibeCraft'ı cPanel'li paylaşımlı hostinge kurar. Apache ve `.htaccess` desteği olan diğer
panellerde (Plesk, DirectAdmin, CyberPanel) adımlar aynıdır; yalnızca menü adları değişir.

> English: [Installation](Installation.md)

## Gerekenler

- HTTPS'li bir alan adı veya alt alan adı (cPanel AutoSSL yeterlidir).
- PHP 8.4 veya üstü ve şu eklentiler: ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql,
  tokenizer, xml (ve genelde açık gelen filter, hash, session, sodium).
- Boş bir MySQL 8 veya MariaDB veritabanı.
- cPanel **Dosya Yöneticisi** ve **Cron İşleri** erişimi.

Terminal, Node, Redis veya arka planda çalışan bir işçi gerekmez.

## 1. Paketi indirin ve doğrulayın

[Sürümler sayfasından](https://gitlab.com/siberanka/vibecraft/-/releases) indirin
(yedek: [GitHub](https://github.com/siberanka/VibeCraft/releases)):

| Dosya | Kullanım |
|---|---|
| `vibecraft-<sürüm>.zip` | Web sitesi; bütün PHP bağımlılıkları içindedir |
| `VibeCraftBridge-<sürüm>.jar` | Paper eklentisi ([Paper Eklentisi](Paper-Eklentisi.md)) |
| `SHA256SUMS` | Doğrulama özetleri |

Platformların otomatik eklediği "Source code" arşivleri bağımlılıkları **içermez**; bunları hostinge
yüklemeyin.

Linux veya macOS'ta `sha256sum -c SHA256SUMS`, Windows'ta
`certutil -hashfile vibecraft-<sürüm>.zip SHA256` komutunu çalıştırıp sonucu `SHA256SUMS` dosyasındaki
satırla karşılaştırın.

## 2. PHP 8.4 seçin

1. cPanel → **MultiPHP Manager**: alan adını işaretleyip PHP 8.4 (veya üstü) seçin.
2. cPanel → **Select PHP Version** → **Extensions** (CloudLinux hostinglerde): yukarıdaki eklentilerin
   işaretli olduğundan emin olun. Bu ekranı olmayan hostinglerde genellikle zaten açıktır.

## 3. Veritabanını oluşturun

1. cPanel → **MySQL Veritabanları** → *Yeni Veritabanı Oluştur*, örneğin `hesap_vibecraft`.
2. *Yeni Kullanıcı Ekle* ile güçlü şifreli bir kullanıcı oluşturun.
3. *Kullanıcıyı Veritabanına Ekle* → **TÜM AYRICALIKLAR** seçeneğini işaretleyin.

Veritabanı adını, kullanıcı adını ve şifreyi not edin; kurulum sihirbazı bunları ister. Sunucu genellikle
`localhost`, port `3306` olur.

## 4. Dosyaları yükleyin

İki yerleşimden birini seçin.

### Yerleşim A (önerilen): kök klasör `public/`

1. Dosya Yöneticisi → ana klasörünüzü (`/home/HESAP`) açın, zip'i yükleyip **Extract** (Çıkart) ile açın.
   `vibecraft` adlı bir klasör oluşur.
2. cPanel → **Alan Adları** → alan adını *Yönet* → **Belge Kökü** (Document Root) değerini
   `vibecraft/public` yapın.

İnternetten yalnızca `public` klasörüne ulaşılabilir; uygulama kodu, `.env` ve `storage` dışarıda kalır.

### Yerleşim B: her şey `public_html` içinde

Kök klasör değiştirilemiyorsa (bazı hostinglerde ana alan adı) bunu kullanın.

1. Zip'i `public_html` içine yükleyip açın.
2. Yeni `vibecraft` klasörüne girin, bütün dosyaları seçin (**Tümünü Seç**; `.htaccess` gibi gizli
   dosyaların göründüğünden emin olun: *Ayarlar → Gizli Dosyaları Göster*), `public_html` içine
   **Taşı**yın ve boş kalan `vibecraft` klasörünü silin.

Paketteki `.htaccess` her isteği `public/` klasörüne yönlendirir; `.env`, `storage`, `vendor`, arşivler,
veritabanı dökümleri ve yedek dosyalar dahil geri kalan her şeyi reddeder. Apache'de `mod_rewrite` yoksa
site dosya açığa çıkarmak yerine bütün istekleri reddeder.

### Sonrasında

**Yüklediğiniz zip dosyasını silin.** İki yerleşimde de internetten indirilemez, ama sunucuda bir işe
yaramaz.

## 5. Tarayıcıdaki kurulum sihirbazı

`https://alanadiniz/` adresini açın. Sihirbaz kendiliğinden başlar ve sağda **Barındırma kontrolleri**ni
gösterir. Her satır **Hazır** olmalıdır; kontrollerin anlamı [Sorun Giderme](Sorun-Giderme.md) sayfasında.

### Adım 1 — Kurulum kodu

Sihirbaz `storage/app/setup-token.txt` dosyasına tek kullanımlık bir kod yazar. Dosyayı Dosya
Yöneticisi'nde açın (sağ tık → *View* veya *Edit*), 64 karakterlik kodu kopyalayıp forma yapıştırın.

Bu dosyayı yalnızca hosting dosyalarınıza erişebilen kişi okuyabilir; böylece siz kurulumu bitirmeden
kimse yeni yüklenmiş sitenizi ele geçiremez.

### Adım 2 — Veritabanı

| Alan | Değer |
|---|---|
| Site adresi | Oyuncuların açacağı tam adres, örneğin `https://play.ornek.com` (yol ve sondaki `/` olmadan) |
| Veritabanı sunucusu | `localhost` (veya hostinginizin verdiği adres) |
| Port | `3306` |
| Veritabanı adı / kullanıcı / şifre | 3. adımdaki bilgiler |

Sihirbaz bağlantıyı dener, ayarları `.env` dosyasına yazar ve bütün tabloları oluşturur. Yavaş
hostinglerde bu bir dakikayı bulabilir. Boş bir veritabanı kullanın; tablolar oluşturulamazsa adım durur.

### Adım 3 — Sahip hesabı

Topluluk adını, sahibin kullanıcı adını (3–24 harf, rakam veya `_`), e-postasını ve harf ile rakam içeren
en az 12 karakterlik bir şifre girin. Gönderdiğinizde:

- bütün yetkilere sahip sahip hesabı oluşturulur,
- kurulum kodu dosyaları silinir,
- kurulum sihirbazı kalıcı olarak kapanır (`/install` "bulunamadı" döner),
- oturumlar ve önbellek veritabanına taşınır.

Giriş sayfasına yönlendirilirsiniz.

## 6. Cron işini ekleyin

Sihirbazın son adımı hostinginize uygun komutu gösterir, örneğin:

```
* * * * * /opt/cpanel/ea-php84/root/usr/bin/php /home/HESAP/vibecraft/artisan schedule:run >> /dev/null 2>&1
```

cPanel → **Cron İşleri** → *Yeni Cron İşi Ekle* → *Dakikada bir* seçip komutu yapıştırın (baştaki beş
`*` alanını form kendisi doldurur). PHP yolu PHP **8.4 CLI** dosyasını göstermelidir; emin değilseniz
hosting firmanıza sorun. Cron olmadan teslimatlar, ödeme tekrarları, sunucu durumu ve sipariş süre
dolumları çalışmaz.

## 7. İlk adımlarla devam edin

Giriş yapın ve [İlk Adımlar](Ilk-Adimlar.md) sayfasını izleyin: iki aşamalı doğrulamayı açın, e-postayı
ayarlayın ve sunucu bilgilerinizi girin.

## Terminal ile elle kurulum

VPS veya SSH erişimli hostingler için [docs/INSTALL.md](../INSTALL.md) dosyasındaki "Manual install"
bölümüne bakın. Sürüm zip'i orada da çalışır; kaynak koddan kurulum Composer gerektirir.

## nginx

Paketin koruması Apache `.htaccess` dosyalarına dayanır. nginx'te `root` değerini `public` klasörü yapın,
bilinmeyen yolları `index.php` dosyasına gönderin ve server bloğunda gizli dosyaları ile arşiv/yedek
uzantılarını reddedin.
