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
| 5 | Simphony Sales Itemizers (1–16) ve Tax Rates listesi | Simphony EMC | Satış kalemi → departman kodu eşlemesi (ör. Cafe Rocks itemizer 1 → 320) | Bekleniyor |
| 6 | IFC8'deki RVC → departman kodu eşlemesi | IFC8 yapılandırması | RVC numarasının "#" mı "RVC ID" mi olduğunu teyit | Bekleniyor |
| 7 | Reporting & Analytics BI API erişimi (kurum kodu, API kullanıcısı) | Simphony yetkili servisi | Bütün satışların aktarımı | Bekleniyor |
| 8 | Logo Tiger: sürüm, REST lisansı, firma/dönem no, hesap planı eşlemesi | Muhasebe / Logo | Logo aktarımı | Bekleniyor |
| 9 | Rocks Hotel oda tipleri ve oda listesi (Fidelio YCAT/YRMS) | Fidelio | Gerçek oda tanımları (şu an yalnız örnek veri var) | Bekleniyor |
| 9b | Rezervasyon tipleri, pazar segmentleri, kaynak kodları (Fidelio listeleri) | Fidelio | Kartta seçilen kodlar Fidelio ile aynı olsun (şu an yalnız örnek veride var) | Bekleniyor |
| 10 | Fiyat kodları ve sezon fiyatları | Fidelio | Gerçek fiyat tanımları | Bekleniyor |

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
| PROISV kayıtları 24.11.2026 | Satış kalemi 1/2 → FOOD/BVRG, ödeme tipi → sanal oda + ödeme kodu (docs/simphony-protokol.md §8) |
| IFC8 ayar dosyası SPH.XML | docs/simphony-protokol.md §7 (parolalar depoya konmadı) |
| IFC8 logu M87POS_SPH_IFC1 (26–27.06) ve canlı IFC8 ekranı | docs/simphony-protokol.md — protokolün tamamı |
| Interfaces #101 Options 12–13 (tam liste) | docs/04-simphony.md §4.1 |
| Extension Application ONBURO + misafir penceresi HTML'i | docs/04-simphony.md §4.1 — misafir listesi ve detay cevap biçimi |

## Dil

- Servis hata mesajları (ör. "Girişten önce oda atanmalı", outlet/departman kodu kuralları) henüz yalnız Türkçe; dil dosyasına taşınacak.
- İngilizce terimlerin Fidelio ekranlarıyla birebir aynılığı personelle bir kez gözden geçirilecek (farklı görülen terim lang/en/pms.php'de düzeltilir).

## Yapılacak ekranlar ve işler (sırayla)

1. ~~Rezervasyon Kartı~~ yapıldı (Ctrl+1, 3, 5, 9) — kalan sekmeler: Ctrl+2 envanter, Ctrl+4 paketler, Ctrl+6 özellikler, Ctrl+7 kredi kartı, Ctrl+8 sabit ücretler, Ctrl+0 yemek planı; iptal ve paylaşım (share)
2. ~~Simphony dinleyicisi (Enhanced protokol)~~ yapıldı — kalan: SPH.XML'den satış kalemi eşlemeleri, oda dışı ödemelerin FA'ya yazılması, Simphony'de ikinci arayüz kaydı (#102) ile canlı deneme
3. ~~Folyo (Billing) ekranı~~ yapıldı — kalan: fatura kesme ve yazdırma, folyo bölme (split), yönlendirme kuralları (routing), kasiyer vardiyası, döviz ile ödeme, ödünç/depozito
4. Vergiler (Tax Details) ekranı ve Departman Kodu Grupları ekranı
5. ~~Oda Planı (Room Rack)~~ yapıldı — kalan: sürükle-bırakla oda değiştirme / uzatma, arıza (OOO/OOS) girişi ekranı
6. Gece kapanışı ekranı
7. Logo aktarımı
8. Fidelio veri aktarımı (PROISV)
