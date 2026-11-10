# Değişiklik kaydı

## Geliştirme sürümü — 11 Kasım 2026

- Beş yeni tema eklendi; toplam on tema oldu. **Prism** (sıvı cam: kuzey ışıkları
  üzerinde yarı saydam paneller, imleci izleyen ışık), **Arcade** (retro piksel:
  piksel yazı, kabartmalı pencereler, basılan düğmeler), **Terminal** (komut
  konsolu: tek renk fosfor, eş aralıklı yazı, tarama çizgileri), **Brick** (açık
  renkli, kalın çerçeveli ve sert gölgeli bloklar) ve **Dusk** (neon gün batımı:
  bantlı gökyüzü, ufka uzanan ızgara). Her temanın kendi ana sayfa düzeni, yazı
  tipi ve özgün görseli vardır; mağaza, hesap, destek ve yönetim sayfaları da
  temaya uyar.
- Yeni tema görselleri koddan üretilen özgün piksel sanat ve düz renkli çizimlerdir
  (`tools/themes/build_artwork.py`); üçüncü taraf görsel içermez. Yeni yazı tipleri
  (Pixelify Sans, VT323, Archivo Black, Righteous) SIL OFL 1.1 ile yerel sunulur.
- Tema tanımı artık açık/koyu renk düzenini ve stil dosyasının yüklenme sırasını
  bildirebilir; açık temalarda tarayıcı denetimleri de açık görünür.

## Geliştirme sürümü — 29 Ekim 2026

- Yönetim paneline Görünüm araçları eklendi: tema başına ana sayfa bölümlerinin
  sırası ve görünürlüğü, ana menüdeki hazır öğeleri gizleme, üst ve alt menüye
  özel bağlantılar (site içi yol veya https adresi) ve Özel CSS. Özel CSS
  kaydedilirken denetlenir, ayrı bir stil dosyası olarak sunulur ve yönetim
  paneline uygulanmaz; dış adreslerden içerik yükleyemez.
- Temalara blok ayrıntıları eklendi: seçili menü öğesi envanter yuvası gibi
  çerçevelenir, başlıklar blok derinliğiyle gölgelenir, sunucu doluluğu on hücreli
  çubukla gösterilir ve ürün görselleri envanter yuvasına oturur.

- Bonuslu kredi paketleri eklendi. Paketin bonusu ödeme öncesi incelemede
  gösterilir, ödeme onaylanınca ayrı bir bakiye kaydıyla eklenir ve iadede geri
  alınır; serbest tutarlara bonus uygulanmaz.
- Resim seçici ve hazır ikon kütüphanesi eklendi. Ürün, kategori, haber, logo ve
  ana sayfa görseli alanlarında yüklenen resimler veya 142 özgün piksel ikon
  (rütbe etiketleri, silah ve aletler, zırhlar, anahtar ve kasalar, spawnerlar,
  spawn yumurtaları, ekonomi, iksirler ve diğerleri) seçilebilir. Kullanılmayan
  yüklemeler silinebilir; kullanılan bir resim silinmek istendiğinde nerede
  kullanıldığı gösterilir. Hazır ikonlar silinemez.

- Sepet başka bir oyuncuya hediye edilebilir. Alıcı, Minecraft hesabını bağlamış
  başka bir oyuncu olmalıdır; ürünler onun hesabına teslim edilir, süreli rütbe ve
  sandık eşyaları ona ait olur ve alıcıya bildirim gider. Ödemeyi ve sipariş
  kaydını gönderen oyuncu tutar. Yönetim ayarlarından kapatılabilir.

## Güvenlik sağlamlaştırması — 30 Ekim 2026

- Ödeme: sağlayıcının iade/itiraz bildirimi ödeme onayından önce gelirse artık
  kaybolmaz; onay geldiğinde uygulanır. Ödeme yöntemi kapatılsa da imzası
  doğrulanabilen bildirimler (iade, itiraz, geç onay) işlenmeye devam eder.
- Rütbe yenileme: adetli alınan süreli rütbeler uzattıkları her dönem için
  ücretlendirilir; askıya alınmış hesapların yenilemesi durur. Hediye siparişi
  iade edildiğinde alıcının kendi ödediği yenileme dönemleri bakiyesine döner.
- Teslimat: inceleme bekleyen siparişlerde süre bitimi ve iade komutları artık
  iptal edilmez; oyun sunucusuna gönderilmiş bir komut sessizce iptal edilmek
  yerine incelemeye alınır. Süre bitimi ve iade komutları oyuncunun güncel
  doğrulanmış adını kullanır.
- Köprü: sunucuya giden istekte yalnız imzalı JSON gövdesi kabul edilir (sorgu
  dizesi ve form verisi reddedilir). Eklentide bir teslimatın ön kontrolü
  reddedilirse yalnız o teslimat başarısız bildirilir, diğerleri işlenmeye devam
  eder; istatistik ve ceza paylaşımı artık çalışır.
- Hesap: parola doğrulama, parola değiştirme ve profil güncellemede yanlış parola
  denemeleri sınırlandı. Discord bağlama sayfaları kendi hız sınırını kullanır ve
  gömülü istekle (resim vb.) açılamaz; bildirim sayfası da gömülü istekle okundu
  olarak işaretlenemez. Yanlış durum parametresi bekleyen Discord bağlantısını
  iptal etmez.
- İçerik: Markdown görselleri yalnız yüklenen medya ve hazır varlıklardan
  gösterilir; diğer görseller alternatif metne dönüşür ve kayıtlı içerik yeniden
  işlenir. Yönetim listeleri kullanıcı metnini çeviri anahtarı olarak kullanmaz.
- Alan adı: Host başlığındaki port da doğrulanır ve üretimde e-posta bağlantıları
  her zaman APP_URL üzerinden üretilir. HTTPS adresinde oturum çerezi varsayılan
  olarak Secure işaretlidir.

## Geliştirme sürümü — 28 Ekim 2026

- Beş temanın ana sayfası yeniden tasarlandı: kenardan kenara sahne görseli,
  sunucu adıyla açılan başlık, birincil "IP kopyala" düğmesi ve Discord'a katılma
  eylemi; altında yalnız ölçülmüş oyuncu sayısını gösteren durum şeridi, sunucu
  kartları, büyük görselli haber panosu ve mağaza vitrini. Başlık
  `Ana sayfa başlığı` ayarıyla değiştirilebilir.
- Menüler bütün temalarda oyuncuların bildiği terimlere sadeleştirildi
  (Ana sayfa, Sunucular, Mağaza, Sepet, Haberler, Destek); mağaza menüde vurgulu.
- Mağaza kartları, ürün sayfası, sunucular sayfası ve haber sayfaları yeni görsel
  dile taşındı. Oyuncuya bilgi katmayan açıklama satırları ve tekrarlayan gezinme
  izleri kaldırıldı; stok yalnız azaldığında gösterilir.
- Footer'a sunucu adresi kopyalama düğmesi eklendi.

- Hukuki metinler için ülke/dil bazlı yönetim, ayrı taslak/yayımlama ve işletmeci
  incelemesi eklendi. Footer'dan ülke belgeleri okunabilir ve metin kopyası
  indirilebilir. Kayıtta sunulan sürümler oturum ve veritabanı işlemi içinde
  doğrulanır; sözleşme kabulü ile gizlilik/çerez bildirimi sunumu ayrı tutulur.
  Alışveriş, amaç bazlı izinler ve ülkeye özgü işletme incelemesi tamamlanmadı.
- Sayfa başlığı ve açıklamasındaki özel karakterlerin iki kez kodlanması
  düzeltildi; özel sayfaların girilmiş meta açıklamaları artık kullanılır.

- Wiki makalelerine sürümlü, düz metin SSS eklendi. Okuyucular soruları
  JavaScript olmadan açabilir; yalnızca kamuya açık rehberler aynı içerikle
  Schema.org işaretlemesi üretir.
- Doğrulanmış okuyucular mevcut makale sürümüne yararlı / henüz değil
  geri bildirimi verebilir ve seçimini değiştirebilir. Tekrarlı oylar ayrıştırılır;
  personel güncel sürümün toplamını görür, okuyucu kimlikleri yayımlanmaz.
- Hukuki bildirim ve izin modülünün tamamı henüz üretim veya hukuki uygunluk
  kabulü almadı; ülke profili açmak otomatik mevzuat sertifikası değildir.

## Geliştirme sürümü — 27 Ekim 2026

- Wiki kategorilerine ve yazılarına doğrulanmış üye / seçilen web rolü erişimi
  eklendi. Kategori, bütün üst sayfalar ve makale kuralları birlikte uygulanır;
  yetkisiz içerik liste, arama, sayaç ve bağlantılardan kaldırılır.
- İçerik yönetme izni kamuya açık okuyucu kuralını geçersiz kılmaz. Personel
  düzenleme ve geçmiş önizlemesi mevcut personel doğrulamalarını kullanır.
- Rol seçimi JavaScript olmadan klavyeyle yapılabilir. Eski içerik sürümünü
  gözden geçirerek kaydetmek güncel okuyucu erişimini korur.
- Wiki üç düzeyli sayfa düzeni, değişmeyen makale adresleri, içerik sürümleri
  ve çakışan düzenlemeler için sürüm kontrolü sunar.

Bu kayıt bir üretim sürümü duyurusu değildir. Migration geri dönüşleri ve
dosya erişim sınırları [Wiki rehberinde](WIKI.md), açık kabul adımları
[durum kaydında](STATUS.md) açıklanır.
