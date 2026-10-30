# Ödemeler

VibeCraft kartla ödemeleri **Stripe** veya **PayTR** üzerinden alır, oyuncu bakiyelerini kendi cüzdanında
tutar. Bir ödeme ancak sağlayıcının imzalı bildirimi (webhook) geldiğinde sayılır; ödeme sayfasından siteye
geri dönmek siparişi tek başına ödenmiş yapmaz.

> English: [Payments](Payments.md)

Ödeme sırları **Yönetim → Entegrasyonlar** bölümüne girilir; bu sayfayı yalnızca site sahibi (veya
*entegrasyon sırları* yetkisi olan bir rol) açabilir. Kaydedilen sırlar şifreli saklanır ve bir daha
gösterilmez; mevcut değeri korumak için sır alanını boş bırakın.

## Stripe

1. Stripe Dashboard'da API anahtarlarını oluşturun (önce **test modunu** kullanın).
2. **Developers → Webhooks → Add endpoint**:
   - Adres: `https://alanadiniz/api/payments/stripe/webhook`
   - Olaylar: `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
     `checkout.session.async_payment_failed`, `checkout.session.expired`, `charge.refunded`,
     `charge.dispute.created`
3. Uç noktanın **imzalama sırrını** (`whsec_…`) kopyalayın.
4. **Yönetim → Entegrasyonlar** bölümünde publishable key, secret key ve webhook imzalama sırrını girip
   Stripe'ı etkinleştirin.

Oyuncular Stripe Checkout sayfasında öder. Stripe Dashboard'dan yapılan iade (`charge.refunded`) ve itiraz
(`charge.dispute.created`) siparişe kendiliğinden yansır.

## PayTR

1. PayTR mağaza panelinde **Mağaza No**, **Mağaza Parola** (Merchant Key) ve **Mağaza Gizli Anahtar**
   (Merchant Salt) değerlerini bulun.
2. **Bildirim URL** değerini `https://alanadiniz/api/payments/paytr/webhook` yapın.
3. **Yönetim → Entegrasyonlar** bölümünde bu üç değeri girin, ilk denemeler için **PayTR test modunu**
   açık tutun ve PayTR'ı etkinleştirin.

Ödeme formu PayTR'ın güvenli iframe'inde açılır. Taksit kapalıdır. Test modunu ancak başarılı bir deneme
satın almasından sonra kapatın.

## Gerçek para olmadan deneme

Gerçek sitenizde sağlayıcıların kendi test modlarını kullanın: Stripe test anahtarları ve Stripe test
kartları, PayTR'da test modu açık. Yerleşik **sandbox ödeme yöntemi** yalnızca yerel geliştirme içindir;
canlı sitede kullanılamaz.

## Bakiye yükleme

Oyuncular bakiyelerini **Hesap → Bakiye yükle** sayfasından serbest bir tutarla veya bir
[kredi paketiyle](Magaza.md#cüzdan-ve-kredi) yükler. Yükleme bir sipariş gibi ödenir ve webhook onaylayınca
hesaba geçer. Bakiye daha sonra mağaza siparişlerinde ve
[otomatik yenilemelerde](Magaza.md#süreli-rütbeler-ve-otomatik-yenileme) kullanılır.

## Bildirim geç gelirse veya başarısız olursa

Sağlayıcı webhook'ları kendisi tekrar dener; VibeCraft da işlenemeyen doğrulanmış bildirimleri cron ile beş
dakikada bir yeniden işler. Ödemesi onaylanmayan sipariş *beklemede* kalır ve 60 dakika içinde ödenmezse
kendiliğinden iptal edilir.

Sağlayıcıda ödenmiş ama sitede hâlâ beklemede görünen bir sipariş varsa şunları kontrol edin:

- webhook adresi yukarıdakiyle birebir aynı ve `https://` ile başlıyor,
- **Yönetim → Entegrasyonlar** bölümündeki imzalama sırrı / gizli anahtar sağlayıcıdakiyle aynı,
- cron dakikada bir çalışıyor ([Kurulum](Kurulum.md#6-cron-işini-ekleyin)),
- bir güvenlik duvarı, Cloudflare kuralı veya bot koruması webhook adresini engellemiyor
  ([Güvenlik](Guvenlik.md#cloudflare)).

## İadeler

| Ödeme yöntemi | İade nasıl yapılır |
|---|---|
| Site kredisi (cüzdan) | **Yönetim → Siparişler** → siparişi açın → gerekçe yazarak iade edin. *İade* yetkisi gerekir. Kredi oyuncunun cüzdanına geri döner. |
| Stripe | Stripe Dashboard'dan iade edin. `charge.refunded` bildirimi siparişi kendiliğinden günceller. |
| PayTR | PayTR panelinden iade edin. PayTR iade bildirimi göndermez; sitedeki sipariş değişmez. Oyun içindeki ürünleri elle geri alın ve siparişe not düşün. |

Sitede kaydedilen bir iade:

- ürünün **İade** komutlarını oyun sunucusunda çalıştırır,
- o siparişle alınan rütbelerin otomatik yenilemesini durdurur,
- geri alma kaydını cüzdan defterine ve denetim günlüğüne yazar.

## Cüzdan defteri

Her bakiye değişikliği benzersiz anahtarlı bir defter kaydıdır; tekrarlanan bir bildirim veya çift
tıklama parayı asla iki kez ekleyemez veya düşemez. Her gün 04:10'da çalışan bir iş, bakiyelerin defterle
uyuştuğunu kontrol eder.
