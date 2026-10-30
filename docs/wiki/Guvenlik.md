# Güvenlik

VibeCraft'ın kendiliğinden koruduğu şeyler ve site sahibi olarak sizin ilgilenmeniz gerekenler.

> English: [Security](Security.md)

## Yerleşik korumalar

| Alan | Koruma |
|---|---|
| Kurulum | Yeni yüklenmiş bir site yalnızca hosting dosyalarındaki tek kullanımlık kodla kurulabilir. Kurulumdan sonra sihirbaz kalıcı olarak kapanır. |
| Dosyalar | `.env`, `storage`, `vendor`, kaynak kod, arşivler (`.zip`, `.tar.gz`…), veritabanı dökümleri (`.sql`), yedekler (`.bak`, `~`), kayıt dosyaları ve gizli dosyalar iki klasör yerleşiminde de web sunucusu tarafından reddedilir. Yalnızca `index.php` PHP olarak çalışır. |
| Hesaplar | Şifreler özetlenerek saklanır; giriş, kayıt ve şifre sıfırlama formlarında deneme sınırı vardır; isteğe bağlı CAPTCHA; personel için zorunlu TOTP iki aşamalı doğrulama. |
| Oturumlar | Şifreli, `HttpOnly`, `SameSite`; HTTPS'te güvenli çerezler. |
| Sayfalar | İstek başına nonce ile katı Content Security Policy, satır içi betik yok; kullanıcı içeriği kaçışlanır; Markdown ham HTML çalıştırmadan işlenir. |
| Ödemeler | Siparişi yalnızca imzalı sağlayıcı bildirimleri değiştirir; tutarlar siparişle karşılaştırılır; her bakiye değişikliği benzersiz bir defter kaydıdır. |
| Oyun sunucuları | Sunucuya özel anahtarlarla imzalı istekler; eklenti dışarıya bağlanır, açık port yoktur; komut köklerine sunucuda izin verilmelidir; komutlara yalnızca türü belli yer tutucular girer. |
| Sırlar | Ödeme, CAPTCHA ve Discord sırları şifreli saklanır, bir daha gösterilmez ve denetim günlüğüne yazılmaz. |
| Personel | İki aşamalı doğrulama, şifre yeniden onayı ve her yönetim değişikliği için denetim günlüğü. |

## Sizin kontrol listeniz

- Alan adında **HTTPS** (cPanel AutoSSL). Ayarlar'daki **HTTPS zorunluluğu (HSTS)** seçeneğini yalnızca alan
  adında ve bütün alt alan adlarında HTTPS çalıştıktan sonra açın.
- **Kök klasörü `public/` olan** yerleşimi tercih edin. `public_html` yerleşimi `mod_rewrite` olan Apache'de
  güvenlidir, ama web klasöründe daha fazla dosya tutar.
- Açtıktan sonra **sürüm zip'ini silin**; site klasörüne asla veritabanı dışa aktarımı veya yedek
  yüklemeyin.
- Canlı sitede `.env` içinde **`APP_DEBUG=false`** kalsın.
- cPanel, veritabanı, e-posta ve sahip hesabı için **güçlü ve farklı şifreler** kullanın; cPanel'de de iki
  aşamalı doğrulamayı açın.
- Personele işine yetecek **en küçük rolü** verin ([Roller ve Yetkiler](Roller-ve-Yetkiler.md)).
- Eklentide yalnızca ürünlerinizin ihtiyaç duyduğu **komut köklerine** izin verin.
- VibeCraft'ı, PHP'yi ve sunucu eklentilerinizi **güncel** tutun.

## CAPTCHA

**Yönetim → Entegrasyonlar**: **Cloudflare Turnstile**, **hCaptcha** veya **Google reCAPTCHA** seçip sağlayıcının
verdiği site anahtarını ve gizli anahtarı girin. CAPTCHA bundan sonra giriş, kayıt, şifre sıfırlama, destek
talepleri, kredi kodları, yetkili başvuruları ve forumu korur. CAPTCHA hizmetine ulaşılamazsa bu formlar
korumasız bırakılmak yerine reddedilir.

Sağlayıcının panelinde yalnızca kendi alan adınıza izin verin.

## Cloudflare

Siteniz Cloudflare proxy'si (turuncu bulut) arkasındaysa:

1. SSL modunu **Full (strict)** yapın.
2. `.env` dosyasına `VIBECRAFT_PROXY_MODE=cloudflare` ekleyin. VibeCraft ziyaretçi IP'sine ancak istek gerçekten
   bir Cloudflare adresinden geliyorsa güvenir (adres listesi cron ile haftada bir yenilenir). Bu ayar olmadan
   her ziyaretçi Cloudflare'den geliyormuş gibi görünür ve deneme sınırları herkesi birlikte etkiler.
3. Giriş, hesap, yönetim, sepet, ödeme, siparişler, destek ve `/api/` adreslerini **önbelleğe almayın**.
4. Ödeme webhook'larını (`/api/payments/…`) ve eklenti adresini (`/api/bridge/…`) bot korumalarından
   ("Under Attack", Bot Fight Mode, managed challenge) hariç tutun. Bunlar kendi imzalarıyla korunur.

Başka bir ters proxy arkasında `VIBECRAFT_PROXY_MODE=custom` kullanın ve proxy adreslerini
`VIBECRAFT_TRUSTED_PROXIES` içinde (virgülle ayrılmış CIDR) listeleyin.

## Yedekler

Düzenli, şifreli ve web klasörünün dışında yedek alın:

| Ne | Neden |
|---|---|
| Veritabanı | Bütün hesaplar, siparişler, bakiyeler ve içerik |
| `storage/app` | Yüklenen görseller ve sipariş kopyaları |
| `.env` | Uygulama anahtarı: o olmadan şifreli sırlar ve iki aşamalı doğrulama ayarları okunamaz |
| Her oyun sunucusunda `plugins/VibeCraftBridge/receipts.vcj` | Teslimat kaydı; eşleşen veritabanı yedeğiyle birlikte geri yükleyin |

Arada bir yedeği ayrı bir sitede geri yükleyerek deneyin. cPanel **Yedekleme** veya **JetBackup** ilk üçünü
karşılayabilir.

## Uygulama anahtarını gelişigüzel değiştirmeyin

`.env` içindeki `APP_KEY` ödeme ve eklenti sırlarını ve iki aşamalı doğrulama ayarlarını şifreler. Onu
değiştirmek bunları okunamaz hale getirir. Sızarsa yenilemeyi [docs/INSTALL.md](../INSTALL.md) dosyasındaki
gibi planlayın.

## Güvenlik açığı bildirme

Lütfen herkese açık issue açmayın. [SECURITY.md](../../SECURITY.md) dosyasında anlatıldığı gibi gizli olarak
bildirin.

Teknik ayrıntılar: [docs/SECURITY.md](../SECURITY.md).
