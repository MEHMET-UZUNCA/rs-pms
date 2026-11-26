# RS PMS

**RS PMS**, Rocks Hotel (KKTC) için sıfırdan yazılan otel ön büro yönetim sistemidir (Property Management System).
Fidelio Suite8'in yerini alacak şekilde tasarlanır; muhasebe **Logo**'da kalır, restoran/bar satış noktaları
**Oracle Simphony**'den gelir. İleride **RS ONE** ile tek uygulamada birleşecektir.

> Durum: **Uygulama katmanı — 2. adım.** Veritabanı `main`'de. Servisler: numara serisi, vergi (Fidelio Tax Details oranları, tarihli değişiklik), fiyat, müsaitlik, folyo kaydı, rezervasyon yaşam döngüsü, gece kapanışı; Fidelio vergi oranı ve departman kodu içe aktarma komutları. 59 test.

## Belgeler

- [Eksikler ve bekleyen bilgiler](docs/eksikler.md)

| # | Belge | İçerik |
|---|---|---|
| 1 | [Mimari](docs/01-mimari.md) | Teknoloji, katmanlar, RS ONE ile birleşme kuralları, modüller, aşamalar |
| 2 | [Kaynak karşılaştırması](docs/02-kaynak-karsilastirma.md) | Fidelio, Kamra, QloApps (ve OCA/pms) veri modelleri; neyi nereden aldık |
| 3 | [Veritabanı](docs/03-veritabani.md) | Tablo tablo, kolon kolon veritabanı tasarımı |
| 4 | [Simphony entegrasyonu](docs/04-simphony.md) | Outlet'ler, oda hesabına yazma, günlük satış aktarımı |
| 5 | [Logo aktarımı](docs/05-logo.md) | Günlük gelir fişi, tahsilat, cari |
| 6 | [Fidelio'dan geçiş](docs/06-fidelio-gecis.md) | 19 yıllık verinin taşınması ve arşiv |
| 7 | [Arayüz](docs/07-arayuz.md) | Ekranlar (QloApps düzeni örnek alınarak) |
| 8 | [Kapsam kontrolü](docs/08-kapsam-kontrolu.md) | Suite8 işlevleri → RS PMS tabloları |
| 9 | [Kamra karşılaştırması](docs/09-kamra-karsilastirma.md) | Kamra'nın 148 veri tipi → RS PMS tabloları |
| 10 | [Raporlar](docs/10-raporlar.md) | Rapor ekranı planı ve başlangıç rapor seti |
| 11 | [Web API](docs/11-web-api.md) | Web sitesinin oda tipi, online fiyat ve müsaitlik okuduğu API |
| — | [Ekran taslakları](docs/ekranlar/README.md) | Görüntüler, videolar ve kaynak dosyalar |
| — | [Fidelio kolon kolon eşleme](docs/fidelio/kolon-kolon.md) | PROISV 13 view, 391 kolon |

## Onaylanan kararlar

| Konu | Karar |
|---|---|
| Veritabanı tasarımı | 212 tablo, `pms_` öneki, normalize; Fidelio PROISV view'larının 391 kolonu, Suite8 işlev listesi ve Kamra'nın 148 veri tipine göre kontrol edildi |
| Fidelio uyumlu view'lar | PROISV'deki 13 view RS PMS'te **aynı ad ve kolonlarla** (`PRO_ISV_POSTINGS` vb.) |
| Arayüz ve kısayollar | Fidelio Suite8 ile aynı yerleşim, ekran adları ve klavye kısayolları |
| Konferans ve banket | Kapsamda (Suite8 CCM karşılığı, 23 tablo) |
| Muhasebe | Logo **Tiger** |
| POS | Oracle **Simphony 18**; bugün kayıtlar IFC8, oda sorguları doğrudan Fidelio'ya — hedef: ikisi de RS PMS'e |
| Kimlik bildirimi (KBS) | Şu an yok; tablo tasarımda hazır, sonraya bırakıldı |
| Fidelio verisi | Suite8 veritabanına erişim var; geçiş boyunca canlı |

## Kurulum (geliştirme)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # Rocks Hotel tanımları: 277 departman kodu, 15 outlet, vergiler, roller
php artisan pms:create-user MU "M.U" kullanici@ornek.com --role=admin   # şifre sorulur
php artisan pms:import-users storage/app/private/fidelio-users.tsv     # Fidelio kullanıcıları: rastgele şifre, rol, ilk girişte şifre değiştirme
php artisan db:seed --class=DemoDataSeeder   # isteğe bağlı: örnek odalar ve rezervasyonlar (yalnız geliştirme)
npm install && npm run build
php artisan test
```

Giriş: `/giris` — kullanıcı adı (ör. `MU`) ya da e-posta ve şifre.

Testler varsayılan olarak SQLite (bellekte) üzerinde çalışır; MySQL ile denemek için `DB_CONNECTION=mysql` ve bağlantı bilgileri verilir.

## Teknoloji (özet)

Laravel 13 · PHP 8.3 · MySQL 8 · Redis (önbellek, kuyruk, kilit) · Blade + Tailwind 4 — RS ONE ile aynı yığın.
