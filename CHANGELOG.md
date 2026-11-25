# Değişiklik günlüğü

## Geliştirme

- 1. aşama: Laravel 13 projesi kuruldu; çekirdek, oda envanteri, kodlar ve profiller için 43 tablo (migration) ve şema testleri eklendi. Testler SQLite ve MySQL (MariaDB) üzerinde geçti. Veritabanı yapısı (ilişkisel, tek ana veritabanı) mimari belgesine eklendi. (M.U)

## Tasarım

- Konferans/toplantı/banket modülü eklendi (23 tablo). Raporlama view'ları Fidelio PROISV ile aynı ad ve kolonlara çevrildi (13 view). Toplam 186 tablo. (M.U)

- Veritabanı kapsam kontrolü: canlı PROISV view'larının 391 kolonu tek tek eşlendi (kolon-kolon.md); Suite8 kılavuzlarındaki işlevler tarandı (08-kapsam-kontrolu.md); 40 yeni tablo ve yeni alanlar eklendi. Toplam 163 tablo. (M.U)

- Fidelio: PROISV postings view'ında olmayan 12 kolon ve etkisi belgelendi. (M.U)

- Fidelio aktarım kaynağı PROISV şemasındaki 13 view olarak belirlendi; PROISV'de olmayan veriler listelendi. (M.U)

- Veritabanı tasarımı Fidelio view'ları ve canlı kodlara göre düzenlendi: 15 ekleme (profil tipleri, kanal kodları, promosyon, indirim kuralları, kur farkı, gecelik brüt/net ve döviz, kimlik alanları, gelir kovaları, folyo ilk sahip alanları), Fidelio karşılıkları işaretlendi, raporlama view'ları eklendi. Toplam 121 tablo. (M.U)

- Fidelio: canlı departman kodları, ödeme tipleri ve outlet'ler belgelendi; rebate'in düzeltme değil indirim olduğu düzeltildi. (M.U)

- Fidelio: PRO_ISV_POSTINGS, döviz ve doluluk view'ları eşlendi; Suite8 tablo adları (YRES, YDET, YRMS, YCAT, YCAD, YBLD, ZCUR, ZRDF, WDAT) belgelendi. (M.U)

- Fidelio PROISV view'ları incelendi; view → RS PMS eşleme belgesi ve önerilen tasarım eklemeleri yazıldı (onay bekliyor). (M.U)

- Onaylanan kararlar işlendi: Logo Tiger, Simphony 18 (kayıt IFC8, sorgu doğrudan), KBS sonraya, Fidelio erişimi mevcut. (M.U)

- Mimari, kaynak karşılaştırması (Fidelio, Kamra, QloApps), veritabanı tasarımı (115 tablo), Simphony entegrasyonu,
  Logo aktarımı, Fidelio'dan geçiş ve arayüz belgeleri eklendi. Onay bekliyor. (M.U)
