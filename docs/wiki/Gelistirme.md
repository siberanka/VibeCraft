# Geliştirme

VibeCraft'ı kaynak koddan çalıştırmak, değişiklikleri test etmek ve sürüm dosyalarını üretmek.

> English: [Development](Development.md)

## Gerekenler

- [Kurulum](Kurulum.md#gerekenler) sayfasındaki eklentilerle PHP 8.4+
- Composer 2
- MySQL 8 veya MariaDB (test paketi bellekte SQLite kullanır)
- Eklenti için: JDK 25 ve bir POSIX kabuğu

Node araç zinciri kullanılmaz: `public/assets` içindeki CSS ve JavaScript elle yazılmıştır.

## Yerelde çalıştırma

```sh
git clone https://gitlab.com/siberanka/vibecraft.git
cd vibecraft
composer install
cp .env.example .env
php artisan key:generate
```

`.env` içinde `APP_ENV=local`, `APP_URL=http://localhost:8000`, `SESSION_SECURE_COOKIE=false` ve yerel
veritabanınızı ayarlayın, sonra:

```sh
php artisan migrate
php artisan vibecraft:setup-token
php artisan serve --host=127.0.0.1
```

`http://localhost:8000/install` adresini açın, yazdırılan kodu girin ve sahip hesabını oluşturun. Varsayılan
kullanıcı yoktur. Kurulum sihirbazının `.env` dosyasını düzenlemesini istemiyorsanız yerelde
`VIBECRAFT_INSTALLER_WRITES_ENV=false` kullanın.

## Proje yapısı

| Yol | İçerik |
|---|---|
| `app/Core` | Kurulum, ayarlar, güvenlik, denetim günlüğü, temalar, yönetim kataloğu |
| `app/Domain` | İş mantığı: ticaret (siparişler, cüzdan, teslimatlar), ödemeler, köprü, Minecraft, içerik, hukuki, medya, CAPTCHA |
| `app/Http` | Denetleyiciler ve ara katmanlar |
| `resources/themes/<id>` | Tema tanımları (`theme.json`) |
| `resources/views/themed` | Bütün temaların ortak Blade görünümleri |
| `resources/lang` | `en.json` ve `tr.json` çevirileri |
| `public/assets` | CSS, JavaScript, yazı tipleri, tema görselleri ve ikon kütüphanesi |
| `routes` | Web, API (ödemeler ve köprü) ve zamanlanmış işler (`console.php`) |
| `bridge/paper` | Paper eklentisi (Java; Gradle veya Maven yok) |
| `tests` | PHPUnit özellik ve birim testleri |
| `tools` | Sürüm, kaynak paketleme ve ikon kütüphanesi betikleri |

## Testler ve kontroller

```sh
php artisan test
php -d opcache.enable_cli=0 vendor/bin/phpstan analyse --memory-limit=256M --no-progress
vendor/bin/pint --test
```

Bir değişiklik birleştirilmeden önce üçü de geçmelidir. Yeni davranış bir testle, güvenlik düzeltmesi bir
regresyon testiyle gelir.

## Çeviriler

Kullanıcıya görünen her metin kodda İngilizce yazılır ve `resources/lang/tr.json` dosyasında çevrilir. Yeni
bir metin eklediğinizde Türkçesini aynı değişiklikte ekleyin. Yönetim ayarı etiketleri ayar anahtarını
(örneğin `store.gifts`) kullanır ve hem `en.json` hem `tr.json` içinde karşılık ister.

## Eklentiyi derleme

`bridge/paper/dependencies.sha256` dosyasında listelenen altı sabit API jar'ını bir klasöre koyun (indirme
bağlantıları ve koordinatlar [bridge/paper/README.md](../../bridge/paper/README.md) dosyasında), sonra:

```sh
cd bridge/paper
sh build.sh /tam/yol/api-jarlari
```

Betik her jar'ın SHA-256 değerini kontrol eder, derler, çekirdek testleri çalıştırır ve yalnızca eklentinin
kendi sınıflarını içeren `target/VibeCraftBridge-<sürüm>.jar` dosyasını yazar. MockBukkit ile isteğe bağlı
çevrimdışı yaşam döngüsü testleri eklenti README dosyasında anlatılır.

## Sürüm paketi üretme

Temiz bir çalışma kopyasından (kaydedilmemiş değişiklik olmadan):

```sh
python tools/build_release.py --php /yol/php8.4 --composer /yol/composer.phar --jar bridge/paper/target/VibeCraftBridge-<sürüm>.jar
```

Betik yalnızca kaydedilmiş dosyaları alır (`git archive`), testleri, araçları ve eklenti kaynağını dışarıda
bırakır, üretim Composer bağımlılıklarını kurar ve ortam dosyalarını, veritabanlarını, dökümleri, kayıt
dosyalarını veya anahtarları paketlemeyi reddeder. `build/release/` klasöründe hosting zip'i, eklenti jar'ı ve
`SHA256SUMS` oluşur.

Yayımlamadan önce zip'i bir test hostinginde iki klasör yerleşimiyle de kurun ve
[Güvenlik](Guvenlik.md#yerleşik-korumalar) sayfasındaki korunan yolların 403 veya 404 döndüğünü kontrol edin.
Sürümler elle üretilip yüklenir; proje CI kullanmaz.

## Sürüm numaraları

Sürüm `config/vibecraft.php` dosyasında ve eklentide (`bridge/paper/build.sh`, `plugin.yml`,
`Protocol.java`) tutulur. Birlikte yükseltin. Etiketler `vMAJOR.MINOR.PATCH` biçimindedir; ön sürümler için
`-beta` eklenir.

## Katkı ve lisans

Hata bildirimleri ve merge request'ler [GitLab](https://gitlab.com/siberanka/vibecraft) üzerinden kabul edilir.
VibeCraft [VibeCraft Lisansı](../../LICENSE) ile dağıtılır: kopyalar ve fork'lar lisansı,
[NOTICE](../../NOTICE) dosyasını ve atfı korumalıdır; satılamaz ve ücretli hizmet olarak sunulamaz.
