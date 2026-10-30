# Hukuki belgeler ve sipariş kayıtları

Ülke profilleri ve sürümlü belgeler yönetim panelinden hazırlanır, önizlenir ve
ayrı onayla yayımlanır. İlk profil kapalıdır. Seçili bütün dillerin altı belge
türü ve işletmecinin açık inceleme onayı tamamlanmadan etkinleştirilemez.
Satıcı bilgileri gerçek işletmeden sağlanmalıdır; örnek metin yayımlamak zorunlu
alanların doldurulmuş olmasıyla hukuken yeterli hale gelmez.

## Otomatik dil ve profil

Footer, kayıt ve alışveriş ekranları belgeleri arayüz dilinde otomatik gösterir.
İşletmeci varsayılan ülke profilini belirler. Bütün diller arasında tek etkin ve
incelenmiş profil varsa ayrıca oyuncu seçimi gerekmez; birden çok profil varsa
varsayılan açıkça ayarlanmalıdır. Müşteriye ikamet seçimi ancak işletmeci bu
seçeneği etkinleştirirse sunulur. Dil veya para birimi kişinin ülkesi sayılmaz.
Yapılandırılan profil ya da dil hazır değilse başka ülkeye sessiz geçiş yapılmaz.

Profil düzenlemek incelemeyi sıfırlar ve profili duraklatır. Taslak kaydetmek
yayımlanmış metni değiştirmez. Yayım, taslak sürümü ve tamamlanmış satıcı alanları
üzerinde ayrı kontroldür. Yapılandırma veya belge sürümü değişirse açık eski form
yeni koşulları atlayamaz; kullanıcı güncel formu tekrar inceler.

## Kabul ve bilgilendirme

| İşlem | Sunulan kayıtlar |
|---|---|
| Kayıt | Kullanım şartları kabulü; veri ve çerez bildiriminin sunumu |
| Sipariş / bakiye yükleme | Kullanım ve satış şartları kabulü; veri, çerez, ön bilgilendirme ve cayma/iptal açıklamalarının sunumu |

Şartlar kutusu başlangıçta boştur. Bildirimler **sunuldu**, sözleşme metinleri
**kabul edildi** olarak ayrı saklanır. Bir metnin sunulması onun okunduğu veya
isteğe bağlı pazarlama/takibe izin verildiği anlamına gelmez. Bu kayıtlar amaç
bazlı isteğe bağlı rıza yerine kullanılamaz.

Kabul kaydı hesap veya siparişin veritabanı işlemi içinde oluşur. Aktif profil,
belge dili ve tam sürümleri, gerçek hesap ve sipariş, son tutar ve inceleme özeti
aynı işlemde yeniden kontrol edilir. Başka hesap, eski belge, kapanmış profil
veya değişmiş ürün/teslimat/fiyat, eski onayla devam edemez. Kısmi işlem geri
alınır; önceki kabul sonradan oluşturulmuş gibi gösterilmez.

Yayımlanmış sürüm, metni ve o andaki satıcı bilgilerini korur. Sonraki düzenleme
eski sürümü değiştirmez. Geçmiş sürüme ilişkin kaydı bulunan hesap sahibi bu
metne erişebilir; personel müşteri bağlantısından başka kişinin geçmişine
erişemez. Bu sayfalar ortak önbelleğe alınmaz.

Siparişin ayrıca özgün ürün/fiyat/belge kopyası ve ayrı şifreli teslimat planı
vardır. İndirilebilir dosya, e-posta kuyruğu, geç ödeme ve inceleme davranışı
[sipariş kopyaları rehberinde](ORDER_CONFIRMATIONS.md) açıklanır.

## Çerezler ve kabul sınırı

Çerez bilgi sayfası uygulamanın oturum, form güvenliği ve isteğe bağlı kalıcı
oturum çerezlerinin adlarını ve işlevlerini gösterir; değerleri göstermez.
Bu envanter barındırma hizmetinin ve sonradan eklenen araçların tam incelemesi
yerine geçmez. Kullanılmayan pazarlama/analitik araçları için sahte izin anahtarı
gösterilmez.

Amaç bazlı izin/geri alma, ürünün hukuki niteliği, erken ifa/cayma, gerçek vergi
ve fatura bilgileri, çocuk/veli işlemleri, kişisel veri silme ve saklama süreleri
ayrı geliştirme ve işletme kabul koşullarıdır. Ülke profilinin yapısal olarak
tamamlanması bütün yerel kanunlara otomatik uyum beyanı değildir. Gerçek ödeme
sağlayıcısı hesabı ve hedef oyun sunucusu kabulü de ayrıca gereklidir.

Hukuki belgeler dört tablo kullanır: legal_markets, legal_policies,
legal_documents ve legal_acceptances. Sipariş kopyaları order_contracts ve
order_contract_documents; bildirim kuyruğu order_contract_notices kullanır.
Geri alma özelliğe ait kayıtları kaldırır. Önce ilgili işletme verileriyle
şifreleme anahtarlarını içeren yetkili yedek ve bakım planı hazırlanmalıdır;
yeni bağımlılıklar bildirim, sipariş kopyası, hukuki belgeler sırasıyla ele alınır.
Uygulama düzeyindeki değişmezlik, veritabanı yöneticisine karşı imza garantisi
veya tamamlanmış saklama/silme politikası değildir.

Bu sürüm geliştirme aşamasındadır; bütün ürünün üretim kabulü verilmemiştir.
Güncel sınırlar [durum kaydında](STATUS.md) izlenir.
