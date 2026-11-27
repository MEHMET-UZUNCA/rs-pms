# 6. Fidelio Suite8'den geçiş

Fidelio'da yaklaşık **19 yıllık** veri var. Tamamı yeni sistemin canlı tablolarına doldurulmaz; amaca göre ayrılır.

## 6.1 Veri nereye gider

| Veri | Hedef | Not |
|---|---|---|
| Misafir profilleri | `pms_profiles` + alt tablolar; telefon/e-posta ile tekilleştirme | RS ONE `clients` ile eşleşme (`rsone_client_id`) |
| Şirket, acente, kaynak profilleri | `pms_profiles` (`type`) + `pms_ar_accounts` | Logo cari kodlarıyla eşleştirilir |
| Fiyat kodları, işlem kodları, segment, kaynak kodları | İlgili tanım tabloları | Kodlar korunur (personel alışkanlığı, raporların sürekliliği) |
| Geçiş tarihinden sonraki rezervasyonlar | `pms_reservations` + `pms_reservation_nights` | Oda, fiyat, garanti, notlar, izler |
| İçerideki misafirler (geçiş anı) | `pms_reservations` (in_house) + açılış bakiyesi kaydı | Folyo bakiyesi tek "devir" kaydı olarak (`source = import`) |
| Açık cari bakiyeler | `pms_ar_invoices` (açık kalemler) | Logo ile karşılaştırılarak |
| Alınmış depozitolar | `pms_postings` (`ledger = deposit`) | |
| Geçmiş konaklamalar, folyolar, faturalar | **Arşiv veritabanı** `rs_pms_archive` (salt okunur) | Misafir kartında "geçmiş konaklamalar" arşivden okunur |
| Konaklama istatistikleri | `pms_profile_stats` (özet) + arşiv | |
| Geçmiş muhasebe | Aktarılmaz | Zaten Logo'da |

## 6.2 Adımlar

1. **Erişim:** Suite8'in Oracle veritabanına salt okunur erişim **mevcut** (otel onayladı). Fidelio geçiş tamamlanana kadar canlı kalır.
2. **Şema çıkarma:** Tablo/kolon listesi ve satır sayıları (`docs/fidelio/` altına konacak). Suite8'in tablo yapısı resmi olarak
   yayınlanmadığı için eşleme tablo içerikleri incelenerek yapılacak; tahmine dayalı eşleme yapılmayacak.
   Daha önce yapılan veri çekme çalışmasının sorguları/çıktıları varsa buraya eklenecek.
3. **Ham kopya:** Gerekli tablolar Oracle'dan MySQL arşiv veritabanına olduğu gibi kopyalanır (`rs_pms_archive.fid_*`).
4. **Eşleme belgesi:** Fidelio tablo/kolon → RS PMS tablo/kolon, kod dönüşümleri, tekilleştirme kuralları.
5. **Deneme aktarımı:** Test sunucusunda; sayım ve tutar karşılaştırması (profil sayısı, gelecek rezervasyon sayısı, açık bakiye toplamı).
6. **Paralel çalışma:** Bir süre iki sistem yan yana; günlük rakamlar karşılaştırılır.
7. **Geçiş gecesi:** Son gece kapanışı Fidelio'da → son farklar aktarılır → RS PMS açılır.

Her aktarım `pms_legacy_import_batches`'e kaydedilir; aktarılan her kayıt `legacy_system = 'fidelio'`, `legacy_id` taşır, tekrar çalıştırmak çift kayıt üretmez.

## 6.3 Kişisel veri

19 yıllık kimlik ve iletişim verisi KKTC kişisel verilerin korunması mevzuatı açısından gözden geçirilmeli:
saklama süresi, aydınlatma metni, pazarlama izni olmayan kayıtların pazarlamada kullanılmaması.
Kimlik numaraları arşivde de şifreli tutulur.

## 6.4 Aktarım komutu (`pms:import-fidelio`)

Kaynak: PROISV view'larının CSV dışa aktarımı, bir klasörde, dosya adı view adı (`PRO_ISV_PROFILEINFO.csv`,
`PRO_ISV_RESERVATIONINFO.csv`, `PRO_ISV_MULTIGUEST_LIST.csv`, `PRO_ISV_POSTINGS.csv`). İlk satır kolon adları; ayraç (`;` `,` sekme `|`)
ve kodlama (UTF-8 değilse Windows-1254) kendiliğinden bulunur. Tarih biçimleri `2026-12-01`, `01.12.2026`, Oracle `01-DEC-26`;
tutarlar `1.234,56` ya da `1234.56`. Klasör sunucuda `storage/app/private/fidelio/` altında tutulur, depoya konmaz (kişisel veri).

```bash
php artisan pms:import-fidelio storage/app/private/fidelio/2026-12-01 --dry-run
```
```bash
php artisan pms:import-fidelio storage/app/private/fidelio/2026-12-01
```

| Seçenek | Anlamı |
|---|---|
| `--dry-run` | Her şey yazılır, rapor çıkar, sonra geri alınır (hiçbir şey kalmaz) |
| `--only=profiles,reservations,guests,balances` | Yalnız bu bölümler |
| `--from=2026-12-01` | Kesim tarihi; bundan önce ayrılan rezervasyonlar geçmiştir, aktarılmaz (varsayılan: iş günü) |
| `--with-cancelled` | İleri tarihli iptalleri de aktar (Reinstate için) |
| `--balance-code=879` | Açık bakiye devri departman kodu |
| `--ledgers=0` | Bakiyeye katılan `POSTING_LEDGERSTATUS` değerleri — deneme aktarımında Suite8 bakiyeleriyle karşılaştırılarak kesinleşir |

Ne yapar:

| Bölüm | Kural |
|---|---|
| Profiller | Kategori 1/5/6 → şirket, 2 → acente, 3 → kaynak, 4 → bireysel; NAME1 bireyselde soyad. Birincil adres, e-posta / telefon / cep / faks / web, kimlik belgesi (şifreli, aynı numara bir kez) ve TC kimlik no. Ülke ve uyruk ISO 2 / ISO 3 / Fidelio kodundan bulunur, dil 3 harften 2 harfe. Durum 0 pasif, 3 sonlandırılmış, 4 birleştirilmiş (hedefi bağlanır) |
| Rezervasyonlar | CALCULATEDSTATUS: 0/1/7 beklenen (RESERVATIONSTATUS 0 → opsiyon), 2/3/4/8 içeride, 10 bekleme listesi, 11 teklif, 12 iptal (`--with-cancelled` ile); 5/6/9 çıkmış ve 13 no-show aktarılmaz (arşiv). Gece satırları gün gün (oda tipi, oda, fiyat kodu, kişi, tutar, elle fiyat, pansiyon); dövizli rezervasyonda fiyat kodu dövizindeki tutar. Onay no = Fidelio rezervasyon no; RS PMS serisi bunun üstünden devam eder. NOOFROOMS > 1 ise oda sayısı kadar rezervasyon, ortak booking. İçerideki misafirin odası dolu yapılır, 1. folyo penceresi açılır |
| Ek misafirler | PRO_ISV_MULTIGUEST_LIST → rezervasyon misafirleri (ana misafir tekrar eklenmez) |
| Açık bakiyeler | Yalnız içerideki misafirler; Fidelio penceresi başına (harcama CDT 1 artı, ödeme CDT 5 eksi) tek devir kaydı, vergisiz, `source = import` |

Kodlar (oda tipi, fiyat kodu, pazar, kaynak, rezervasyon tipi, kanal, VIP) RS PMS'te aynı kodla aranır; farklıysa klasöre `eslesme.json` konur:

```json
{ "rate_codes": { "RACK": "BAR" }, "market_segments": { "IN": "IND" }, "reservation_types": { "6PM": "OPT" }, "countries": { "XX": "CY" } }
```

Oda tipi, fiyat kodu, pazar, kaynak bulunamazsa rezervasyon aktarılmaz ve hata olarak yazılır; rezervasyon tipi bulunamazsa
garantili / opsiyon varsayılanı kullanılır. Rapor (`storage/app/private/imports/fidelio-….txt`, kişisel veri yok): bölüm bölüm okunan / yeni /
güncellenen / atlanan / hatalı sayıları, duruma göre rezervasyon sayısı, gece sayısı ve oda geliri toplamı, Fidelio bakiye toplamı ile aktarılan
bakiye toplamı, RS PMS'te bulunmayan kodlar (kaç kayıtta), ilk 50 hata. Her bölüm `pms_legacy_import_batches`'e, hatalar `pms_legacy_import_errors`'a yazılır.

Tekrar çalıştırmak güvenlidir: profil ve rezervasyon `legacy_id` ile güncellenir, bakiye kaydı bir kez yazılır. RS PMS'te işlem görmüş
(aktarım dışında folyo kaydı olan) rezervasyonun üzerine yazılmaz. Deneme aktarımında rapor rakamları Suite8'in gelen / içeride / bakiye
raporlarıyla karşılaştırılır (6.2 adım 5). PROISV'de olmayanlar (depozitolar, cari açık kalemler, izler, bloklar) geçiş gecesi ayrı devralınır.
