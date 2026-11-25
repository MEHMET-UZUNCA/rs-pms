# 1. Mimari

## 1.1 Hedef

- Fidelio Suite8'in ön büro, kasa, gece kapanışı, cari (AR), kat hizmetleri işlevlerini karşılamak.
- Muhasebe kaydını üretmek ama **resmi defteri tutmamak**: resmi defter Logo'dadır; PMS her iş günü için Logo'ya fiş gönderir.
- Restoran/bar satışları Simphony'de kalır; PMS, Simphony'den oda hesabına yazılan harcamaları alır ve outlet ciro özetini Logo'ya taşır.
- İleride RS ONE ile tek uygulama olabilecek şekilde kurulmak.

## 1.2 Teknoloji

| Katman | Seçim | Neden |
|---|---|---|
| Uygulama | Laravel 13, PHP 8.3 | RS ONE ile aynı; birleşme kolay |
| Veritabanı | MySQL 8 (InnoDB, utf8mb4) | İstenen; RS ONE ile aynı sunucuda çalışabilir |
| Redis | Önbellek, kuyruk (Horizon), kilitler | Oda atama ve folyo kayıtlarında eşzamanlılık kilidi, gece kapanışı ve Logo/Simphony işleri kuyrukta |
| Arayüz | Blade + Tailwind 4 + Alpine.js; oda planı (tape chart) için ayrı JS bileşeni | RS ONE ile aynı; görünüm QloApps düzeni örnek alınarak |
| Uzun çalışan süreç | Simphony arayüz dinleyicisi (`php artisan pms:simphony-listen`), Supervisor ile | Simphony TCP üzerinden bağlanır |
| Test | PHPUnit, SQLite :memory: + kritik testler MySQL'de | RS ONE ile aynı düzen |

## 1.2.1 Veritabanı yapısı: ilişkisel, tek ana veritabanı

RS PMS **ilişkisel** (MySQL) bir veritabanı kullanır; **dağıtık değildir**.

- Folyo bakiyesi, fatura numarası, oda atama ve gece kapanışı aynı anda birden fazla tabloyu değiştirir. Bunların ya hep birlikte
  ya hiç olmaması (ACID işlem) gerekir; bu tek veritabanında güvenle sağlanır. Dağıtık yapıda aynı garanti çok daha zordur.
- Tek otel (ve ileride birkaç tesis) için veri hacmi tek MySQL sunucusunun rahatça taşıyacağı düzeydedir (19 yıllık Fidelio verisi dahil).

Mantıksal olarak üç veritabanı, aynı MySQL sunucusunda:

| Veritabanı | İçerik | Erişim |
|---|---|---|
| `rs_pms` | Canlı PMS tabloları (`pms_*`) | Uygulama (okuma/yazma) |
| `proisv` | Fidelio uyumlu `PRO_ISV_*` view'ları | Raporlar, dış yazılımlar (yalnız okuma) |
| `rs_pms_archive` | Fidelio'dan alınan 19 yıllık geçmiş | Uygulama ve raporlar (yalnız okuma) |

Ölçek ve süreklilik:
- **Redis:** önbellek, kuyruk (gece kapanışı, Logo, Simphony işleri) ve kilitler.
- **Okuma kopyası (replica):** Rapor yükü artarsa MySQL'in ikinci bir kopyası açılır, raporlar oradan okunur; kayıtlar ana sunucuya yazılır.
- **Yedek:** Günlük tam yedek + ikili günlük (binlog) ile dakika bazında geri dönüş.

## 1.3 Kod düzeni (RS ONE ile aynı)

```
app/Domain/
  Core/          tesis, kullanıcı, yetki, ayarlar, iş günü, döviz, denetim kaydı
  Inventory/     bina, kat, oda tipi, oda, bağlantılı oda, kullanım dışı
  Rates/         fiyat kodu, sezon, fiyat tablosu, paket (pansiyon), kısıtlar, müsaitlik
  Profiles/      misafir, şirket, acente, kaynak, grup profilleri
  Reservations/  rezervasyon, gecelik fiyat, paylaşım, gruplar, allotman, iz (trace)
  Front/         giriş, çıkış, oda atama, oda değiştirme, kimlik bildirimi
  Cashiering/    folyo, pencere, yönlendirme, işlem kodları, kayıtlar, ödeme, fatura, kasa
  Receivables/   cari hesaplar (city ledger), cari faturalar, tahsilat eşleme
  NightAudit/    gece kapanışı adımları, günlük istatistik
  Events/        konferans, toplantı, banket (salon, etkinlik, menü, BEO)
  Housekeeping/  oda durumu, görevler, uyuşmazlıklar, kayıp eşya
  Integrations/
    Simphony/    outlet, eşlemeler, arayüz mesajları
    Logo/        hesap eşlemesi, aktarım partileri
    RsOne/       müşteri eşleşmesi, SPA/restoran harcaması
    Legacy/      Fidelio aktarımı
```

## 1.4 Temel kurallar

1. **Mali kayıt silinmez, değiştirilmez.** Folyo kaydı, ödeme, fatura, cari hareket yalnız *ters kayıt* ile düzeltilir
   (`reversal_of`). Veritabanı seviyesinde `UPDATE/DELETE` yetkisi uygulama kullanıcısından bu tablolar için ileride kısıtlanabilir.
2. **İş günü (business date) takvim gününden ayrıdır.** Her mali kayıt hem `business_date` hem `created_at` taşır.
   İş günü yalnız gece kapanışıyla ilerler. Kapanmış iş gününe kayıt atılamaz.
3. **Para:** `DECIMAL(14,2)`; kur: `DECIMAL(18,8)`; her tutarın yanında para birimi. Tesis ana para birimi (KKTC için TRY) + döviz karşılığı saklanır.
4. **Çok tesis:** Tüm iş tabloları `property_id` taşır; kullanıcı yalnız yetkili olduğu tesisi görür (RS ONE'daki kapsam mantığı).
5. **Eşzamanlılık:** Oda atama, folyo bakiyesi, fatura numarası, gece kapanışı Redis kilidi + veritabanı satır kilidi (`SELECT … FOR UPDATE`) ile.
6. **Tekrar güvenliği (idempotency):** Dışarıdan gelen her kayıt (Simphony, RS ONE, kanal) `idempotency_key` ile; aynı istek iki kez işlenmez.
7. **Denetim:** Her değişiklik `pms_audit_logs`'a kim, ne, eski değer, yeni değer, neden ile yazılır.
8. **Anlık görüntü:** Fatura ve gece kapanışı raporu, o anki adları/oranları kopyalayarak saklar (QloApps'in `htl_booking_detail` yaklaşımı); sonradan ad değişse de geçmiş bozulmaz.

## 1.5 RS ONE ile birleşme kuralları

- Aynı Laravel/PHP sürümü, aynı `app/Domain/*` düzeni, aynı kodlama ve commit kuralları.
- **Tüm PMS tabloları `pms_` önekiyle** adlandırılır. RS ONE'da zaten `users`, `audit_logs`, `settings`, `packages`, `suppliers`, `tasks`
  gibi tablolar var; önek çakışmayı önler, birleşmede tablolar olduğu gibi taşınır.
- Ortak kavramlar şimdiden bağlanır:
  - `pms_properties.rsone_business_id` → RS ONE `businesses`
  - `pms_profiles.rsone_client_id` → RS ONE `clients` (ortak müşteri havuzu)
  - `pms_outlets` → Simphony outlet'leri; RS ONE SPA/restoran departmanları da outlet olarak tanımlanır
- Birleşmeye kadar iki uygulama **API** ile konuşur (RS ONE → PMS: oda hesabına harcama, misafir sorgusu; PMS → RS ONE: konaklayan misafir bilgisi).
- Kullanıcılar: Birleşmeye kadar ayrı `users` tablosu; e-posta aynı tutulur, birleşmede RS ONE kullanıcısına eşlenir.

## 1.6 Aşamalar

| Aşama | Kapsam | Çıktı |
|---|---|---|
| **0** | Tasarım onayı (bu belgeler) | Onaylı veri modeli |
| **1** | Çekirdek + envanter + kodlar + profiller | Tesis, oda, oda tipi, kullanıcı/yetki, profil ekranları |
| **2** | Fiyat + müsaitlik + rezervasyon | Rezervasyon kartı, oda planı (tape chart), müsaitlik ekranı |
| **3** | Ön büro | Giriş/çıkış, oda atama, oda değiştirme, kimlik bildirimi |
| **4** | Kasa | Folyo, pencereler, yönlendirme, ödeme, fatura, kasa vardiyası |
| **5** | Gece kapanışı + raporlar | İş günü, otomatik oda ücreti, günlük istatistik, yönetici raporu |
| **6** | Cari (AR) + Logo aktarımı | Acente/şirket faturaları, günlük fiş Logo'ya |
| **7** | Simphony | Oda hesabına yazma, outlet ciro aktarımı |
| **8** | Kat hizmetleri | Oda durumu, görev atama, telefon ekranı |
| **8b** | Konferans ve banket | Salonlar, etkinlik rezervasyonu, teklif, BEO, menü, faturalama |
| **9** | Fidelio aktarımı + paralel çalışma | Profiller, gelecek rezervasyonlar, açık bakiyeler, arşiv |
| **10** | RS ONE bağlantısı | Ortak müşteri, SPA/restoran harcaması |

Sonraki adımlar (kapsam dışı, ileride): kanal yöneticisi, çevrimiçi rezervasyon motoru, kapı kilidi, santral.
