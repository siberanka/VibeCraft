# Topluluk Özellikleri

Mağaza dışında oyuncuları geri getiren her şey. Haberler, mağaza, destek talepleri ve wiki
**Yönetim → Ayarlar → Modüller** bölümünden ayrı ayrı kapatılabilir.

> English: [Community Features](Community-Features.md)

## Haberler

**Yönetim → Haberler**: başlık, kategori, Markdown metin, kapak görseli, durum (taslak veya yayımlandı) ve
yayın zamanı. Haberler `/news` sayfasında ve **Son haberler** ana sayfa bölümünde görünür. İleri bir yayın
zamanı haberi zamanlar.

## Sayfalar

**Yönetim → Sayfalar**: kurallar, hakkımızda veya yetkili ekibi gibi Markdown ile yazılan bağımsız
sayfalar. Sayfayı menüye eklemek için **Menüde göster** seçeneğini işaretleyin; ya da
[Menüler](Gorunum-ve-Temalar.md#menüler) bölümünden bağlantı verin.

## Sunucu rehberi (wiki)

Komutlar, rütbeler, kurallar ve rehberler için `/wiki` adresinde yerleşik bir rehber.

- **Yönetim → Wiki kategorileri** ve **Wiki yazıları**. Kategoriler ve yazılar her dil için ayrı yazılır.
- Yazılar Markdown'dır, üç düzeye kadar alt sayfa içerebilir ve başlıklarından içindekiler listesi
  oluşturulur.
- Her kayıt bir sürüm saklar. **Sürüm geçmişi** eski sürümleri gösterir; bir sürümü gözden geçirmek
  yayındaki yazıyı değiştirmeden düzenleyiciyi doldurur.
- **Okuyucu erişimi**: kategori ve yazı bazında herkes, doğrulanmış üyeler veya seçilen roller.
- Yazı başına en fazla on **sık sorulan soru**, açılır liste olarak gösterilir.
- Giriş yapmış okuyucular yazının yararlı olup olmadığını işaretleyebilir; toplamları yalnızca personel
  görür.

Ayrıntılı başvuru belgesi: [docs/WIKI.md](../WIKI.md).

## Forum

**Yönetim → Forum kategorileri** bölümleri oluşturur. **Konuyu yalnız yetkililer açar** seçeneği bölümü,
oyuncuların yine de yanıt yazabildiği bir duyuru bölümüne çevirir. E-postası doğrulanmış oyuncular konu
açar ve yanıt yazar; *forum moderasyonu* yetkisi olan personel konuları sabitleyebilir, kilitleyebilir,
gizleyebilir ve tek tek mesajları gizleyebilir. Her moderasyon işlemi denetim günlüğüne yazılır.

## Destek talepleri

Oyuncular `/tickets` sayfasında konu ve mesajla talep açar ve yanıtları orada takip eder. Personel
**Yönetim → Destek** bölümünden yanıtlar. Yeni talepler Discord'a da bildirilebilir (aşağıya bakın).

## Yetkili başvuruları

**Yönetim → Yetkili pozisyonları**: ad, açıklama, en fazla on soru (her satıra bir soru) ve **Başvuru
alıyor**. Açık pozisyonlar `/apply` sayfasında listelenir. *Başvuru değerlendirme* yetkisi olan personel
**Yönetim → Yetkili Başvuru** bölümünde okur ve karar verir; oyuncu sonucu aynı sayfada görür.

## Oy siteleri

**Yönetim → Oy siteleri**: ad, oy bağlantısı (`https://`), oylar arası saat ve görünürlük. `/vote` sayfası
siteleri oyuncuların ne sıklıkla oy verebileceğiyle birlikte listeler. Oy ödülleri oyun sunucusunda oy
eklentiniz tarafından verilir.

## Sıralama ve cezalar

- `/leaderboards` oynama süresini, oyuncu öldürme, ölüm ve yaratık öldürme sayılarını gösterir.
- `/punishments` sunucunun yasaklarını gösterir.

İkisini de `share-statistics` ve `share-punishments` açıkken
[Paper eklentisi](Paper-Eklentisi.md) doldurur.

## Oyuncu profilleri ve rozetler

Her oyuncunun `/player/<ad>` adresinde bağlı Minecraft hesabını, katılma tarihini, forum mesajlarını ve
rozetlerini gösteren bir profili vardır. *Kullanıcı yönetimi* yetkisi olan personel `/admin/badges`
adresinde rozet (ad, renk, açıklama) oluşturur ve **Yönetim → Kullanıcılar** altındaki kullanıcı
sayfasından verir.

## Discord

**Yönetim → Entegrasyonlar → Discord**:

| Ayar | Kullanım |
|---|---|
| Client ID, client secret | Oyuncuların Discord hesaplarını **Hesap** sayfasından bağlamasını sağlar. Discord Developer Portal'da bir uygulama oluşturup `https://alanadiniz/account/discord/callback` yönlendirme adresini ekleyin. Yalnızca `identify` izni kullanılır; Discord bağlantısı kimseyi siteye giriş yaptırmaz. |
| Webhook URL | Bildirimler için bir Discord kanal webhook'u |
| Sipariş bildirimi, talep bildirimi | Hangi olayların o kanala gönderileceği |

Discord davet düğmesi **Ayarlar → Discord daveti** değerinden gelir.

## Bildirimler

Oyuncular ödenen siparişleri, aldıkları hediyeleri, yanıtlanan talepleri ve başvuru kararlarını
**Hesap → Bildirimler** sayfasında görür. Önemli iletiler (doğrulama, şifre sıfırlama, sipariş kopyaları)
[e-posta ayarlandıktan](Ilk-Adimlar.md#2-e-postayı-ayarlayın) sonra e-postayla da gönderilir.
