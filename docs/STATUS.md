# Geliştirme ve kabul durumu

30 Ekim 2026. Bu sürüm geliştirme aşamasındadır; üretim kabulü verilmemiştir.
Bir özelliğin uygulanması bütün ürünün veya hedef barındırmanın kabulü değildir.
Dağıtım adımları [kabul kontrol listesinde](RELEASE_CHECKLIST.md) izlenir.

| Alan | Uygulanan kapsam | Açık kapsam / kabul sınırı |
|---|---|---|
| Hesap ve personel | Yerel hesap, e-posta doğrulama, Fortify/TOTP, web rolleri ve personel doğrulamaları | Sosyal hesap, passkey ve bütün kimlik adaptörleri tamamlanmadı |
| Mağaza | Ürün, kupon, kod bozdurma, bonuslu kredi paketi, başka oyuncuya hediye, otomatik rütbe yenileme, sandık, cüzdan defteri, sipariş; ayrı incelemede son fiyat; oturuma bağlı onay ve özgün kopyalar; yerel sandbox, Stripe ve PayTR kodu | Gerçek sağlayıcı/billing kabulü, ürünün hukuki niteliği, erken/ertelenmiş ifa, vergi, gerçek çoklu para birimi ve vergi kapsamı tamamlanmadı |
| Teslimat | Ayrı ödeme/teslimat durumları; özgün şifreli komut planı, kalıcı teslimat kuyruğu; belirsiz sonuçta inceleme | Gerçek oyun sunucusu kabulü ve silinmiş katalog kaydını kurtarma kapsamı açık |
| Sunucu portalı | Java/Bedrock bağlantı alanları, önbellekli durum ve hesap eşleme | Her proxy, offline-mode veya Bedrock kimlik adaptörü desteklenmez |
| İçerik | Haber, özel sayfa, medya ve beş temanın ortak içerik sözleşmeleri | Sayfa oluşturucunun bütün blokları ve topluluk modülleri tamamlanmadı |
| Wiki | Kategori/arama, başlık bağlantıları, üç düzeyli sayfalar, metin ve SSS sürümleri, incelenmiş taslak geri yükleme; kategori/üst sayfa/makale erişimi; doğrulanmış okuyucu geri bildirimi | Rozet tabanlı erişim için gerçek ödül altyapısı henüz yok |
| Hukuki bildirimler | Ayrı taslak/yayım ve işletmeci incelemesi; otomatik dil/varsayılan profil; kayıt, sipariş ve bakiye yüklemede işlem içi kabul/sunum; özgün belge/ürün kopyası ve e-posta kuyruğu | Gerçek ülke metinleri ve işletme incelemesi, amaç bazlı izin/geri alma, yaş/veli, saklama/silme ve belirsiz e-posta için yönetim iş akışı tamamlanmadı |
| Temalar | Atlas, Forge, Volt, Nomad ve Mono; özgün görseller ve ikon kütüphanesi, renk/yarıçap belirteçleri, bölüm sırası, üst/alt menü düzenleyici ve denetlenen özel CSS | Yetkilerden ayrı rütbe görünümü, animasyon düzenleme/önizleme ve sürümlü geri alma mekanikleri tamamlanmadı |
| Destek ve topluluk | Destek talebi, forum, oy siteleri, yetkili başvurusu, oyuncu profili ve rozetler, sıralama ve cezalar sayfası | Özel formlar, öneriler ve gelişmiş moderasyon tamamlanmadı |
| Paper köprüsü | Kalıcı yerel kayıt, teslimat kiralama, makbuz kurtarma ve belirsiz sonuçta inceleme | Varsayılan kapalı; hedef oyun sunucusu kabulü ve köprü rehberindeki destek sınırları geçerli |

İşlev doğrulamaları ağdan izole Linux ortamındaki sentetik hesap, belge ve
verilerle yürütülür. SQLite/MariaDB regresyonları, gerçek üretim bağımlılık paketi,
yedek geri yükleme ve bağımlılık sırasıyla migration provaları ayrı kontrollerdir.
Beş tema için gerçek tarayıcıda dar ekran, JavaScript kapalı kullanım, klavye ve
native %200 yakınlaştırma kontrolleri uygulanır. Her kanıt kendi kaynak sürümü
için geçerlidir; sonraki değişiklikler yeniden doğrulanmalıdır.

Kaynak tabanlı tam kapsamlı bir güvenlik denetimi yapılmış ve doğrulanan bulgular
regresyon testleriyle kapatılmıştır. Bağımsız üçüncü taraf denetimi, hedef barındırmanın güvenlik/önbellek/proxy ayarları, paylaşılan kuyruk ve
önbellek davranışı, yük dengeleme ve gerçek dış servis kabulü henüz tamamlanmamıştır.

[Hukuki belgeler](LEGAL.md), [sipariş kopyaları](ORDER_CONFIRMATIONS.md) ve
[Paper köprüsü](../bridge/paper/README.md) kendi sınırlarını ayrıca açıklar.
