# Görünüm ve Temalar

Sitenizin görünümünü kod yazmadan değiştirin. Her değişiklik hemen uygulanır ve denetim günlüğüne yazılır.

> English: [Appearance and Themes](Appearance-and-Themes.md)

## Temalar

**Yönetim → Temalar** on hazır temayı gösterir. Tema değiştirmek içeriği, hesapları ve siparişleri korur.

| Tema | Kimin için |
|---|---|
| **Atlas** | Çok oyun modlu ağlar (network) |
| **Forge** | Survival ve ekonomi sunucuları |
| **Volt** | Rekabetçi ve arena sunucuları |
| **Nomad** | Macera ve RPG sunucuları |
| **Mono** | Sade siteler ve içerik üreticileri |
| **Prism** | Modern sıvı cam görünümü: kuzey ışıklı gece üzerinde yarı saydam paneller ve imleci izleyen ışık |
| **Arcade** | Retro piksel sunucular: piksel yazı, kabartmalı menü pencereleri ve basılan düğmeler |
| **Terminal** | Yeşil fosforlu konsol: eş aralıklı yazı, komut satırı tarzı başlıklar ve tarama çizgileri |
| **Brick** | Aydınlık ve samimi açık tema: kalın çerçeveler, sert gölgeler ve düz renkli bloklar |
| **Dusk** | Seksenler neon gün batımı: bantlı gökyüzü, parlayan çerçeveler ve ufka uzanan ızgara |

Her temanın kendi yerleşimi, yazı tipleri ve özgün görselleri vardır; seçmeden önce kendi içeriğinizle
deneyin. Mono ve Brick açık renkli, diğerleri koyu temalardır. Prism'in cam efekti arka plan bulanıklığı
kullanır; eski tarayıcılarda paneller düz renkli görünür. Bütün temaların ekran görüntüleri [README](../../README.md#screenshots) dosyasında.

## Tema renkleri

**Yönetim → Temalar → Tema renkleri**, etkin temanın renk değerlerini (arka planlar, metin, vurgu,
düğmeler ve kenarlıklar) ve köşe yuvarlaklığını değiştirir. **Tema varsayılanlarına dön** özgün paleti
geri getirir. Yeterli kontrastı olan renkler seçin; metin ve odak görünümü bunlara bağlıdır.

## Ana sayfa bölümleri

**Tema ayarları → Ana sayfa bölümleri** altında her bölümün sırasını ve görünüp görünmeyeceğini seçin:

| Bölüm | İçerik |
|---|---|
| Durum şeridi | Çevrimiçi oyuncular ve sunuculara, mağazaya, desteğe ve Discord'a hızlı bağlantılar |
| Sunucular | Canlı oyuncu sayılarıyla oyun sunucularınız |
| Son haberler | En yeni haberler |
| Son alışverişler | Son mağaza satın alımları (yalnızca Ayarlar'da açıksa) |
| Mağaza | Öne çıkan ürünler |

Aynı sayfada hazır menü öğelerini ana menüden gizleyebilirsiniz: sunucular, mağaza ve sepet, haberler,
destek, sandık ve bakiye yükleme, topluluk menüsü.

## Menüler

**Yönetim → Menüler**, **Üst menü** ve **Alt menü** bağlantılarını düzenler. Her bağlantının bir adı, bir
adresi (`/` ile başlayan site yolu veya `https://` adresi), sırası, **Görünür** ve **Yeni sekmede aç**
seçenekleri vardır. Discord'unuzu, kurallar sayfanızı, oy sayfanızı veya haritanızı eklemek için kullanın.

## Özel CSS

**Yönetim → Özel CSS**, kendi CSS'inizi bütün temalarda kamuya açık siteye ekler. Yönetim panelinde hiç
yüklenmez; bir hata sizi panelin dışında bırakamaz.

- CSS kaydedilmeden önce denetlenir. Görseller yalnızca `/media/` veya `/assets/` adreslerinden gelebilir;
  dış adresler, import ve betikler reddedilir.
- **Hazır parçalar** sık kullanılan Minecraft dokunuşlarını tek tıkla ekler: blok gölgeli başlık, hotbar
  menü, envanter yuvası görselleri ve piksel köşeli kartlar.

## Görsel kütüphanesi

Görsel seçici, görsel gereken her yerde açılır (logo, karşılama görseli, kategoriler, ürünler, haber
kapakları ve daha fazlası). Üç bölümü vardır:

- **Hazır görseller**: 14 pakette 142 özgün piksel sanat ikon; sitenizde serbestçe kullanılabilir: rütbeler
  ve boş etiketler, spawner'lar, ekonomi, anahtarlar, kasalar, zırhlar, kılıçlar, baltalar, kazmalar,
  aletler, spawn yumurtaları, iksirler ve daha fazlası. Bunlar silinemez.
- **Yüklediklerim**: sizin yüklediğiniz bütün görseller.
- **Yeni resim yükle**: JPEG, PNG veya WebP; en fazla 4 MB ve 4 milyon piksel. Ekran okuyucu kullananlar
  için kısa bir açıklama ekleyin.

**Yönetim → Görsel kütüphanesi** yüklemelerinizi listeler. Hiçbir yerde kullanılmayan bir görsel
silinebilir; hâlâ kullanılıyorsa panel nerede kullanıldığını gösterir ve önce değiştirmenizi ister.

Yüklenen görseller herkese açık dosyalardır. Özel belge yüklemeyin.

## Logo ve karşılama görseli

**Yönetim → Ayarlar** bölümünden ayarlanır. Logo yoksa tema, site adınız ve sloganınızla bir blok simgesi
gösterir. İyi bir karşılama görseli sunucunuzun geniş bir ekran görüntüsüdür (en az 1600 piksel genişlik).

## Site simgesi (favicon)

Tarayıcı sekmesindeki simge `public/favicon.ico` dosyasıdır. Dosya Yöneticisi'nde kendi simgenizle
değiştirin.

## Diller

Site, yönetim paneli ve e-postalar Türkçe ve İngilizce gelir. Ziyaretçiler dil düğmesiyle dil değiştirir;
varsayılan dil **Ayarlar → Varsayılan dil** ile belirlenir. Wiki yazıları ve hukuki belgeler her dil için
ayrı yazılır.
