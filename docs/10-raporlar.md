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

## 10.3 Ekran

- Sol: rapor ağacı (Front Office, Rooms / Housekeeping, Occupancy, Cashiering); üstte metinle arama.
- Sağ: seçili raporun süzgeçleri, `F10` göster, yazdır / PDF, Excel.
- Yetki: her rapor grubu için ayrı yetki (ör. finans raporları yalnız muhasebe ve yönetim).
- Gece kapanışında üretilen günün raporları (`report_pack`) tarihle geri açılır; sonradan değişmez.

## 10.4 Otelden beklenen

- Fidelio'da en çok kullanılan raporların adı ve Binary Name'i (Reports ekranından) — başlangıç setinde olmayan rapor varsa eklenir.
- Athena rapor sayfası (bağlantı ya da ekran görüntüsü).
