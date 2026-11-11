# Görünüm ve Temalar

Sitenizin görünümünü kod yazmadan değiştirin. Her değişiklik hemen uygulanır ve denetim günlüğüne yazılır.

> English: [Appearance and Themes](Appearance-and-Themes.md)

## Temalar

**Yönetim → Görünüm → Temalar**, on hazır temayı önizleme görseli, güncel renkleri ve açık/koyu bilgisiyle
kartlar halinde gösterir. Tema değiştirmek içeriği, hesapları ve siparişleri korur.

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

## Küçük ayrıntılar

Her temanın kendine özgü birkaç ayrıntısı vardır; her biri tek bir yere ve tek bir işe bağlıdır:

| Nerede | Ne yapar |
|---|---|
| Ana sayfa bölüm başlıkları | Sunucular, haberler ve mağaza başlıklarının her biri temanın tarzında çizilmiş kendi küçük işaretini alır |
| Çevrimiçi sunucular | Oyuncu sayısının yanında "canlı" işareti: işaret feneri, kor, kalp, yıldız imi vb. |
| IP kopyalama düğmesi | Adres kopyalanınca temaya özgü kısa bir tepki |
| Fiyatlar, tarihler, hata sayfaları | Temanın dünyasından bir nesne gibi biçimlenir (para, fiyat etiketi, köşeli ayraçlı tarih, etiket rakamlar) |
| Sayfanın üstü | Kaydırma animasyonlarını destekleyen tarayıcılarda ince bir okuma ilerleme çizgisi |

Bir de gizli kutlama vardır: herhangi bir sayfada ↑ ↑ ↓ ↓ ← → ← → B A tuşlarına basın ya da ana sayfada site
adına hızlıca beş kez basın. Her tema birkaç saniyeliğine farklı karşılık verir (paralar, kayan yıldızlar,
rakam yağmuru, yuvarlanan bloklar…). Süslemelerin tamamı ekran okuyuculardan gizlidir ve cihazında "hareketi
azalt" seçeneğini açan ziyaretçiler için kapalıdır. Kutlamayı kapatmak için **Özel CSS** bölümüne
`.theme-egg { display: none; }` ekleyin.

## İmleç efektleri

Her temanın fareyi izleyen ve tıklamalara karşılık veren kendine özgü canlı bir efekti vardır. Telefon ve
tabletlerde bunun yerine dokunmalara karşılık verir.

| Tema | Efekt | Tıklayınca ya da dokununca |
|---|---|---|
| Atlas | Dalgalar: yoldan küçük ışık zerreleri yükselir | Su gibi halkalar yayılır |
| Forge | Korlar yukarı süzülür | Örsten çıkar gibi kıvılcımlar saçılır |
| Volt | Yol boyunca şimşek çakar | Her yöne şimşekler |
| Nomad | Sağlı sollu ayak izleri | Bir avuç kum havalanır |
| Mono | İmleci sessiz bir halka izler, bağlantıların üzerinde açılır | Küçük bir artı damgası |
| Prism | Işık pembe, beyaz ve mavi olarak ayrışır | Cam kırıkları |
| Arcade | Izgaraya oturan pikseller | Paralar zıplayıp düşer |
| Terminal | Metin ızgarasında karakterler | Yanıp sönen blok imleç ve tarama çizgisi |
| Brick | Yoldan oyuncak bloklar yuvarlanır | Bir avuç blok havaya fırlar |
| Dusk | Pembeden camgöbeğine dönen neon şerit | Neon halka ve birkaç yıldız |

Efekt farenin ne yaptığına göre davranır:

- Bağlantı ve düğmelerin üzerinde parlaklaşır; kullanılamayan bir şeye tıklanınca küçük, soluk bir tepki verir.
- Form alanlarının üzerinde, metin seçilirken, fare durduğunda ya da pencereden çıktığında durur.
- Normal fare imleci hiç değiştirilmez; metin, bağlantı, bekleme ve "izin yok" imleçleri çalışmaya devam eder.
- Dokunmatik ekranlarda yalnızca kısa ve hareketsiz dokunuşlar efekti tetikler; kaydırma ve uzun basma tetiklemez.
- Cihazında "hareketi azalt" seçeneğini açan ziyaretçiler efekti hiç görmez; yönetim panelinde hiç çalışmaz.

**Yönetim → Görünüm → Tema ayarları → İmleç efektleri** her tema için ayrı ayarlanır:

| Ayar | Seçenekler |
|---|---|
| Efekt | Temanın kendi efekti, diğer dokuzundan biri ya da **Kapalı** |
| Yoğunluk | Hafif, Normal ya da Canlı (iz sayısı ve patlamaların büyüklüğü) |
| Telefon ve tabletlerde dokunma efekti | Açık ya da kapalı |

Kaydetmeden önce aynı sayfadaki önizleme alanında seçiminizi deneyin. Efekt temanın renklerini kullanır;
başka bir palet seçmek efekti de değiştirir.

## Tema renkleri

**Yönetim → Görünüm → Tema renkleri** (ya da tema kartındaki **Renkler**) iki bölümden oluşur:

- **Hazır paletler**: her tema kendisi için hazırlanmış birkaç paletle gelir. Tek tıkla palet uygulanır;
  **Tema varsayılanı** özgün renkleri geri getirir. Kullanılan palet işaretlidir.
- **Kendi renklerin**: varsayılandan ya da hazır bir paletten başlayarak her rengi (arka planlar, metin,
  vurgu, odak, kenarlıklar) ve köşe yuvarlaklığını kendiniz değiştirin.

Kaydederken yazı renkleri denetlenir: okunması zor bir birleşim, iki rengin adını veren bir iletiyle
reddedilir. Her tema kendi renklerini saklar; birkaç temayı hazırlayıp aralarında geçiş yapabilirsiniz.

## Ana sayfa görseli

**Yönetim → Görünüm → Tema ayarları** → **Ana sayfa görseli**, her temanın ana sayfasındaki görseli belirler:

| Seçim | Sonuç |
|---|---|
| Tema görseli | Temayla gelen özgün görsel |
| Kendi görselim | Yüklediklerinizden ya da görsel kütüphanesinden bir görsel; en az 1600 piksel genişliğinde geniş bir görsel en iyi sonucu verir |
| Görsel yok | Ana sayfa görselsiz gösterilir |

**Tema görseli** yeniden seçilerek özgün görsele her zaman dönülür. Seçim tema başına saklanır.
Ayarlar'daki **Ana sayfa görseli**, "Tema görseli" seçili kalan bütün temalarda kullanılmaya devam eder;
böylece tek bir görsel bütün temaları kapsayabilir. Ana sayfa görseli olarak kullanılan bir görsel, görsel
kütüphanesinde "kullanımda" görünür ve yanlışlıkla silinemez.

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
