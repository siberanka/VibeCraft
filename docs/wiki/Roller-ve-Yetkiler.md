# Roller ve Yetkiler

Her hesabın tek bir rolü vardır. Personel rolleri yönetim panelini açar; panelde kimin neler yapabileceğini
rolün yetkileri belirler.

> English: [Roles and Permissions](Roles-and-Permissions.md)

## Hazır roller

| Rol | Kimin için | Yapabildikleri |
|---|---|---|
| **owner** (sahip) | Siz ve güvendiğiniz ortaklar | Roller, ödeme sırları ve bakiye düzeltmeleri dahil her şey |
| **admin** | Üst düzey yetkililer | Mağaza, kuponlar, siparişler ve iadeler, teslimatlar, sunucular, içerik, destek, ayarlar, görünüm, kullanıcılar ve yasaklar, forum, başvurular, denetim günlüğü, sistem sağlığı |
| **moderator** | Moderatörler | Kullanıcılar ve yasaklar, içerik, destek, forum moderasyonu, yetkili başvuruları |
| **support** | Destek ekibi | Kullanıcılar, siparişler (yalnızca görüntüleme) ve destek talepleri |
| **member** | Oyuncular | Yönetim paneline erişim yok |

Rolleri yalnızca bir **sahip** değiştirebilir: **Yönetim → Kullanıcılar** → kullanıcıyı açın →
**Hesap rolü**. Son sahip hiçbir zaman kaldırılamaz; yanlışlıkla kendinizi dışarıda bırakamazsınız.

## Yetki listesi

| Yetki | İzin verdiği |
|---|---|
| `admin.access` | Yönetim panelini açmak |
| `users.view` / `users.manage` / `users.ban` | Hesapları görmek / düzenlemek ve rozet vermek / yasaklamak ve yasağı kaldırmak |
| `roles.manage` | Rol değiştirmek (yalnızca sahip) |
| `store.manage` | Kategoriler, ürünler, ürün komutları, kredi paketleri |
| `coupons.manage` | Kuponlar |
| `orders.view` / `orders.manage` | Siparişleri görmek / iptal etmek ve not eklemek |
| `finance.refund` | Site kredisiyle ödenmiş siparişleri iade etmek |
| `finance.wallet_adjust` | Oyuncu bakiyesini düzeltmek (yalnızca sahip) |
| `deliveries.manage` | Teslimatları yeniden denemek veya iptal etmek |
| `servers.manage` | Oyun sunucuları ve köprü anahtarları |
| `content.manage` | Haberler, sayfalar, wiki, oy siteleri, görsel kütüphanesi |
| `tickets.view` / `tickets.reply` | Destek taleplerini okumak / yanıtlamak |
| `settings.manage` | Site ayarları ve hukuki belgeler |
| `appearance.manage` | Temalar, renkler, menüler, özel CSS |
| `integrations.manage` | Ödeme, CAPTCHA ve Discord sırları (yalnızca sahip) |
| `forum.moderate` | Forum kategorileri ve moderasyon |
| `applications.review` | Yetkili pozisyonları ve başvurular |
| `audit.view` | Denetim günlüğü |
| `system.view` | Sistem sağlığı bilgileri |

## Personel güvenliği

- Her personel rolü için yönetim paneli açılmadan önce **iki aşamalı doğrulama** zorunludur. Açık tutun
  (**Ayarlar → Yönetim için iki aşamalı doğrulama**).
- Yönetim paneli hassas bölümlerden önce şifreyi yeniden sorar (en fazla birkaç saatte bir).
- Yönetim panelindeki her değişiklik, personeli, zamanı ve değişen alanların adlarıyla (sır değerleri hiçbir
  zaman) **denetim günlüğüne** yazılır.
- Rol değişiklikleri ve yasaklar son sahibin kaldırılmasına karşı korumalıdır.

## Sahip erişimini kaybetmek

Tek sahip erişimini kaybederse, Dosya Yöneticisi ve cron (veya SSH) erişimi olan biri erişimi geri
verebilir:

```
php artisan vibecraft:grant-owner sahip@ornek.com
```

Bu komut, var olan o hesaba sahip rolünü verir. İki aşamalı doğrulamayı kapatmaz; bunun için hesabın
kurtarma kodlarını kullanın. Hosting girişinizi koruyun: konsol erişimi olan herkes bu komutu çalıştırabilir.

## Yasaklar

`users.ban` yetkisi olan personel kullanıcı sayfasında gerekçe yazarak bir hesabı yasaklar. Yasaklı hesap
giriş yapamaz, otomatik yenilemeleri durur ve hediye alamaz. Oyun içi yasaklar ayrıdır; sunucunuzdan gelir ve
cezalar sayfasında görünür.
