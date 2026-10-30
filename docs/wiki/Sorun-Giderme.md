# Sorun Giderme

Sık karşılaşılan sorunlar ve çözümleri. Bir hata sayfası çıktığında asıl neden `storage/logs/laravel.log`
dosyasındadır (Dosya Yöneticisi'nde açıp son satırlara bakın).

> English: [Troubleshooting](Troubleshooting.md)

## Kurulum sihirbazı

### Bir barındırma kontrolü "Kontrol gerekli" diyor

| Kontrol | Çözüm |
|---|---|
| PHP 8.4+ | cPanel → **MultiPHP Manager**: alan adı için PHP 8.4 veya üstünü seçin. |
| PHP eklentileri | Eksik olanlar kontrollerin altında listelenir. **Select PHP Version → Extensions** bölümünden açın veya hosting firmanıza sorun. |
| Uygulama anahtarı | `.env` oluşturulamadı. Uygulama klasörünün hesabınız tarafından yazılabilir olduğundan emin olun (klasörler için `755`), sonra sayfayı yenileyin. |
| Yazılabilir depolama | `storage` ve `bootstrap/cache` ile içlerindeki her şey yazılabilir olmalı: klasörler `755`, dosyalar `644`. Asla `777` kullanmayın. |
| Yazılabilir .env dosyası | Uygulama klasörü veya `.env` yazılabilir değil. İzinleri düzeltin (`.env` için `600` veya `644`). |
| Üretimde hata ayıklama kapalı | `.env` içinde `APP_DEBUG=false` yapın. |

### `storage/app/setup-token.txt` dosyası yok

Kurulum sayfasını bir kez açın; dosya ilk ziyarette yazılır. Hâlâ oluşmuyorsa `storage/app` yazılabilir
değildir (yukarıya bakın). Kurulumdan sonra dosya bilerek silinir.

### "Kurulum kodu geçersiz"

`setup-token.txt` içindeki 64 karakterlik satırın tamamını boşluksuz kopyalayın. Form dakikada beş denemeye
izin verir.

### "Veritabanına bağlanılamadı"

- Sunucu genellikle `localhost` olur; bazı firmalar ayrı bir sunucu adı verir.
- cPanel hesap adınızı ön ek olarak ekler: tam adları kullanın, örneğin `hesap_vibecraft` ve `hesap_kullanici`.
- Kullanıcı **MySQL Veritabanları** bölümünde veritabanına **TÜM AYRICALIKLAR** ile eklenmiş olmalıdır.
- Şifreyi yeniden kopyalayın; her türlü karakter içeren şifreler desteklenir.

### "Veritabanı tabloları oluşturulamadı"

**Boş** bir veritabanı kullanın ve kullanıcının bütün yetkilere sahip olduğundan emin olun. Çok yavaş
hostinglerde tekrar deneyin; adım aynı veritabanında güvenle tekrarlanabilir.

### Kurulum sayfası stilsiz açılıyor veya formlar `https://` adresine gidiyor

Siteyi gerçekten kullanacağınız adresle açın. Site Cloudflare arkasındaysa SSL modunu *Flexible* değil
**Full (strict)** yapın.

## Site

### Her sayfada "403 Forbidden"

- Yerleşim B (`public_html`): `.htaccess` dosyası diğer dosyalarla birlikte taşınmamış (Dosya Yöneticisi'nde
  gizlidir; *Gizli Dosyaları Göster* seçeneğini açın) veya Apache'de `mod_rewrite` yok. Hosting firmanıza
  sorun.
- Yerleşim A: kök klasör `/public` ile bitmelidir.

### "400 Unrecognized host"

Kurulumdan sonra site yalnızca `APP_URL` içindeki adrese yanıt verir (host başlığı saldırılarına karşı
koruma). Yeni bir alan adına geçtiyseniz veya `www` ile `www`'suz arasında değiştiyseniz `.env` içindeki
`APP_URL` değerini güncelleyin ve diğer biçimi cPanel **Yönlendirmeler** bölümünden ona yönlendirin.

### "500 Server Error"

`storage/logs/laravel.log` dosyasının sonuna bakın. Sık nedenler: `.env` içinde yazım hatası (boşluk içeren
değerler çift tırnak ister), yüklemeden sonra yanlış dosya izinleri veya alan adı için 8.4'ten eski bir PHP
sürümü seçilmiş olması.

### `.env` değişiklikleri etki etmiyor

Daha önce `php artisan config:cache` çalıştırdıysanız her değişiklikten sonra `php artisan optimize:clear`
çalıştırın.

## Cron çalışmıyor

- Komut PHP 8.4'ün **CLI** dosyasını kullanmalıdır, örneğin `/opt/cpanel/ea-php84/root/usr/bin/php`.
  `/usr/local/bin/php` çoğu zaman daha eski bir sürümdür.
- `artisan` yolu tam olmalıdır: `/home/HESAP/vibecraft/artisan`.
- Hatayı görmek için bir çalıştırmalık `>> /dev/null 2>&1` kısmını kaldırın ve cPanel'in çıktıyı size
  e-postayla göndermesini sağlayın.

## E-posta gelmiyor

- `.env` içindeki `MAIL_` ayarlarını ([İlk Adımlar](Ilk-Adimlar.md#2-e-postayı-ayarlayın)) ve cron'un
  çalıştığını kontrol edin: e-postaları kuyruk işi gönderir.
- `MAIL_FROM_ADDRESS` kendi alan adınızdaki bir posta kutusu olmalıdır.
- Postaların spam'e düşmemesi için alan adına SPF, DKIM ve DMARC kayıtları ekleyin (cPanel → **Email
  Deliverability**).

## Ödemeler

Ödenmiş bir sipariş hâlâ beklemede görünüyorsa: [Ödemeler → Bildirim geç gelirse veya başarısız
olursa](Odemeler.md#bildirim-geç-gelirse-veya-başarısız-olursa).

## Eklenti

### Eklenti bağlanmıyor

- `enabled: true` ve `site` tam olarak `https://alanadiniz` (yol ve sondaki `/` olmadan).
- `key-id` ve `secret` **son** anahtar yenilemesinden alınmış.
- Oyun sunucusu web sitesine HTTPS ile ulaşabiliyor (bazı firmalar dışarı giden bağlantıları engeller;
  sorun).
- Oyun sunucusunun saati doğru (beş dakikadan fazla sapma reddedilir); NTP saat eşitlemesini açın.
- Sunucu konsolu açılışta asıl nedeni gösterir. Offline mode, eski BungeeCord forwarding ve Folia bilerek
  reddedilir.

### Bir teslimat gelmiyor

| Durum veya ileti | Neden |
|---|---|
| Oyuncu çevrimdışıyken bekliyor | Komutta **Oyuncu çevrimiçi olmalı** işaretli; oyuncu girince çalışır |
| Durduruldu, komut kökü izinli değil | Komutun ilk kelimesini `allowed-command-roots` listesine ekleyip yeniden başlatın |
| Belirsiz | Bkz. [Paper Eklentisi → Belirsiz teslimatlar](Paper-Eklentisi.md#belirsiz-teslimatlar) |
| Ürün bağlı hesap istiyor | Oyuncu önce Minecraft hesabını bağlar |

### `/vclink` kodun geçersiz olduğunu söylüyor

Kodlar 10 dakika sonra geçersiz olur ve bir kez kullanılır. **Hesap** sayfasından yenisini oluşturun.

## Hesaplar

### Bir yetkili iki aşamalı doğrulama cihazını kaybetti

Kurtarma kodlarından biriyle giriş yapar ve **Hesap → Güvenlik** bölümünden iki aşamalı doğrulamayı yeniden
kurar.

### Sahip giriş yapamıyor

Bkz. [Roller ve Yetkiler → Sahip erişimini kaybetmek](Roller-ve-Yetkiler.md#sahip-erişimini-kaybetmek).

## Hâlâ çözemediniz mi?

[GitLab](https://gitlab.com/siberanka/vibecraft/-/issues) üzerinde VibeCraft sürümü, PHP sürümü, hosting türü
ve `laravel.log` dosyasındaki ilgili satırlarla bir issue açın. Paylaşmadan önce e-posta adreslerini,
anahtarları ve şifreleri silin. Güvenlik sorunlarını gizli olarak bildirin ([SECURITY.md](../../SECURITY.md)).
