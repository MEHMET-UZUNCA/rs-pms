# Ekran taslakları

Fidelio Suite8 yerleşiminde hazırlanan ekran taslakları (örnek veri). Canlı, tıklanabilir hali: Design canvas "RS PMS Ekran Taslakları".

Adlandırma: `<ekran no>-<ekran adı>-<sıra no>-<durum>.png` — ekranlar numara sırasıyla, her ekranın durumları kendi içinde sıralı.

## Klasörler

| Klasör | İçerik |
|---|---|
| `goruntuler/` | Ekran görüntüleri (PNG, 2880×1800 — yüksek çözünürlük) |
| `videolar/` | Kullanım videoları (MP4, 1440×900) |
| `kaynak/` | Taslak kaynak dosyaları (`.dc.html`) ve yerel görüntüleyici (`support.js`) |

## 01 — Rezervasyon Navigatörü

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/01-rezervasyon-navigatoru-01-iceridekiler.png` | İlk açılış: içerideki rezervasyonlar (`Alt+1`) |
| `goruntuler/01-rezervasyon-navigatoru-02-bugun-gelecekler-secili.png` | Bugün gelecekler filtresi (`Alt+2`), seçili rezervasyonun alt paneli |
| `videolar/01-rezervasyon-navigatoru-filtreler-ve-secim.mp4` | Filtreler arasında geçiş ve satır seçimi |

## 02 — Rezervasyon Kartı (`Ctrl+1` … `Ctrl+0`)

| Dosya | Sekme |
|---|---|
| `goruntuler/02-rezervasyon-karti-01-rezervasyon.png` | `Ctrl+1` Rezervasyon |
| `goruntuler/02-rezervasyon-karti-02-envanter.png` | `Ctrl+2` Envanter |
| `goruntuler/02-rezervasyon-karti-03-profiller.png` | `Ctrl+3` Profiller |
| `goruntuler/02-rezervasyon-karti-04-paketler.png` | `Ctrl+4` Paketler |
| `goruntuler/02-rezervasyon-karti-05-gece-fiyatlari.png` | `Ctrl+5` Gece fiyatları |
| `goruntuler/02-rezervasyon-karti-06-ozellikler.png` | `Ctrl+6` Özellikler |
| `goruntuler/02-rezervasyon-karti-07-kredi-karti.png` | `Ctrl+7` Kredi kartı |
| `goruntuler/02-rezervasyon-karti-08-sabit-ucretler.png` | `Ctrl+8` Sabit ücretler |
| `goruntuler/02-rezervasyon-karti-09-notlar.png` | `Ctrl+9` Notlar |
| `goruntuler/02-rezervasyon-karti-10-yemek-plani.png` | `Ctrl+0` Yemek planı |
| `videolar/02-rezervasyon-karti-sekmeler-ctrl1-ctrl0.mp4` | Sekmeler arasında sırayla geçiş |

## 03 — Oda Planı (Room Rack, `Ctrl+L`)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/03-oda-plani-01-14-gun.png` | 14 günlük oda planı, durum renkleri, temizlik durumu |
| `videolar/03-oda-plani-gezinme-ve-karta-gecis.mp4` | Navigatörden oda planına geçiş, 7/30/14 gün görünümü, rezervasyon kartına geçiş |

## 04 — Giriş (gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/04-giris-01-pencere.png` | Giriş penceresi: kullanıcı adı (ya da e-posta) ve şifre |

## 05 — Ayarlar → Outlet'ler (gerçek uygulama)

Fidelio ayar listesi düzeni: solda ayar ağacı, ortada hızlı arama + liste, sağda komut düğmeleri.

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/05-outletler-01-liste.png` | 15 outlet, seçili satır (↑/↓ ile gezinme), iş günü durum çubuğunda |
| `goruntuler/05-outletler-02-ayarlar-menusu.png` | Menü çubuğu → Ayarlar |
| `goruntuler/05-outletler-03-duzenle-byblos.png` | Düzenleme: bilgiler ve bağlı departman kodları ("Yalnız seçilenler") |
| `goruntuler/05-outletler-04-yeni-baska-outletteki-kodlar.png` | Yeni outlet: başka outlet'e bağlı kodlar soluk ve seçilemez |
| `goruntuler/05-outletler-05-yeni-dogrulama-hatasi.png` | Aynı kodla ikinci outlet açılamaz |
| `videolar/05-outletler-klavye-ile-kullanim.mp4` | Giriş → Ayarlar menüsü → liste, hızlı arama, `Alt+E` düzenle, RVC no, `F10` kaydet, `Alt+U` yukarı, `Esc` kapat |

## 06 — Ayarlar → Departman Kodları (gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/06-departman-kodlari-01-liste.png` | 271 kod, Fidelio sırasıyla; grup, KDV, outlet sütunları; hızlı arama ve grup süzgeci |
| `goruntuler/06-departman-kodlari-02-duzenle-100-room.png` | Fidelio "Department Codes → Edit" alanları; tarihli vergi; düzeltme kodu 102 |
| `goruntuler/06-departman-kodlari-03-tarihli-vergi-degisikligi.png` | Bir kodun vergisini bir tarihten itibaren değiştirmek (eski satır kapanır, yeni satır eklenir) |

## 07 — Rezervasyon Navigatörü (gerçek uygulama, örnek veriyle)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/07-rezervasyon-navigatoru-01-iceridekiler.png` | `Alt+1` içeridekiler; seçili rezervasyonun ağacı ve bilgi paneli |
| `goruntuler/07-rezervasyon-navigatoru-02-bugun-gelecekler.png` | `Alt+2` bugün gelecekler; oda atanmışlar için `Alt+G` giriş |
| `goruntuler/07-rezervasyon-navigatoru-03-sorgu-sirket.png` | Şirket/acente sorgusu (`F3`), bütün durumlar |
| `goruntuler/07-rezervasyon-navigatoru-04-rezervasyonsuz-hesaplar.png` | `Alt+6` rezervasyonsuz hesaplar (FA) ve son kayıtları |

Örnek veri: `php artisan db:seed --class=DemoDataSeeder` (yalnız geliştirme; gerçek otel verisi değildir).

## 08 — Dil (İngilizce varsayılan, Türkçe)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/08-dil-01-giris-ingilizce.png` | Giriş ekranı İngilizce, dil seçimi |
| `goruntuler/08-dil-02-navigator-ingilizce.png` | Fidelio terimleri: In House, Arrivals, Departures, Check In, Business Date; Front Office menüsü |
| `goruntuler/08-dil-03-navigator-turkce.png` | Aynı ekran Türkçe (üst çubukta TR) |

## 09 — Rezervasyon Kartı (gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/09-rezervasyon-karti-01-yeni-profil-arama.png` | Navigatörde `Ctrl+N` → yeni kart; soyadla profil arama, ya da yeni misafir |
| `goruntuler/09-rezervasyon-karti-02-kaydedildi.png` | `F10` ile kaydedildi: onay no, oda, gece fiyatı ve toplam |
| `goruntuler/09-rezervasyon-karti-03-gece-fiyatlari.png` | `Ctrl+5` gece gece fiyat (Rate Grid) |

## 10 — Folyo (Billing, gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/10-folyo-01-pencere-1-kayit.png` | Navigatörde `Alt+B` → folyo; 8 pencere, `Alt+P` kayıt (510 ×2, %16 KDV), ödeme, düzeltme, transfer, check-out |
| `goruntuler/10-folyo-02-transfer-pencere-2.png` | Seçili kayıt `Alt+T` ile 2. pencereye aktarıldı (`Alt+2`) |

## 11 — Oda Planı (Room Rack, gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/11-oda-plani-01-14-gun.png` | `Ctrl+L` her ekrandan; 14 gün, In House / Expected / OOO çubukları, VC/VD/OC durumları, günlük doluluk ve odasız rezervasyon |
| `goruntuler/11-oda-plani-02-30-gun.png` | 30 günlük görünüm |

## 12 — Gece Kapanışı (Night Audit, gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/12-gece-kapanisi-01-kontroller.png` | Kapanış öncesi kontroller (Fidelio sırası, hepsi Stop): çıkışı yapılmamış misafir, gelmeyen misafir, bakiyeli gelmeyen, süresi dolan ve beklenen hesaplar (FA), açık kasa; amir onayı gerekçesi; adımlar; "Answers for Automatic Night Audit" (Stop / Continue) |
| `goruntuler/12-gece-kapanisi-02-tamamlandi.png` | Kapanış tamamlandı: yeni iş günü, son günün doluluk / ADR / RevPAR / gelir özeti ve departman kodu bazında gelir |

## 13 — Onay Mektubu (Reservation Confirmation Letter, gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/13-onay-mektubu-en.png` | Navigatörde ya da rezervasyon kartında `Alt+L`; Confirmation_Letter_EN şablonu, alanlar rezervasyondan; `Ctrl+P` ile yazdır / PDF'e kaydet |
| `goruntuler/13-onay-mektubu-tr.png` | Aynı mektup Türkçe (üst çubukta EN / TR; dil verilmezse misafir profilinin dili) |

## 14 — Kullanıcılar ve Roller (gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/14-kullanicilar-01-liste-gecici-sifre.png` | Ayarlar → Kullanıcılar: yeni kullanıcı kaydedildi, geçici şifre bir kez gösteriliyor; Alt+P yeni şifre, Alt+R roller |
| `goruntuler/14-kullanicilar-02-rol-yetkileri.png` | Rol düzenleme: yetkiler modüllere göre gruplu, grup başında tümünü seç |

## 15 — Kısayollar (Quick Keys, gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/15-kisayollar-01-ekran.png` | F1: genel kısayollar ve ekranlara özel tuşlar |
| `goruntuler/15-kisayollar-02-menu.png` | Quick Keys menüsü; henüz olmayan ekranlar soluk |

## 16 — Döviz kurları (gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/16-doviz-kurlari-01-liste.png` | TCMB'den çekilen günün kurları; Rate / Cash Rate (−%5) / Ratecode Rate, banka kurları, kaynak seçimi, Alt+F şimdi çek |
| `goruntuler/16-doviz-kurlari-02-kural.png` | Döviz kuralı: dayanak kur, kâr payları, otomatik güncelleme ve elle kur |

## 17 — Fiyat kodları (Rates, gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/17-fiyat-kodlari-01-liste.png` | Kategori ağacı (2027 FIYATLARI …), seçili kodun özeti |
| `goruntuler/17-fiyat-kodlari-02-rate-edit.png` | Rate Edit: başlık alanları ve Rate Details listesi |
| `goruntuler/17-fiyat-kodlari-03-detay-fiyatlar.png` | Rate Details + Prices: dönem, oda tipleri, odalar, günler; Default/1–4 yetişkin, hafta sonu, CA1/CA2 |

## 18 — Lost & Found (gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/18-lost-found-01-liste.png` | Kayıp ve bulunan listeleri, süzgeçler, seçili eşyaların geçmişi |
| `goruntuler/18-lost-found-02-eslesme.png` | Eşleşen kayıp ↔ bulunan; teslim, imha, eşleşmeyi kaldır, not |

## 19 — House Status (gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/19-house-status.png` | Ctrl+H: toplam, hareket, kat hizmetleri, gün sonu — Fidelio penceresiyle aynı bölümler |

## 20 — Rooms Management (gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/20-rooms-01-out-of-order.png` | Out of Order: güncel / planlanan / geçmiş, yeni arıza, kaldır |
| `goruntuler/20-rooms-02-room-history.png` | Room History: odada kalanlar, durum değişiklikleri, arızalar, oda değişiklikleri |
| `goruntuler/20-rooms-03-housekeeping-durum-cubugu.png` | Housekeeping ekranının alt durum çubuğu (Fidelio gibi) |

## Taslakları yerelde açmak

```bash
cd docs/ekranlar/kaynak
php -S 127.0.0.1:8000
```

Tarayıcıda `http://127.0.0.1:8000/01-rezervasyon-navigatoru.dc.html` adresini açın. Ekranlar arasındaki bağlantılar çalışır.

Yeni ekran eklendiğinde: kaynak dosya `kaynak/` altına sıradaki numarayla, görüntü ve videolar aynı numarayla eklenir ve bu tablo güncellenir.
