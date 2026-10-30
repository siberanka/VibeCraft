# Sipariş incelemesi ve özgün kopyalar

Sepet ve bakiye yükleme, sipariş oluşturulmadan önce ayrı inceleme ekranına gider.
Sepet kuponu burada sunucu tarafından hesaplanır; ürünler, teslimat hesabı, ödeme
yöntemi ve ödenecek son tutar gösterilir. Şartlar kutusu başlangıçta boş olur.
Toplam tutar onay düğmesinin yanında tekrar gösterilir. İnceleme ekranını açmak
tek başına sipariş veya kabul kaydı oluşturmaz.

Onay; sunucu hesabına, oturuma, ürün ve teslimat tanımlarına, tutara ve gösterilen
belge sürümlerine bağlıdır. Bu bilgiler değiştiğinde yeni inceleme gerekir.
Sipariş, hukuki kayıtlar, kupon/stok rezervasyonu ve cüzdan hareketi aynı veritabanı
işlemi içinde yürütülür. Aynı onayın tekrar gönderilmesi ikinci sipariş veya ikinci
cüzdan tahsilatı oluşturmaz.

## Özgün kayıt

Sipariş kopyası, onay anındaki ürün açıklamalarını, fiyatı, satıcı bilgilerini ve
altı belge metnini saklar. Hesap sahibi sipariş ekranından kopyayı okuyabilir veya
metin dosyası indirebilir. Sonraki ürün, dil, satıcı veya belge düzenlemeleri bu
dosyayı değiştirmez. Personel, başka hesabın kişisel kopyasına müşteri bağlantısı
üzerinden erişemez. Bu kayıt vergi faturası veya nitelikli elektronik imza değildir.

Oyun komutları ve özgün hedef sunucu kimlikleri ayrı, şifreli teslimat planında
tutulur; müşteri kopyasına eklenmez. Sonraki komut düzenlemeleri ve komut satırı
silinmesi, eski siparişin satın alma, süre sonu veya iade komutunu değiştirmez.
Özgün sunucu bulunamıyorsa veya plan okunamıyorsa işlem incelemeye alınır.
Ürün kaydının silinmesi de mevcut uygulamada otomatik kurtarma yerine inceleme
gerektirir. Yeni katalog tanımı eski sipariş yerine kullanılmaz.

Geçerli bir özgün kaydı olmayan eski sipariş için yeni ödeme başlatılmaz.
Sağlayıcının doğrulanmış geç ödemesi alınırsa ödeme gerçeği kaydedilir; eksik
kayıt yerine sonradan kabul uydurulmaz ve teslimat/bakiye kredisi bekletilir.

## E-posta kuyruğu

Kopya, sipariş anındaki doğrulanmış e-posta adresine gönderilmek üzere kalıcı
kuyruğa yazılır. Adres şifreli saklanır; kuyruk işi yalnızca kayıt kimliği ve
mesaj başvurusunu taşır. Hesabın sonraki adres değişikliği bu alıcıyı değiştirmez.
Ekteki metin, indirilen özgün dosyayla aynıdır.

Mevcut zamanlayıcı aşağıdaki komutu her dakika çalıştırır. Tek başına da
çalıştırılabilir; veritabanı kuyruğu işçisinin çalışması gerekir:

```sh
php artisan vibecraft:contract-notices --limit=50
```

| Durum | Anlamı |
|---|---|
| `pending` | Gönderilmeyi bekleyen özgün kopya |
| `sending` | Bir işçinin aldığı gönderim |
| `sent` | E-posta taşıyıcısının gönderiyi kabul ettiği kayıt |
| `manual_review` | Kabul sonucu belirsiz veya işçi yarıda kesilmiş; otomatik tekrar gönderilmez |

`sent`, iletinin alıcının gelen kutusuna ulaştığını göstermez. Belirsiz SMTP
sonucu otomatik tekrar gönderimle gizlenmez. Kuyruk kesintisinden kalan bekleyen
kayıtlar yeniden kuyruğa alınabilir; belirsiz gönderiler ayrıca incelenmelidir.
Müşterinin indirme bağlantısı kullanılabilir kalır. Yönetim panelinden belirsiz
gönderiyi çözme/yeniden gönderme iş akışı henüz tamamlanmamıştır.

## Kabul sınırı

Ürünün hukuki niteliği, erken ifa/cayma hakkı, gereken gerçek vergi ve fatura
bilgileri, amaç bazlı izinler, yaş/veli işlemleri ve saklama süreleri ayrı kabul
koşullarıdır. Bu altyapı bütün ülke mevzuatına otomatik uyum veya tamamlanmış
ticaret modülü beyanı değildir. Gerçek ödeme sağlayıcısı ve hedef oyun sunucusu
kabulü ayrıca yapılmalıdır. Güncel ürün kapsamı [durum kaydındadır](STATUS.md).
