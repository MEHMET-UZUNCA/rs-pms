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
