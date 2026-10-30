# Sunucu rehberi / Wiki

Wiki, kategori ve yazıları Türkçe/İngilizce ayrı tutan yerleşik içerik modülüdür.
Yönetim panelindeki Wiki kategorileri ve Wiki yazıları alanları `content.manage`
iznini, mevcut personel iki aşamalı doğrulamasını ve parola onayını kullanır.

Önce yazıyla aynı dilde bir kategori oluşturun. Ardından başlık, kısa adres,
özet ve Markdown metnini girin. Taslaklar kamuya açılmaz. Yayımlanmış bir yazı,
yayın zamanı geldiğinde ve kategorisi görünür olduğunda `/wiki` altında görünür.
Zaman alanı uygulamanın ayarlanmış saat dilimini kullanır; boş bırakılan yayın
zamanı, yayımlama sırasında geçerli zamanla doldurulur. Aynı kısa adres farklı
dillerde kullanılabilir. Çeviriler ayrı yazılardır; eksik çeviri otomatik gösterilmez.

Ham HTML metin olarak gösterilir; metin ve bağlantılar sunucuda biçimlendirilir.
İkinci ve üçüncü düzey başlıklardan bağlantılı içindekiler listesi oluşturulur;
metindeki birinci düzey başlık ikinci düzeye çevrilir. Tablolar klavyeyle
kaydırılabilir bir bölgede tutulur. Yazı sınırları 100.000 karakter ve 200 başlıktır.

Arama başlık, özet ve yazı metninde çalışır; `%` ve `_` gibi karakterler aynen
aranır. Listeler sayfa başına 12 yazı, kategori gezinmesi en fazla 100 kategori,
yönetici kategori seçimi ilk 1.000 kategoriyi ve varsa yazının bu sınır dışında
kalan mevcut kategorisini gösterir. İlgili yazılar aynı
kategorideki en fazla sekiz yayımlanmış yazıdır.

İki düzenleyici aynı kaydı açarsa ilk kayıt korunur; eski sürümle kaydetme
isteği reddedilir. Bildirim sonrasında sayfayı yeniden yükleyip değişiklikleri
birleştirin. Bir kategorinin dilini değiştirmeden önce mevcut yazılarını uygun
kategorilere taşıyın. Görünürlüğü kapatmak yazıları korur ve kamuya kapatır.

Site ayarlarından Wiki modülünü kapatmak veriyi silmez; kamuya açık Wiki
adresleri ve menü bağlantısı kapanır. Yetkili personel düzenlemeye devam edebilir.
Modül beş temanın ortak erişilebilir arama, kategori ve rehber bileşenlerini
kullanır; temel kullanım JavaScript gerektirmez. Yeni tablolar standart
`php artisan migrate --force` güncellemesiyle eklenir. Güncellemeden önce
veritabanını ve uygulama dosyalarını yedekleyin.

## İçerik sürümleri

Yazıyı düzenleme ekranındaki **Sürüm geçmişi** bağlantısı, kayıtlı metin sürümlerini
en yeniden eskiye gösterir. Her sürüm başlık, özet, Markdown metni, SSS ve dili saklar;
biliniyorsa kaydeden personel ve kayıt zamanı da görünür. Geçmiş yalnızca içerik
yönetme yetkisi olan, mevcut personel doğrulamalarını tamamlayan kullanıcılarca
görülebilir. Kamuya açık rehber eski sürümleri veya düzenleyici bilgilerini göstermez.

Eski sürümü açıp **Bu sürümü gözden geçir** bağlantısını kullanın. Bu işlem yalnızca
düzenleme formunu doldurur; mevcut yazıyı değiştirmez. Kısa adres, kategori ve dil
güncel değerlerini korur; güncel üst sayfa ve okuyucu erişimi de korunur.
Yayın seçimi taslakla başlar ve yayın zamanı temizlenir.
Metni gözden geçirip kaydedin; hemen yayımlamak isterseniz yayın durumunu açıkça
Yayımlandı olarak seçin. Kaydetmek yeni bir sürüm ekler ve hangi sürümün temel
alındığını kaydeder. Eski sürüm korunur. Bu işlem geçmişteki adresi, kategoriyi,
dili, erişim ayarını veya yayın takvimini geri almaz.

Geçmiş kayıtlarını düzenleyen veya silen bir panel işlemi yoktur. Bir personel
hesabı silinirse içerik korunur ve düzenleyici bilinmiyor olarak gösterilebilir.
İçerik çakışması halinde hata formu önceki beklenen sürümü korur; sayfayı yeniden
yükleyip güncel içerikle karşılaştırmadan eski form tekrar kaydedilemez.

Özellik güncellemesi, mevcut her yazının yalnızca o andaki içeriğini başlangıç
kaydı olarak alır. Daha önce tutulmayan düzenlemeler ve son düzenleyici bilgisi
üretilmez. Bu kayıtlar başlangıç içeriği ve bilinmeyen düzenleyici olarak açıkça
işaretlenir. Bakım modunda yedek aldıktan sonra standart migration işlemini
çalıştırın. Veritabanı geri dönüşü geçmiş tablosunu kaldırır; böyle bir işlemden
önce sürüm kayıtlarını da içeren yedek gerekir. Mevcut yazı içerikleri korunur.

## Alt sayfalar

Yazılar aynı kategori ve dil içinde en fazla üç düzeyde düzenlenebilir. Önce
yeni taslağı kaydedin; düzenleme ekranında **Üst sayfa** alanını kullanın.
**Üst sayfa yok** seçimi yazıyı en üst düzeye taşır. Kategori veya dil değiştirirken
önce üst sayfa ilişkisini kaldırıp kaydedin. Alt sayfaları olan bir yazının kategori
ve dilini değiştirmeden önce alt sayfalarını uygun yerlere taşıyın.

Üst sayfa seçenekleri mevcut alt sayfaların derinliğine göre sınırlandırılır.
Bir sayfa kendisinin veya alt sayfalarının altına taşınamaz. Bir dalı taşımak,
mevcut alt sayfalarıyla birlikte üç düzeyi aşarsa işlem reddedilir. İçerik
düzenlemeleri ve yapı değişiklikleri aynı düzenleme sürümü korumasını kullanır.

Kamuya açık bir alt sayfanın görünmesi için kendisi ve bütün üst sayfaları
yayımlanmış, yayın zamanı gelmiş ve kategorileri görünür olmalıdır. Arama,
kategori sayaçları, ilgili yazılar ve doğrudan adresler aynı kurala uyar. Üst
sayfanın taslağa alınması alt sayfaları silmez; onları da kamuya kapatır.

Bir yazıda en fazla 20 doğrudan alt sayfa bağlantısı gösterilir; diğer yazılar
genel liste ve aramayla bulunabilir. Üst sayfa seçimi ilk 1.000 uygun yazıyı ve
varsa sınır dışında kalan mevcut seçimi korur. Yapı değiştiğinde yazıların kısa
adresleri ve mevcut URL'leri korunur; gezinme yolu yeni üst sayfaları gösterir.
Eski içerik sürümünden gözden geçirerek kaydetmek güncel üst sayfayı korur.

Mevcut kurulumlar güncellendiğinde eski yazılar üst düzeyde kalır. Yeni ilişki,
normal migration ile eklenir. Veritabanı geri dönüşü bu düzeni kaldırır; içerik
korunur. Yapıyı geri getirmek için migration öncesi yedek gerekir.

## Okuyucu erişimi

Kategori ve yazılar için **Herkes**, **Doğrulanmış üyeler** veya **Seçilen roller**
seçilebilir. Seçilen rollerden en az birine sahip olmak ve e-posta adresini
doğrulamış olmak gerekir. Kullanıcı, kategorinin ve bütün üst sayfaların erişim
kurallarını da karşılamalıdır. Herkese açık bir alt sayfa, üst sayfasının kuralını
gevşetmez. Rol adları web hesabındaki gerçek rollerdir; oyun adı veya rütbe
etiketi okuyucu rolü yerine geçmez. Rozet tabanlı erişim henüz desteklenmez.

Yetkisiz yazılar ve kategoriler listelerde, aramada, sayaçlarda, alt sayfalarda
ve ilgili içerikte gösterilmez; doğrudan adresleri 404 döndürür. Yönetici veya
site sahibi rolü kamuya açık adreslerde otomatik erişim sağlamaz. İçerik yönetme
izni olan personel, mevcut personel doğrulamalarını tamamlayarak yönetim
panelindeki düzenleyici ve sürüm önizlemesini kullanabilir. Oturumlu yanıtlar
paylaşılan önbelleğe alınmaz; CDN kurallarınız uygulamanın `no-store` yanıtlarını
korumalıdır.

Rol seçimleri JavaScript olmadan kaydedilebilir. Seçilen roller modunda en az bir
geçerli web rolü gerekir. Diğer modlarda saklanan rol listesi temizlenir. Kural
değişiklikleri de düzenleme sürümü kontrolünü ve denetim kaydını kullanır.
Eski metni gözden geçirerek geri yüklemek güncel erişim kurallarını korur.

Bu kurallar **sayfanın okunmasını** sınırlar. Metne eklenen bağlantılar, dosyalar
ve görseller kendi erişim izinlerini korur. Yerleşik medya adresleri herkese
açıktır; özel belgeleri buraya yüklemeyin. Migration mevcut kategori ve yazıları
Herkes olarak korur. Migration geri dönüşü erişim kurallarını kaldırır ve kalan
içeriği eski herkese açık davranışa döndürür; önce yedek alın ve erişimi bakım
modunda sınırlandırın.

## Sık sorulan sorular

Makale düzenleyicisindeki **Sık sorulan sorular** bölümünü açın. En fazla on
soru/cevap çifti eklenebilir; sorular 160, cevaplar 2.000 karakterle sınırlıdır.
Metinler düz yazıdır; HTML ve Markdown çalıştırılmaz. Yeni bir satır açmak için
kaydedip düzenleyiciyi yeniden açın. Bir satırı kaldırmak için soru ve cevabı
birlikte temizleyin; tek tarafı boş bırakmak hata verir. SSS değişiklikleri de
makalenin sürümüne kaydedilir ve eski sürümü gözden geçirirken forma gelir.
SSS özelliğinden önceki sürümlerde soru üretilmez; bu sürümler boş SSS içerir.

Okuyucu, soruları klavye veya dokunmayla açabilir; JavaScript gerekmez.
Yalnızca misafirlerin okuyabildiği makalelerde, ekranda bulunan aynı soru ve
cevaplar `FAQPage` / `Question` / `Answer` microdata işaretlemesini taşır.
Özel makaleler ve yönetim önizlemeleri bu kamuya yönelik işaretlemeyi üretmez.
Bu, Schema.org anlamlandırmasıdır; Google'da özel arama sonucu vaat etmez.
Google, FAQ zengin sonuçlarını 7 Mayıs 2026'da kaldırmıştır.
[Resmi değişiklik kaydı](https://developers.google.com/search/updates).

## Okuyucu geri bildirimi

E-posta adresi doğrulanmış ve makaleyi okumaya yetkili kullanıcılar, mevcut sürüm
için **Evet, yararlı** veya **Henüz değil** seçebilir. Aynı sürüme tekrar gönderim
yeni oy oluşturmaz; kullanıcının mevcut seçimini değiştirir. Makale değişmişse
eski form reddedilir ve yeniden yükleme istenir. Kategori, üst sayfa, makale
erişimi ve modül durumu gönderim sırasında da kontrol edilir. Dakikada hesap
başına en fazla 20 gönderim kabul edilir.

Kamuya açık sayfa oy toplamlarını veya okuyucu adlarını göstermez. Yetkili
personel sürüm geçmişinde yalnızca güncel sürümün gerçek toplamlarını görür.
Eski sürüm oyları yeni sürüme taşınmaz. Hesap veya makale silinince ilişkili
geri bildirim silinir. Serbest yorum, misafir parmak izi veya bu özellik için
ayrıca IP kaydı tutulmaz.

Güncelleme, makale ve sürüm tablolarına SSS alanı ve `wiki_feedback` tablosu ekler.
Migration geri dönüşü SSS ve geri bildirim verilerini kaldırır; diğer makale
metinlerini korur. SSS/geri bildirim dahil yedek almadan geri dönüş yapmayın.
