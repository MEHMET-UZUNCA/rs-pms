# Eksikler ve bekleyen bilgiler

Bekleyen her şey burada tutulur; bilgi gelince satır "Tamam" olur ve ilgili belgeye/koda işlenir.

## Otelden beklenen bilgiler

| # | Ne | Nereden | Ne için | Durum |
|---|---|---|---|---|
| 1 | Department Code Groups ve Main Groups ekranları (grup adları 15, 16, 20, 30, 40, 50, 55, 60, 75, 80, 90, 95) | Fidelio | Grup adları şimdilik geçici (10, 11 = Room Revenue alındı) | Bekleniyor |
| 2 | P4556 raporunun diğer sayfaları (VAT 0 %, VAT 1 %, VAT 10 %, VAT 20 %) | Fidelio raporu | Spa, kuaför, VAT Discount, paket kodlarının vergisini teyit | Bekleniyor |
| 3 | Spa (513), Kuaför (640), 560/561 VAT Discount, 781 Package, 798 Deposit Retain kod pencereleri → "Tax" kutusu | Fidelio | Şimdilik %5 verildi, teyit | Bekleniyor |
| 4 | Simphony EMC → Interfaces #101 Options listesinin devamı (12. seçenekten sonrası: ödeme tutarı / prorate seçenekleri) | Simphony EMC | Dinleyicinin tutar alanlarını doğru okuması | Bekleniyor |
| 5 | Simphony Sales Itemizers (1–16) ve Tax Rates listesi | Simphony EMC | Satış kalemi → departman kodu eşlemesi (ör. Cafe Rocks itemizer 1 → 320) | Bekleniyor |
| 6 | IFC8'deki RVC → departman kodu eşlemesi | IFC8 yapılandırması | RVC numarasının "#" mı "RVC ID" mi olduğunu teyit | Bekleniyor |
| 7 | Reporting & Analytics BI API erişimi (kurum kodu, API kullanıcısı) | Simphony yetkili servisi | Bütün satışların aktarımı | Bekleniyor |
| 8 | Logo Tiger: sürüm, REST lisansı, firma/dönem no, hesap planı eşlemesi | Muhasebe / Logo | Logo aktarımı | Bekleniyor |
| 9 | Rocks Hotel oda tipleri ve oda listesi (Fidelio YCAT/YRMS) | Fidelio | Gerçek oda tanımları (şu an yalnız örnek veri var) | Bekleniyor |
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

## Yapılacak ekranlar ve işler (sırayla)

1. Rezervasyon Kartı (Ctrl+1 … Ctrl+0) — yeni / düzenle
2. Simphony dinleyicisi (Enhanced protokol) + test için ikinci arayüz kaydı (#102)
3. Folyo (Billing) ekranı
4. Vergiler (Tax Details) ekranı ve Departman Kodu Grupları ekranı
5. Oda Planı (Room Rack)
6. Gece kapanışı ekranı
7. Logo aktarımı
8. Fidelio veri aktarımı (PROISV)
