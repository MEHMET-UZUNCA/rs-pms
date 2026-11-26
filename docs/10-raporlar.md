# 10. Raporlar

**İlke:** Fidelio'daki gibi tek bir rapor ekranı (Miscellaneous → Reports, `Ctrl+Shift+R`), ama yalnız otelin kullandığı raporlar.
Fidelio'nun yüzlerce hazır raporu taşınmaz; aşağıdaki başlangıç seti yapılır, eksik görülen rapor sonra eklenir.

## 10.1 Kaynaklar

| Kaynak | Ne alındı |
|---|---|
| Suite8 Miscellaneous Manual V8.9 — 4 Reports | Ekranın yapısı: rapor bölümleri ve alt bölümler (ağaç), metinle arama, rapor bölümüne git, rapor güvenlik seviyesi, Crystal / HTML / Cube rapor türleri. Hazır raporların listesi kılavuzda yok; otelin Fidelio'sundaki rapor ekranında |
| Suite8 Rooms Management Manual V8.9 | Kat hizmetleri arama sonucu raporu, kat görevlisi (attendant) raporu: oda, oda tipi, durum, kişi sayısı; ileri tarih için de alınabilir |
| Elektraweb yardım — Raporlar, Gün Sonu | Rapor grupları: Durum (anlık), Dağılım analizi (geçmiş yılla karşılaştırma), Doluluk grafikleri, Yönetim raporları (doluluk, detaylı doluluk, özet günlük yönetim raporu); gün sonunda üretilen raporların tarihle geri alınması |
| Elektraweb yardım — Boş Oda Listesi | Süzgeçler: tarih aralığı, oda tipi, özellik, temizlik durumu, kat, manzara; "temiz ve onaylı", "kapalı odalar hariç", "bugün çıkacak dolu odalar hariç" |
| Elektraweb yardım — Kapalı Oda Listesi, HK Oda İşlemleri, Maid Yönetimi, Ön Kasa | Kapalı odalar Geçmiş / Güncel / İptal; uyuşmazlık raporu; kasa: para birimi ve kullanıcı bazında toplamlar, kasa işlemleri detayı |
| Fidelio Reports ekranı (fiyat raporları, 26.11.2026) | C4330, P4473, P4333, P4324, P4329, P4341, P4039, P4326, P4375 → aşağıda "Fiyat" |
| Athena | Bekleniyor (rapor sayfası bağlantısı ya da ekran görüntüsü) |

## 10.2 Başlangıç rapor seti

Her rapor: ekranda görme, yazdırma / PDF, Excel (CSV). Tarih girişi Fidelio gibi (t, +1, 25.11).

### Ön Büro (Front Office)
| # | Rapor | İçerik | Süzgeç |
|---|---|---|---|
| FO1 | Arrivals | Gelecek misafirler: oda, ad, gece, oda tipi, kişi, fiyat kodu ve tutar, acenta/şirket, not | Tarih, oda tipi, acenta, odası atanmamış |
| FO2 | Departures | Çıkacak misafirler ve folyo bakiyesi | Tarih |
| FO3 | In House Guests | İçerideki misafirler oda sırasıyla: ad, uyruk, kişi, giriş-çıkış, fiyat, bakiye | Blok, kat, uyruk |
| FO4 | No Show / Cancellations | Günün no-show ve iptalleri, sebep, kullanıcı | Tarih aralığı |
| FO5 | Guest list by nationality | Uyruğa göre içerideki misafir ve kişi sayısı | Tarih |

### Odalar ve Kat Hizmetleri (Rooms / Housekeeping)
| # | Rapor | İçerik | Süzgeç |
|---|---|---|---|
| HK1 | Free Rooms | Tarih aralığında boş odalar: oda, tip, kat, özellikler, temizlik durumu | Tarih aralığı, oda tipi, kat, özellik, yalnız temiz, OOO hariç |
| HK2 | Room Status by Floor | Kat kat bütün odalar: tip, FO/HK durumu, işaret, misafir, bugün giriş/çıkış, kişi — kat görevlisi listesi | Blok, kat, durum, tarih (ileri tarih de) |
| HK3 | Out of Order / Out of Service | Kapalı odalar: dönem, sebep, açıklama, giren kullanıcı | Güncel / geçmiş |
| HK4 | Room Status History | Odanın durum değişiklikleri (kim, ne zaman) | Oda, tarih aralığı |

### Doluluk (Occupancy)
| # | Rapor | İçerik | Süzgeç |
|---|---|---|---|
| OC1 | Occupancy Forecast | Gün gün: toplam oda, dolu, OOO, boş, doluluk %, giriş, çıkış, kişi; oda tipine göre kırılım | Tarih aralığı (ileri) |
| OC2 | History & Forecast | Geçmiş günler kapanış istatistiğinden (doluluk, ADR, RevPAR, oda geliri), gelecek günler rezervasyondan | Tarih aralığı |
| OC3 | Manager Report | Gün / ay başından / yıl başından: doluluk, satılan oda, ADR, RevPAR, oda ve toplam gelir; geçen yılla karşılaştırma | Tarih |

### Finans (Cashiering)
| # | Rapor | İçerik | Süzgeç |
|---|---|---|---|
| FI1 | Department Code Totals | Departman kodu bazında adet, net, vergi, brüt (Fidelio P4556 benzeri), grup ara toplamları | Tarih aralığı, kod grubu |
| FI2 | Daily Revenue | Gün gün gelir (oda, yiyecek, içecek, diğer), toplam | Tarih aralığı |
| FI3 | Revenue by Room | Oda bazında: her oda için gün gün oda geliri ve ekstra harcamalar | Tarih aralığı, oda tipi, kat |
| FI4 | Posting Journal | Günün bütün kayıtları: saat, oda/hesap, kod, tutar, kullanıcı, düzeltmeler | Tarih, kullanıcı, kod |
| FI5 | Cashier Report | Ödemeler: ödeme şekli ve para birimi bazında toplam, kullanıcı bazında kasa | Tarih, kullanıcı |
| FI6 | Guest Ledger / Open Balances | İçerideki misafirlerin ve hesapların (FA) bakiyeleri | Tarih |
| FI7 | Tax Report | KDV oranına göre matrah ve vergi | Tarih aralığı |

### Fiyat (Rates) — Fidelio'da kullanılan raporlardan (26.11.2026)
| # | Fidelio raporu | RS PMS karşılığı | Veri |
|---|---|---|---|
| RT1 | C4330 Reservations by rate code (daily), alphabetic | Fiyat koduna göre rezervasyonlar (gün gün) | Gece fiyat satırları (`pms_reservation_nights`) |
| RT2 | P4473 / C4473 Forecast occupancy and price (date period), Individual/Group | Doluluk ve ortalama fiyat tahmini, bireysel / grup ayrımı | Gece satırları + bloklar |
| RT3 | P4333 Reservations summary info for A/C/G by Arrival / by Departure date period | Acenta / şirket / grup bazında giriş ya da çıkış tarihine göre özet | Rezervasyon + profil bağlantıları |
| RT4 | P4324 Rate Discrepancy (In House today / date period / creation date) | Fiyat farkı: gece fiyatı fiyat kodundaki fiyattan farklı olanlar | Elle fiyat (`is_rate_overridden`, `manual_amount`) ile hesaplanan fiyat karşılaştırması |
| RT5 | P4329 Rate Discrepancy Detailed (In house / Arrival / Departure) | Fiyat farkı ayrıntılı, gece gece | Aynı |
| RT6 | P4341 Reservation Rate Changes | Rezervasyonda fiyat değişiklikleri (kim, ne zaman, eski / yeni) | Fiyat değişikliği kaydı gerekiyor (`pms_reservation_rate_changes` — eklenecek) |
| RT7 | P4039 Guest in House (date period) Rate Distribution | İçerideki misafirlerin fiyat kodu / fiyat dağılımı | Gece satırları |
| RT8 | P4326 Discount Report for A/C/S (date period) | Acenta / şirket / kaynak bazında indirimler | İndirim kayıtları |
| RT9 | P4375 Blocks by Room Type and Rate Room Type | Blokların oda tipi ve fiyat oda tipi kırılımı | Bloklar (blok ekranı gelince) |

Not: Fidelio'daki "A/C/G" acenta / şirket / grup, "A/C/S" acenta / şirket / kaynak demektir.

## 10.3 Ekran

- Sol: rapor ağacı (Front Office, Rooms / Housekeeping, Occupancy, Cashiering); üstte metinle arama.
- Sağ: seçili raporun süzgeçleri, `F10` göster, yazdır / PDF, Excel.
- Yetki: her rapor grubu için ayrı yetki (ör. finans raporları yalnız muhasebe ve yönetim).
- Gece kapanışında üretilen günün raporları (`report_pack`) tarihle geri açılır; sonradan değişmez.

## 10.4 Otelden beklenen

- Fidelio'da en çok kullanılan raporların adı ve Binary Name'i (Reports ekranından) — başlangıç setinde olmayan rapor varsa eklenir.
- Athena rapor sayfası (bağlantı ya da ekran görüntüsü).
