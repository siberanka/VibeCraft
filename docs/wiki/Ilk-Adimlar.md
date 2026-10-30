# İlk Adımlar

[Kurulumdan](Kurulum.md) hemen sonra, bu sırayla yapılacaklar.

> English: [First Steps](First-Steps.md)

## 1. İki aşamalı doğrulamayı açın

Yönetim paneli (`/admin`) yalnızca iki aşamalı doğrulaması açık personel hesaplarına açılır. Giriş yapın,
**Hesap → Güvenlik** sayfasını açın, QR kodu bir doğrulama uygulamasıyla (Aegis, 2FAS, Google
Authenticator, Microsoft Authenticator vb.) okutun ve bir kodla onaylayın. Kurtarma kodlarını internete
bağlı olmayan güvenli bir yerde saklayın.

Yönetim paneli hassas sayfalardan önce zaman zaman şifrenizi yeniden ister.

## 2. E-postayı ayarlayın

Kayıt e-postaları, şifre sıfırlama ve sipariş kopyaları gerçek bir e-posta sunucusu ister. Kurulum
sihirbazı bunu sormaz; Dosya Yöneticisi'nde `.env` dosyasını düzenleyin (`artisan` dosyasının yanındadır):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=mail.alanadiniz.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=no-reply@alanadiniz.com
MAIL_PASSWORD="e-posta hesabınızın şifresi"
MAIL_FROM_ADDRESS="no-reply@alanadiniz.com"
MAIL_FROM_NAME="Sunucunuzun Adı"
```

E-posta hesabını önce cPanel → **E-posta Hesapları** bölümünde oluşturun. Hostinginiz STARTTLS istiyorsa
`587` portunu `MAIL_SCHEME=smtp` ile kullanın. Boşluk veya `#` içeren değerleri çift tırnak içine yazın.
Oyunculara kaydı açmadan önce giriş sayfasındaki "Şifremi unuttum" ile deneyin.

`.env` uygulama anahtarınızı ve şifrelerinizi tutar: kendinize özel bir kopyasını saklayın, kimseyle
paylaşmayın.

## 3. Site ayarlarını doldurun

**Yönetim → Ayarlar**:

| Ayar | Açıklama |
|---|---|
| Topluluk adı, ana sayfa başlığı, kısa tanıtım, topluluk açıklaması | Üst bölümde, ana sayfada ve arama sonuçlarında görünür |
| Java sunucu adresi, Bedrock sunucu adresi, Bedrock bağlantı noktası | Ana sayfada kopyalama düğmesiyle gösterilir |
| Logo görseli, ana sayfa görseli | [Görsel kütüphanesinden](Gorunum-ve-Temalar.md#görsel-kütüphanesi) seçilir |
| Varsayılan dil | Türkçe veya İngilizce; ziyaretçiler değiştirebilir |
| Üst duyuru, alt bilgi notu | Sitenin genelinde kısa metin |
| Discord davet bağlantısı | `https://` ile başlayan davet bağlantısı |
| Mağaza, haber, destek ve wiki modülleri | Sitenin her bölümünü ayrı açıp kapatır |
| Yeni üyelikler, e-posta doğrulaması | E-posta çalışınca e-posta doğrulamasını açık tutun |
| Yönetim için iki aşamalı doğrulama | Varsayılan olarak açık; açık tutun |
| HTTPS zorunluluğu (HSTS) | Yalnızca alan adında ve bütün alt alan adlarında HTTPS çalıştıktan sonra açın |
| Bakiye adı | Site para biriminizin adı, örneğin "Kredi" |
| Son alışverişleri göster, oyuncular birbirine hediye gönderebilir | Mağaza seçenekleri, bkz. [Mağaza](Magaza.md#ayarlardaki-mağaza-seçenekleri) |

## 4. Oyun sunucularınızı ekleyin

**Yönetim → Sunucular → Yeni**: ad, Java adresi, isteğe bağlı Bedrock adresi ve portu, durum sorgusu adresi
ve portu (genelde aynı adres ve `25565`). `/servers` sayfasında listelemek için **Sitede göster**
seçeneğini işaretleyin; oyuncu sayısı cron ile iki dakikada bir yenilenir.

Satın alımları oyunda teslim etmek için sıradaki adım eklentidir: [Paper Eklentisi](Paper-Eklentisi.md).

## 5. Tema seçin

**Yönetim → Temalar**: Atlas, Forge, Volt, Nomad veya Mono'yu seçin; renkleri, ana sayfa bölümlerini ve
menüleri **Tema ayarları** ve **Menüler** sayfalarında düzenleyin. Ayrıntılar:
[Görünüm ve Temalar](Gorunum-ve-Temalar.md).

## 6. Hukuki belgelerinizi yayımlayın

Satışa başlamadan önce kullanım, gizlilik ve satış belgelerinizi **Yönetim → Hukuki belgeler** bölümünde
yayımlayın. Örnek metinler hukuki tavsiye değildir; belgelerde gerçek işletmenizin bilgileri yer almalıdır.
Ayrıntılar [docs/LEGAL.md](../LEGAL.md) dosyasında.

## 7. Mağazayı ve ödemeleri kurun

Kategori ve ürün oluşturun, konsol komutlarını bağlayın ve bir ödeme sağlayıcısı bağlayın:
[Mağaza](Magaza.md) ve [Ödemeler](Odemeler.md).

## 8. Yetkililerinizi ekleyin

Yetkililer oyuncular gibi kayıt olur. **Yönetim → Kullanıcılar** bölümünden rol verin; bkz.
[Roller ve Yetkiler](Roller-ve-Yetkiler.md). Her yetkili, yönetim paneline girebilmek için iki aşamalı
doğrulamayı açmalıdır.

## Gerçek ödeme almadan önce

Gerçek hostinginizde [docs/RELEASE_CHECKLIST.md](../RELEASE_CHECKLIST.md) listesini tamamlayın: ödeme
sağlayıcısının test modunda bir satın alma, sunucunuzda bir teslimat, bir iade ve yedekten geri yükleme.
