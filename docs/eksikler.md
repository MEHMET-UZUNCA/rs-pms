# Eksikler ve bekleyen bilgiler

Bekleyen her şey burada tutulur; bilgi gelince satır "Tamam" olur ve ilgili belgeye/koda işlenir.

## Otelden beklenen bilgiler

| # | Ne | Nereden | Ne için | Durum |
|---|---|---|---|---|
| 1 | Department Code Groups ve Main Groups ekranları (grup adları 15, 16, 20, 30, 40, 50, 55, 60, 75, 80, 90, 95) | Fidelio | Grup adları şimdilik geçici (10, 11 = Room Revenue alındı) | Bekleniyor |
| 2 | P4556 raporunun diğer sayfaları (VAT 0 %, VAT 1 %, VAT 10 %, VAT 20 %) | Fidelio raporu | Spa, kuaför, VAT Discount, paket kodlarının vergisini teyit | Bekleniyor |
| 3 | Spa (513), Kuaför (640), 560/561 VAT Discount, 781 Package, 798 Deposit Retain kod pencereleri → "Tax" kutusu | Fidelio | Şimdilik %5 verildi, teyit | Bekleniyor |
| 4b | Simphony satış kalemi (Sales Itemizer) 3, 4, 6, 7, 8 adları — kalem 1 yiyecek, 2 içecek Fidelio kayıtlarından çıkarıldı | Simphony EMC → Descriptors | Az kullanılan kalemlerin departman kodu | Bekleniyor |
| 4c | Simphony ödeme tipleri (Tender/Media) listesi — en az 131, 231, 401, 402, 404, 601 | Simphony EMC | Ödeme tipi adları ve türleri | Bekleniyor |
| 7 | Reporting & Analytics BI API erişimi (kurum kodu, API kullanıcısı) | Simphony yetkili servisi | Bütün satışların aktarımı | Bekleniyor |
| 8 | Logo Tiger: lisanslar alındı (Tiger 3'e geçiş, LObjects var — docs/05-logo.md). Kalan: REST servisi kurulu mu, firma/dönem no, hesap planı eşlemesi | Muhasebe / Logo | Logo aktarımı | Kısmen |
| 9 | Oda sayısı farkı: Room Types ekranında YMT 20 / YMF 35, oda listesinde YMT 21 / YMF 34. Aday: **1308** (listede YMT "Twin"; 1108 ve 1208 YMF) — Fidelio'da 1308'in tipi teyit edilecek | Fidelio Rooms → 1308 | Oda tipi sayıları | Bekleniyor |
| 9c | 2404 tipi ESF (French) ama açıklaması "Twin"; 2405 tipi EST (Twin) ama açıklaması "French" — Fidelio'da yer değiştirmiş olabilir | Fidelio Rooms | Doğru tip / açıklama | Teyit edilecek |
| 9b | Rezervasyon tipleri, pazar segmentleri, kaynak kodları (Fidelio listeleri) | Fidelio | Kartta seçilen kodlar Fidelio ile aynı olsun (şu an yalnız örnek veride var) | Bekleniyor |
| 10 | Fiyat kodları ve fiyatları: 2027 kodları başlıklarıyla girildi (27COMP, 27IND/A başlıkları tam). Kalan: her kodun Rate sekmesi ve Prices (dönem, oda tipi, kişi fiyatları), 27AGY/A ve 27IND/A'daki %15'in anlamı (bağlı fiyat mı?), CA1/CA2 yaş aralıkları, pansiyon paketlerinin içeriği (Package Link Header: ör. Breakfast PP 17 $, Dinner PP 28 $), 2026 ve diğer kategorilerdeki kodlar | Fidelio Rates | Fiyat hesabı | Kısmen |

## Alınanlar

| Ne | Nereye işlendi |
|---|---|
| Department Codes listesi (277 kod) | database/data/fidelio/department-codes.tsv |
| Tax Details, Tax Definition | Seeder (VAT0–VAT20), docs/fidelio/eslesme.md §16 |
| 100 Room / 107 Room Supplement kod pencereleri | Tarihli vergi, Adjustment Dept, grup adı "Room Revenue" |
| P4556 VAT 5 % ve VAT 16 % sayfaları | 510 Laundry, 520 Transfer %16; oda/pansiyon/outlet %5 doğrulandı |
| Simphony RVC listesi (17 RVC) | Outlet'ler, docs/04-simphony.md §4.4 |
| Simphony Interfaces #101 General / Options / Service Host | docs/04-simphony.md §4.1 — Enhanced protokol (ISL MF_ENH), TCP 192.168.200.11:5010 |
| ORACLE_Micros_Simphony_Enh_IFC_19.1.4 paketi (ayar dosyası; ISL'ler şifreli) | docs/04-simphony.md §4.1 |
| RVC numarası teyidi | Simphony mesajında SalesOutlet = EMC "#" (15 = Rocks Patisserie) |
| PROISV kayıtları 24.11.2026 | Satış kalemi 1/2 → FOOD/BVRG, ödeme tipi → sanal oda + ödeme kodu (docs/simphony-protokol.md §8) |
| IFC8 ayar dosyası SPH.XML | docs/simphony-protokol.md §7 (parolalar depoya konmadı) |
| IFC8 logu M87POS_SPH_IFC1 (26–27.06) ve canlı IFC8 ekranı | docs/simphony-protokol.md — protokolün tamamı |
| Fidelio Room Sections, Room Types, Rooms, Room Features | database/data/fidelio/room-types.tsv, rooms.tsv, room-features.tsv — 10 tip, 155 aktif oda, özellikler odalara bağlı |
| Fidelio User Groups (15 grup) | Roller; yetkiler başlangıç önerisi (seeder) |
| Fidelio Housekeeping kodları (9) | pms_hk_status_codes: INS, CL, DI, TU (durum), DND, NL, SO, NS, DBL (işaret) |
| Fidelio Answers for Automatic Night Audit | Gece kapanışı kontrolleri ve Stop / Continue cevapları (NightAuditAnswers), hepsi Stop |
| Fidelio Confirmation Letter (EN, TR) | Onay mektubu (Alt+L), lang/en/letter.php, lang/tr/letter.php |
| Oda blokları teyidi | 1xxx = A BLOK, 2xxx = B BLOK — otel onayladı |
| Fidelio kullanıcı listesi (tamamı) | 26 kullanıcı (pms:import-users) |
| Interfaces #101 Options 12–13 (tam liste) | docs/04-simphony.md §4.1 |
| Extension Application ONBURO + misafir penceresi HTML'i | docs/04-simphony.md §4.1 — misafir listesi ve detay cevap biçimi |

## Kullanıcılar

- 26 Fidelio kullanıcısı `php artisan pms:import-users <dosya>` ile açılır (rastgele geçici şifre, rol, ilk girişte şifre değiştirme zorunlu).
  Kullanıcı listesi ve şifre dosyası depoya konmaz (storage/app/private). Arayüz hesapları, PROTEL, Guest.Selfupdate ve TEST1 açılmadı.
- Rol yetkileri başlangıç önerisidir; Ayarlar → Kullanıcılar / Roller ekranından otelle birlikte gözden geçirilecek.

## Dil

- Servis hata mesajları (ör. "Girişten önce oda atanmalı", outlet/departman kodu kuralları) henüz yalnız Türkçe; dil dosyasına taşınacak.
- İngilizce terimlerin Fidelio ekranlarıyla birebir aynılığı personelle bir kez gözden geçirilecek (farklı görülen terim lang/en/pms.php'de düzeltilir).

## Kat hizmetleri

- Fidelio "Room Status Reason" listesi (OOO/OOS sebepleri) — şimdilik yalnız "OOO" ve "OOS" genel sebepleri var.
- Fidelio ayarı (Global Settings → Reservation 1): girişte, çıkışta ve gece kapanışında dolu odada oda **Dirty**, no-show'da değişmez. Otel önce "gece kapanışı değiştirmez" demişti; ayar Dirty gösterdiği için Fidelio ayarı uygulandı, Ayarlar → Rezervasyon seçeneklerinden "Değişmez" yapılabilir — teyit edilecek.
- Check-out'ta oda Dirty olur, oda değişikliğinde eski oda Dirty olur — uygulama böyle; otel "emin değilim" dedi, Fidelio'daki davranış teyit edilecek.
- Kat görevlisi görev dağıtımı ekranı.

## Döviz kurları

- KKTC Merkez Bankası (mb.gov.ct.tr/kur/gunluk.xml) geliştirme ortamından erişilemedi (yurt dışı IP'lere kapalı görünüyor); otel sunucusunda denenecek. Okuyucu Türkçe etiketleri (Sembol, Birim, Doviz_Alis, Efektif_Satis …) tanıyor; gerçek dosyadan bir örnek alınınca test eklenecek. O zamana kadar kaynak TCMB.
- Fidelio'daki EUP (Pay Euro), USP (Pay US Dollar), UST (USD Travel Agency Curr.) ayrı kurlu sözde dövizler; kullanılıyorsa para birimi olarak açılıp kuralda bankadaki karşılığı (EUR/USD) seçilir.
- Sunucuda zamanlayıcı için cron gerekli: `* * * * * cd /var/www/rs-pms && php artisan schedule:run`.

## Onay mektubu

- Şablondaki üç yazım düzeltmesi ("Reservation Number", "Booked By", "Giriş Saati") otel tarafından onaylandı.
- Yazı tipleri şablondaki gibi (Century Gothic, Century, Calibri, Times New Roman); Windows'ta birebir görünür, bu yazı tipleri olmayan bilgisayarda en yakın yazı tipi kullanılır.
- E-postayla gönderme yapıldı; SMTP bilgilerini otel sunucudaki .env'e girecek (MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS). Girilene kadar "Send by e-mail" kapalı.

## Menü kapsamı (otelin kullandıkları, 26.11.2026)

- Reservation / Front Desk: New Financial Account (yapıldı), Lost & Found (yapıldı), Traces, Queue Rooms, Room Blocking; Group / Block / Waitlist / Quick Reservation kullanımı sorulacak. **Messages + Locator kullanılmayacak** (otel kararı); Simphony kasası mesaj sorduğunda "mesaj yok" cevabı alır.
- Lost & Found eşya grupları ve ortak alanlar başlangıç listesi; otelin kendi listesi gelirse değiştirilecek (şimdilik pms_reason_codes: lost_found_group, public_place).
- Rooms Management: House Status, Out of Order listesi, Room History, Housekeeping alt durum çubuğu (yapıldı). Fidelio'daki kullanıcı tanımlı kayıtlı aramalar yerine sabit süzgeçler (Alt+1–8) var; gerekirse eklenecek. **Attendants ve Maintenance alınmayacak.**
- Diğer Financial Account grupları (PM Accounts dışında) otelden bekleniyor.

## Yapılacak ekranlar ve işler (sırayla)

1. ~~Rezervasyon Kartı~~ yapıldı (Ctrl+1, 3, 5, 9) — kalan sekmeler: Ctrl+2 envanter, Ctrl+4 paketler, Ctrl+6 özellikler, Ctrl+7 kredi kartı, Ctrl+8 sabit ücretler, Ctrl+0 yemek planı; iptal ve paylaşım (share)
2. ~~Simphony dinleyicisi (Enhanced protokol)~~ yapıldı — kalan: SPH.XML'den satış kalemi eşlemeleri, oda dışı ödemelerin FA'ya yazılması, Simphony'de ikinci arayüz kaydı (#102) ile canlı deneme
3. ~~Folyo (Billing) ekranı~~ yapıldı — kalan: fatura kesme ve yazdırma, folyo bölme (split), yönlendirme kuralları (routing), kasiyer vardiyası, döviz ile ödeme, ödünç/depozito
4. Vergiler (Tax Details) ekranı ve Departman Kodu Grupları ekranı
5. ~~Oda Planı (Room Rack)~~ yapıldı — kalan: sürükle-bırakla oda değiştirme / uzatma, arıza (OOO/OOS) girişi ekranı
6. ~~Gece kapanışı ekranı~~ yapıldı — kalan: gece raporlarının yazdırılması (PDF), otomatik (zamanlanmış) kapanış, Logo fişinin kapanışta üretilmesi
7. Logo aktarımı
8. Fidelio veri aktarımı (PROISV)
9. Rapor ekranı (Miscellaneous → Reports) — başlangıç seti docs/10-raporlar.md'de (ön büro, oda/kat hizmetleri, doluluk, finans; 19 rapor)
10. Multiboard (çoklu pansiyon: RO / BB / HB / FB / AI aynı otelde) — altyapı var (pms_packages board tipleri, fiyat kodu → pansiyon, gece kapanışında pansiyon elemanları ayrı kodlara); kalan: rezervasyonda gece bazında pansiyon ve pansiyon değişikliği, kartta Meal Plan sekmesi, mutfak için günlük öğün sayıları raporu, otelin sattığı pansiyon tipleri ve fiyat kodları bekleniyor
