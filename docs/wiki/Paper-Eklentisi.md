# Paper Eklentisi

**VibeCraft Bridge**, Minecraft sunucunuzu web sitesine bağlar. Mağaza satın alımlarını teslim eder,
oyuncu hesaplarını bağlar ve sıralama ile cezalar sayfaları için istatistik ve yasakları paylaşabilir.

> English: [Paper Plugin](Paper-Plugin.md)

## Nasıl çalışır

- Eklenti web sitesini HTTPS ile **kendisi çağırır**; site oyun sunucunuza hiç bağlanmaz. RCON veya açık
  port gerekmez.
- Her istek sunucuya özel bir anahtarla imzalanır. Site yalnızca o sunucuya ait teslimatları verir.
- Eklenti bir komutu çalıştırmadan önce yerel bir kayda (`receipts.vcj`) yazar, sonucu daha sonra siteye
  bildirir. Çalıştırılmış bir teslimat, çökme veya bağlantı kopması sonrasında bile tekrar çalışmaz.

## Desteklenen sunucular

| Kurulum | Destek |
|---|---|
| Java 25 üzerinde Paper 26.3, online mode | Evet |
| **Velocity** arkasında modern forwarding ile Paper (online-mode proxy, forwarding secret) | Evet; eklentiyi Velocity'ye değil her Paper sunucusuna kurun |
| **Floodgate** ile gelen Bedrock oyuncular | Evet; Xbox hesaplarıyla (XUID) bağlanırlar |
| Offline mode, eski BungeeCord forwarding, Folia | Hayır; eklenti başlamayı reddeder |

## Kurulum

1. [Sürümler sayfasından](https://gitlab.com/siberanka/vibecraft/-/releases)
   `VibeCraftBridge-<sürüm>.jar` dosyasını indirip `SHA256SUMS` ile doğrulayın.
2. Sunucuyu durdurun, jar'ı `plugins/` klasörüne koyun, sunucuyu bir kez başlatıp tekrar durdurun. Eklenti
   `plugins/VibeCraftBridge/config.yml` dosyasını oluşturur ve **kapalı** kalır.
3. Sitede **Yönetim → Sunucular** bölümünde sunucuyu düzenleyip **Köprü anahtarını yenile** düğmesine
   basın. Anahtar kimliği ve sırrı **yalnızca bir kez** gösterilir; doğrudan config dosyasına kopyalayın.
4. `config.yml` dosyasını düzenleyin:

```yaml
enabled: true
site: 'https://alanadiniz'
key-id: 'siteden alınan anahtar kimliği'
secret: 'siteden alınan sır'
allowed-command-roots: ['lp', 'crates']
share-statistics: false
share-punishments: false
```

- `site` yalnızca HTTPS kökü olmalıdır: yol ve sondaki `/` olmadan.
- `config.yml` dosyasını koruyun: sırrı sohbete, ekran görüntüsüne veya destek mesajına asla yapıştırmayın.

5. Sunucuyu başlatın. **Yönetim → Sunucular** bölümündeki sunucu kartı son köprü bağlantısını gösterir.

## İzinli komutlar

`allowed-command-roots`, sitenin çalıştırabileceği her konsol komutunun ilk kelimesini küçük harfle
listeler. `lp user {uuid} parent add vip` ürün komutu için `lp`, `crates key give ...` için `crates`
gerekir.

- Joker karakter yoktur. Ad alanlı biçimler ayrı satır ister: `minecraft:give` ile `give` farklıdır.
- Yalnızca ürünlerinizin gerçekten kullandığı kökleri ekleyin. İzin verdiğiniz bir kök, siteye onunla
  başlayan *her* komutu çalıştırma imkânı verir; ürün komutlarındaki argümanları sıkı tutun.
- Kökü izinli olmayan bir teslimat hiçbir şey çalışmadan durdurulur ve yönetim panelinde incelemeyi bekler.
  Kökü ekleyip sunucuyu yeniden başlatın, sonra tekrar deneyin.

## Oyuncu hesaplarını bağlama

Rütbeler ve birçok ürün doğrulanmış bir Minecraft hesabı ister.

1. Oyuncu sitede **Hesap** sayfasını açıp **Bağlantı kodu oluştur** düğmesine basar.
2. 10 dakika içinde oyunda `/vclink KOD` yazar.
3. Eklenti oyuncunun gerçek UUID'sini ve adını doğrulanmış oturumdan gönderir; kimse başkasının hesabını
   bağlayamaz.

Oyuncular hesap bağlantısını aynı sayfadan kaldırabilir. Herkes varsayılan olarak `vibecraft.link`
yetkisine sahiptir.

## İstatistikler ve yasaklar

| Seçenek | Etkisi |
|---|---|
| `share-statistics: true` | Çevrimiçi oyuncuların oynama süresini, oyuncu öldürme, ölüm ve yaratık öldürme sayılarını **Sıralama** sayfası için gönderir |
| `share-punishments: true` | Sunucunun profil yasak listesini **Cezalar** sayfası için gönderir |

İkisi de varsayılan olarak kapalıdır.

## Teslimat kuralları

- Komutlar sunucunun ana iş parçacığında, her seferinde bir teslimat olarak çalışır.
- **Oyuncu çevrimiçi olmalı** komutları oyuncu oyuna girene kadar bekler.
- Çevrimiçi bir oyuncunun UUID'si veya adı siparişle artık eşleşmiyorsa teslimat hiçbir şey çalışmadan
  reddedilir.
- Özellikle teslimat anında çevrimdışı olan oyuncular için UUID kullanan komutları tercih edin.
- Süre bitimi ve iade komutları oyuncunun güncel doğrulanmış adını kullanır.

## Belirsiz teslimatlar

Sunucu bir komutu başlattıktan sonra çöktüyse, komut hata verdiyse veya çok uzun sürdüyse eklenti ürünün
verilip verilmediğini bilemez. Teslimat **belirsiz** olur ve kendiliğinden tekrar denenmez.

1. Oyunda (veya ilgili eklentinin kayıtlarında) oyuncunun ürünü alıp almadığını kontrol edin ve siparişe
   bakın.
2. **Yönetim → Teslimatlar** bölümünde teslimatı **Yeniden dene** veya **Vazgeç** ile sonuçlandırın.
   Yeniden denemek güvenlidir: eklenti, kaydında çalıştırılmış görünen bir teslimatı atlar; ürün iki kez
   verilemez.
3. Kayıtta çalıştırılmış görünüyor ama oyuncu ürünü almamışsa, ürünü **yeni** ve ayrı bir teslimat olarak
   verin ve nedenini siparişe not edin.

Konsol komutu çalıştırmak ile dosyaya yazmak hiçbir zaman tek bir adım olamaz; bu yüzden hiçbir sistem
"tam olarak bir kez" teslimat sözü veremez. İki kez çalışsa da sorun çıkarmayan komutlar (örneğin "100
coin ekle" yerine "rütbeyi ayarla") en iyi sonucu verir.

## Kayıt dosyasını koruyun

`plugins/VibeCraftBridge/receipts.vcj`, eklentinin neyi teslim ettiğini hatırladığı dosyadır.

- Yeniden başlatma, güncelleme ve anahtar yenileme sırasında koruyun; sunucu yedeklerine dahil edin.
- Takılan bir teslimatı "açmak" için asla silmeyin; iki sunucunun aynı eklenti klasörünü paylaşmasına
  izin vermeyin.
- Eski bir kayıt dosyasını tek başına geri yüklemek yeni teslimatları tekrar çalıştırabilir. Eşleşen site
  yedeğiyle birlikte geri yükleyin.

## Anahtarı yenileme

Yeni bir makineye geçerken veya sır sızmış olabilirse anahtarı yenileyin: **Yönetim → Sunucular → Köprü
anahtarını yenile**, yeni değerleri `config.yml` dosyasına yapıştırın, yeniden başlatın. Eski anahtar
hemen çalışmaz hale gelir. Kayıt dosyasını sıfırlamayın.

## Daha fazla ayrıntı

- Eklenti README: [bridge/paper/README.md](../../bridge/paper/README.md)
- Haberleşme protokolü: [docs/BRIDGE.md](../BRIDGE.md)
- Jar'ı kendiniz derlemek: [Geliştirme](Gelistirme.md#eklentiyi-derleme)
