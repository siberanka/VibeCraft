# Güncelleme ve Bakım

Çalışan bir siteyi sağlıklı tutmak ve yeni bir sürüme geçmek.

> English: [Updates and Maintenance](Updates-and-Maintenance.md)

## Cron ne yapar

Tek cron satırı her dakika `artisan schedule:run` komutunu çalıştırır; o da şu işleri başlatır:

| İş | Sıklık | Ne yapar |
|---|---|---|
| Kuyruk | Her dakika | E-postaları ve Discord bildirimlerini gönderir, arka plan işlerini yürütür (50 saniye sonra durur) |
| Süre bitimi | Her dakika | Ödenmeyen siparişleri 60 dakika sonra iptal eder, süreli rütbeleri yeniler veya bitirir |
| Sipariş kopyaları | Her dakika | Sipariş kopyalarını e-posta kuyruğuna alır |
| Sunucu durumu | 2 dakikada bir | Oyun sunucularınızın oyuncu sayılarını yeniler |
| Ödeme tekrarları | 5 dakikada bir | Başarısız olan doğrulanmış ödeme bildirimlerini yeniden işler |
| Temizlik | Saatte bir | Süresi dolmuş geçici verileri ve şifre sıfırlama kodlarını siler |
| Defter kontrolü | Her gün 04:10 | Cüzdan bakiyelerinin defterle uyuştuğunu kontrol eder |
| Başarısız iş temizliği | Her gün | 30 günden eski başarısız işleri siler |
| Cloudflare adresleri | Haftada bir | Cloudflare IP listesini yeniler (yalnızca Cloudflare modunda) |

İşler üst üste binmez; yavaş bir çalışma birikme yaratmaz. Cron durursa oyuncular bunu önce gelmeyen
teslimatlardan ve e-postalardan fark eder; cPanel **Cron İşleri** bölümünde komutu ve PHP yolunu kontrol edin
(bkz. [Sorun Giderme](Sorun-Giderme.md#cron-çalışmıyor)).

## Yeni sürüme güncelleme

Önce sürüm notlarını okuyun; güncellemenin veritabanını değiştirip değiştirmediğini yazar.

1. Veritabanını, `storage/app` klasörünü, `.env` dosyasını ve eklenti kaydını **yedekleyin**
   ([Güvenlik](Guvenlik.md#yedekler)).
2. Yeni `vibecraft-<sürüm>.zip` dosyasını indirip `SHA256SUMS` ile doğrulayın.
3. **Dosyaları değiştirin, verileri koruyun.** Yerleşim A'da (kök klasör `public/`):
   - yeni zip'i eski klasörün yanına, örneğin `vibecraft-new` olarak açın,
   - eski klasördeki `.env` dosyasını ve bütün `storage` klasörünü `vibecraft-new` içine taşıyın (paketteki
     boş `storage` klasörünün yerine),
   - eski klasörün adını `vibecraft-old`, `vibecraft-new` klasörünün adını `vibecraft` yapın. Kök klasör
     `vibecraft/public` olarak kalır.

   Yerleşim B'de (`public_html`): yeni zip'i geçici bir klasöre açın ve içeriğini `.env` ile `storage`
   klasörüne dokunmadan `public_html` üzerine kopyalayın.
4. **Veritabanını güncelleyin.** cPanel'de **Terminal** açın (veya tek seferlik bir cron işi ekleyin) ve
   çalıştırın:

   ```
   cd ~/vibecraft && php artisan migrate --force && php artisan optimize:clear
   ```

   Cron satırınızdaki PHP yolunun aynısını kullanın. Tek seferlik cron işini çalıştıktan sonra silin.
5. Siteyi açın, giriş yapın; mağazayı, bir ürün sayfasını ve **Yönetim → Teslimatlar** bölümünü kontrol edin.
6. Sürümde yeni bir eklenti jar'ı varsa her oyun sunucusunda güncelleyin (durdurun, değiştirin, başlatın;
   eklenti klasörünü koruyun).
7. Emin olana kadar `vibecraft-old` klasörünü saklayın, sonra silin.

Bir sorun çıkarsa klasör adlarını geri çevirin ve eski dosyalarla eşleşen veritabanı yedeğini geri yükleyin.
Yeni bir sürümün değiştirdiği bir veritabanını asla eski dosyalarla çalıştırmayın.

## Konsol komutları

Uygulama klasöründe cPanel **Terminal** veya SSH ile çalıştırın (`php artisan …`):

| Komut | Kullanım |
|---|---|
| `vibecraft:setup-token` | Yeni bir kurulum kodu yazar (yalnızca kurulum bitmeden önce) |
| `vibecraft:grant-owner EPOSTA` | Var olan bir hesaba sahip rolü verir ([erişim kaybı](Roller-ve-Yetkiler.md#sahip-erişimini-kaybetmek)) |
| `vibecraft:status-refresh` | Sunucu durumunu hemen yeniler |
| `vibecraft:expire` | Sipariş ve rütbe süre bitimini hemen çalıştırır |
| `vibecraft:webhooks-retry` | Başarısız ödeme bildirimlerini hemen yeniden dener |
| `vibecraft:ledger-reconcile` | Cüzdan defterini hemen kontrol eder |
| `vibecraft:prune` | Süresi dolmuş geçici verileri siler |
| `down` / `up` | Siteyi bakım moduna alır ve geri açar |
| `optimize:clear` | Elle yapılan değişikliklerden sonra önbelleğe alınmış ayarları, yolları ve görünümleri temizler |

## Kayıtlar

Hatalar `storage/logs/laravel.log` dosyasına yazılır (internetten erişilemez). Bir şey ters gittiğinde Dosya
Yöneticisi'nde açın. VibeCraft sırları bu dosyaya yazmaz, ama e-posta ve IP adresleri içerebilir: herkese açık
paylaşmayın, yalnızca ilgili satırları paylaşın.
