# Değişiklik günlüğü

## Geliştirme

- Uygulama katmanı 1. adım: 34 model; Rocks Hotel tanım verileri (Fidelio'nun 61 departman kodu, 22 indirim kodu asıl kodlarına bağlı, 10 outlet, para birimleri, kur farkı kodu); servisler: boşluksuz numara serisi, vergi hesaplama (fiyata dahil/hariç), fiyat hesaplama (kişi sayısı, çocuk bantları, ek yatak, en dar aralık, haftanın günü, gün tipi, türetilmiş fiyat, yuvarlama, pansiyon paketi), günlük müsaitlik hesabı (arıza, kesin/opsiyon satış, blok), folyo kaydı (harcama, ödeme, ters kayıt, transfer, tekrar güvenliği, kapalı folyo/iş günü kontrolü) — 12 yeni test. Testler SQLite ve MySQL'de geçti. (M.U)

- Fidelio uyumlu PROISV view'ları: 13 view, 391 kolon — Fidelio ile aynı ad, kolon adı, sıra ve kod değerleri (durum 0–13, profil kategorisi 1–6, CDT 1/5, ödeme tipi, cinsiyet…). Kolonların Fidelio ile birebir aynı olduğunu ve kod değerlerini doğrulayan testler; sunucu için ayrı proisv şeması ve yalnız okuma kullanıcısı betiği. Testler SQLite ve MySQL'de geçti. (M.U)

- 8. aşama: dış kimlikler, RS ONE olay kuyruğu, kimlik bildirimi, Fidelio aktarım partileri ve hataları, kanal yöneticisi (bağlantı, eşleme, gönderim kuyruğu), kayıtlı raporlar, bildirimler, webhook — 13 tablo. Tasarım belgesindeki her tablonun veritabanında bulunduğunu doğrulayan test eklendi. Veritabanı tamamlandı: 211 tablo, 604 bağlantı, 10 tetikleyici. Testler SQLite ve MySQL'de geçti. (M.U)

- 7. aşama: Simphony (outlet'ler, arayüz, gelir ve ödeme eşlemeleri, ham mesajlar, oda hesabı kayıtları, günlük satış) ve konferans/toplantı/banket (salonlar, oturma düzeni ve kapasite, salon fiyatı, özellikler, etkinlik tipi ve durumu, rakipler, menüler, ekipman, toplantı paketleri, etkinlik rezervasyonu, teklif, ödeme planı, açık konular, etkinlikler, menü/kalem/görevler) — 32 tablo ve testleri. Tetikleyici kuralları tek sınıfta toplandı (FinancialRecordGuard). Testler SQLite ve MySQL'de geçti. (M.U)

- 6. aşama: cari hesaplar (hesap tipi, hesap, cari faturalar, tahsilatlar, eşleme, ekstre, ihtar), stopaj, acente komisyonları (kod, kural, hesaplanan komisyon), Logo Tiger aktarımı (hesap eşlemesi, aktarım partisi, fiş satırları) — 14 tablo ve testleri. Profil, folyo ve provizyonun cari/komisyon bağlantılarına kısıt eklendi. Cari tahsilat silinemez, Logo fiş satırı değiştirilemez (tetikleyici). Testler SQLite ve MySQL'de geçti. (M.U)

- 5. aşama: gece kapanışı (çalışma, adımlar, kontrol listesi), günlük istatistik (Fidelio gelir kovaları ve yatak bazlı sayılar), günlük gelir, defter dengesi; kat hizmetleri (bölüm, görevli, görev tipi, görev, durum geçmişi, uyuşmazlık, kayıp eşya); suit bileşenleri, servis talepleri ve geçmişi, kart anahtar, sanal dahili no; çamaşır; bütçe, elle istatistik, vardiya defteri, pazarlama kodları, ek dosyalar; kontrol listeleri — 30 tablo ve testleri. Testler SQLite ve MySQL'de geçti. (M.U)

- 4. aşama: kasa — folyo (8 pencere), yönlendirme, folyo kayıtları, vergi kırılımı, fatura ve satırları, kasiyer/terminal/vardiya ve sayım, vergi muafiyeti, provizyon/cari onayı, onay istekleri, para aktarımı, döviz bozdurma, teminat depozitosu, ödeme bağlantısı, ürünler, belge şablonları ve geçmişi, hediye çekleri — 25 tablo ve testleri. Mali kayıtların silinmesi ve değiştirilmesi veritabanı tetikleyicileriyle engellendi (yalnız ters kayıt). Testler SQLite ve MySQL'de geçti. (M.U)

- 3. aşama: rezervasyon tabloları — blok/allotman, çok odalı rezervasyon, rezervasyon, gece satırları, ek misafirler, paketler, durum ve oda değişikliği geçmişi, sabit ücretler, depozito talepleri, kart anahtarları, izler, uyarılar, gece promosyonları, indirim bağlantıları, çevrimiçi giriş; rezervasyon hizmetleri (ikram, transfer, ödünç eşya, mesaj, uyandırma, bagaj) — 27 tablo ve testleri. Odanın içerideki rezervasyonu bağlantısına kısıt eklendi. Testler SQLite ve MySQL'de geçti. (M.U)

- Ekran taslakları (Rezervasyon Navigatörü, Rezervasyon Kartı, Oda Planı) docs/ekranlar altına düzenli adlarla kaydedildi: 13 ekran görüntüsü, 3 kullanım videosu, kaynak dosyalar ve yerel görüntüleyici. (M.U)

- 2. aşama: işlem kodları (Fidelio departman kodları), fiyat kodları, sezonlar, gün tipleri, fiyat tablosu, paketler, iptal/depozito politikaları, kısıtlar, günlük müsaitlik, overbooking, promosyon ve indirim kuralları, fiyat sınırları, kaçan talepler — 27 tablo ve testleri. 1. aşamadaki profil/fiyat bağlantılarına kısıt eklendi. Testler SQLite ve MySQL'de geçti. (M.U)

- Kamra'nın 148 veri tipi tek tek karşılaştırıldı; 26 tablo ve ek alanlar eklendi (onay istekleri, kasa terminali ve para aktarımı, döviz bozdurma, teminat depozitosu, stopaj, ödeme bağlantısı, çevrimiçi giriş, fiyat sınırları, kanal yöneticisi, çamaşır, kontrol listeleri, kayıtlı raporlar, bildirimler, webhook). Toplam 212 tablo. Profil tablosuna fotoğraf ve izin kaynağı eklendi. Arayüz belgesi Fidelio Suite8 yerleşimi ve klavye kısayollarıyla yeniden yazıldı. (M.U)

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
