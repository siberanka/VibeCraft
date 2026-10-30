# Mağaza

Rütbe, anahtar ve eşya satmak ve bunları sunucunuzda teslim etmek.

> English: [Store](Store.md)

## Satın alma nasıl işler

1. Oyuncu ürünleri sepete ekler, son fiyatı inceler ve satış şartlarınızı kabul eder.
2. Kartla (Stripe veya PayTR) ya da cüzdanındaki site kredisiyle öder.
3. Ödeme sağlayıcısı ödemeyi onaylayınca sipariş ödenmiş olur ve bir **teslimat** sıraya alınır.
4. Oyun sunucunuzdaki [Paper eklentisi](Paper-Eklentisi.md) teslimatı alır ve ürünün konsol komutlarını
   çalıştırır. Sandık teslimatında ürün, oyuncu alana kadar **Sandık** sayfasında bekler.

Ödenmeyen siparişler 60 dakika sonra kendiliğinden iptal edilir.

## Fiyat ve para birimi

Bütün tutarlar **kuruş cinsinden** girilir: `2500` = 25,00. Para birimi `.env` dosyasındaki
`VIBECRAFT_CURRENCY` ile belirlenir (varsayılan `TRY`). İlk satıştan önce karar verin, sonra değiştirmeyin.

## Kategoriler

**Yönetim → Kategoriler**: ad, açıklama, görsel ve sıra (küçük sayı önce gelir). Her ürün bir kategoriye
bağlıdır.

## Ürünler

**Yönetim → Ürünler → Yeni**:

| Alan | Anlamı |
|---|---|
| Kategori, ad, özet, görsel | Oyuncuların mağazada gördükleri |
| Kuruş cinsinden fiyat | `1500` = 15,00 |
| Stok | Sınırsız için boş bırakın |
| En fazla adet | Bir sipariş satırındaki en yüksek adet |
| Hesap başına sınır | Bir hesabın en fazla kaç kez alabileceği (boş = sınırsız) |
| Gün cinsinden süre | Aylık rütbe gibi süreli ürünlerde doldurun; kalıcı ürünlerde boş bırakın |
| Doğrulanmış Minecraft hesabı gerekli | Alıcı önce oyun hesabını bağlamış olmalı (rütbeler için önerilir) |
| Sandık üzerinden teslim | Ürün hemen teslim edilmez; oyuncu daha sonra `/chest` sayfasından alır |
| Görünür | Gizli ürünler satın alınamaz |

## Ürün komutları

Komut bağlanmayan bir ürün oyunda hiçbir şey yapmaz. **Yönetim → Ürün komutları → Yeni**:

| Alan | Anlamı |
|---|---|
| Ürün, sunucu | Hangi ürün ve komutun hangi oyun sunucusunda çalışacağı |
| Aşama | **Satın alma** ödemeden sonra, **Süre sonu** süreli ürün bitince, **İade** sipariş iade edilince çalışır |
| Konsol komutu | Baştaki `/` olmadan |
| Oyuncu çevrimiçi olmalı | Komut, oyuncu oyuna girene kadar bekler |
| Sıra | Aynı aşamadaki birden çok komutun sırası |

Yer tutucular:

| Yer tutucu | Yerine gelen |
|---|---|
| `{player}` | Oyuncunun doğrulanmış Minecraft adı |
| `{uuid}` | Oyuncunun tireli UUID'si |
| `{uuid_nodash}` | Tiresiz UUID |
| `{quantity}` | Alınan adet |
| `{order}` | Sipariş numarası |
| `{days}` | Ürün süresi (gün) |

Komutlara yalnızca bu türü belli değerler girebilir; ürün adları, notlar veya oyuncuların yazdığı başka
hiçbir şey komuta ulaşamaz. Örnekler:

```
lp user {uuid} parent addtemp vip {days}d
crates key give {player} legendary {quantity}
lp user {uuid} parent remove vip
```

UUID kullanan komutları tercih edin. Her komutun ilk kelimesine ("kök", örneğin `lp` veya `crates`)
eklentinin `allowed-command-roots` listesinde de izin verilmelidir; aksi halde teslimat incelemeye
alınır. Bkz. [Paper Eklentisi](Paper-Eklentisi.md#izinli-komutlar).

## Süreli rütbeler ve otomatik yenileme

**Gün cinsinden süre** girilmiş bir ürün süreli bir hak oluşturur. Süre bitince **Süre sonu** komutları
çalışır.

Oyuncular etkin bir süreli rütbe için hesaplarından **otomatik yenileme** açabilir. Rütbe bitiş tarihine
geldiğinde, süre bitimi çalışmadan önce ücret cüzdanından alınır ve rütbe uzatılır; satın alma komutu
yeniden gönderilmez. Bakiye yetmezse yenileme kapanır ve normal süre bitimi çalışır. Askıya alınmış
hesapların yenilemeleri ücret alınmadan durdurulur.

## Cüzdan ve kredi

Oyuncular site para biriminizde bakiye tutar (adı Ayarlar'daki **Bakiye adı** ile belirlenir).

- **Kredi paketleri** (**Yönetim → Kredi paketleri**): fiyat ve üstüne eklenen isteğe bağlı **bonus**;
  örneğin 100,00 öde, 110,00 kredi al.
- **Kredi kodları** (**Yönetim → Kredi kodları**): sabit tutarlı kodlar üretin; adet, isteğe bağlı en fazla
  kullanım sayısı, son kullanma tarihi ve size özel not girilebilir. Oyuncular kodu **Hesap → Kod Bozdur**
  bölümünde kullanır.
- *Cüzdan düzeltmesi* yetkisi olan personel bakiyeyi düzeltebilir; her değişiklik deftere ve denetim
  günlüğüne yazılır.

## Kuponlar

**Yönetim → Kuponlar**:

| Alan | Anlamı |
|---|---|
| Kod | Büyük harf, rakam, `_` veya `-` |
| Tür | **Yüzde** (`100` = %1, yani `1000` = %10) veya **Sabit** (kuruş) |
| En düşük ara toplam | Kuponun geçerli olması için gereken sepet tutarı |
| En fazla kullanım, hesap başına kullanım | Genel ve oyuncu başına sınırlar |
| Başlangıç, bitiş, etkin | Geçerlilik aralığı |

## Hediyeler

Ayarlar'da **Oyuncular birbirine hediye gönderebilir** açıksa oyuncu ödeme sırasında başka bir oyuncunun site kullanıcı adını yazabilir.
Alıcı, Minecraft hesabını bağlamış etkin bir hesap olmalıdır; teslimat o oyuncuya yapılır, ödemeyi yapan
siparişin sahibi olarak kalır.

## Siparişler ve teslimatlar

- **Yönetim → Siparişler**: ödeme ve teslimat durumuyla bütün siparişler. Personel ödenmemiş siparişleri
  iptal edebilir; finans yetkisiyle site kredisiyle ödenmiş siparişleri iade edebilir. Kartla yapılan
  ödemeler sağlayıcıda iade edilir; bkz. [Ödemeler](Odemeler.md). İade, ürünün **İade**
  komutlarını çalıştırır.
- **Yönetim → Teslimatlar**: teslimat kuyruğu. Eklentinin doğrulayamadığı bir teslimat **belirsiz** olur
  ve birinin oyun sunucusunu kontrol edip karar vermesini bekler; bkz.
  [Paper Eklentisi](Paper-Eklentisi.md#belirsiz-teslimatlar).

Her sipariş, satın alma anındaki ürün, fiyat ve kabul edilen belgelerin bir kopyasını saklar; sonradan
yapılan düzenlemeler oyuncunun aldığını değiştirmez. Oyuncular bu kopyayı sipariş sayfasından indirebilir.

## Ayarlar'daki mağaza seçenekleri

- **Son alışverişleri göster**: ana sayfada son satın alımların listesi (oyuncu adı, ürün ve zaman; fiyat
  gösterilmez).
- **Hediyeler**: başka oyuncu adına satın almaya izin verir.
- **Bakiye adı**: site para biriminin adı.
