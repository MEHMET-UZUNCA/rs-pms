# 2. Kaynak karşılaştırması

RS PMS üç kaynaktan beslenir. **Kod kopyalanmaz**; veri modeli, iş kuralları ve ekran düzeni örnek alınır.

| Kaynak | Lisans | Ne alınır |
|---|---|---|
| **Fidelio Suite8** | Ticari (Oracle) | Kavramlar ve otel işleyişi: profil tipleri, işlem kodları, folyo pencereleri, yönlendirme, iz (trace), allotman, gece kapanışı sırası. Personelin alışkanlığı. |
| **Kamra PMS** | Apache-2.0 | Mali çekirdek: iş günü, folyo defter kaydı (çift taraflı), gece kapanışı kontrol listesi, cari defter, dış POS harcaması, muhasebe köprüsü |
| **QloApps** | OSL-3.0 | Arayüz düzeni (yönetim paneli, müsaitlik ve oda planı), bağlantılı oda, oda kapatma tarihleri, kayıt anında anlık görüntü |
| OCA/pms (yardımcı) | AGPL-3 | Yalnız fikir: gecelik rezervasyon satırı, pansiyon tipi satırları, oda bölme/birleştirme kuralları |

> **Fidelio veritabanı hakkında:** Suite8'in tablo yapısı Oracle tarafından yayınlanmıyor; elimde güvenilir bir şema yok.
> Aşağıdaki Fidelio sütunu, Suite8'in **işlevsel kavramlarına** dayanıyor. Gerçek tablo/kolon eşlemesi, otelin Suite8 veritabanından
> şema çıktısı alındığında [Fidelio'dan geçiş](06-fidelio-gecis.md) belgesinde yapılacak.

## 2.1 Kavram kavram karşılaştırma

| Kavram | Fidelio Suite8 | Kamra | QloApps | **RS PMS kararı** |
|---|---|---|---|---|
| Tesis | Çok tesis desteği | `Property` | `htl_branch_info` | `pms_properties` |
| Oda tipi | Oda tipi + oda sınıfı | `Room Type` (fiyat alanları içinde) | `htl_room_type` (ürüne bağlı) | `pms_room_types` + `pms_room_classes`; fiyat ayrı tabloda |
| Oda | Oda, kat, bina, özellik, bağlantılı oda | `Room` (`connects_to`) | `htl_room_information`, `htl_connected_room` | `pms_rooms` + `pms_room_connections` (çoklu bağlantı) |
| Oda durumu | Kat durumu (temiz/kirli/kontrol edildi), ön büro durumu, OOO/OOS, uyuşmazlık | `housekeeping_status`, `occupancy_status`, `room_discrepancy` | `htl_room_status`, `htl_room_disable_dates` | `pms_rooms` üzerinde anlık durum + `pms_room_status_logs` + `pms_room_outages` + `pms_room_discrepancies` |
| Profil | Bireysel, şirket, acente, kaynak, grup, iletişim | `Guest`, `Corporate Account`, `Travel Agent` ayrı | PrestaShop `customer` | **Tek tablo** `pms_profiles` + `type`; adres, iletişim, kimlik, tercih, üyelik alt tablolarda |
| Fiyat | Fiyat kodu, fiyat kategorisi, sezon, kişi sayısına göre fiyat, paket | `Rate Plan` (temel fiyata göre +/−), `Season`, `Meal Plan` | `htl_room_type_feature_pricing` (tarih aralığına etki) | `pms_rate_codes` + `pms_rate_amounts` (tip×tarih aralığı×kişi) + `pms_packages` |
| Kısıtlar | Min/maks konaklama, giriş/çıkışa kapalı | `Rate Restriction` | `htl_room_type_restriction_date_range` | `pms_rate_restrictions` (günlük) |
| Müsaitlik | Ev durumu (house status) | `channel_ari_state` | Sorgu ile hesaplanır | `pms_inventory_daily` (oda tipi × gün; satılan, blok, kapalı) — hızlı ekran ve kanal için |
| Rezervasyon | Oda başına rezervasyon, paylaşım (share), bekleme listesi | `Reservation` (oda başına) + `Reservation Night Rate` | `htl_booking_detail` (oda başına, toplam fiyat) | `pms_reservations` (oda başına) + `pms_reservation_nights` (gecelik fiyat ve paket) + `pms_reservation_guests` (paylaşanlar) |
| Grup ve allotman | Grup, blok, allotman (release günü) | `Group Booking`, `Group Room Block` | Yok | `pms_blocks` + `pms_block_inventory` + rezervasyonda `block_id` |
| İz (trace) | Departmana not/görev, tarihli | `Stay Instruction` | Yok | `pms_traces` |
| Sabit ücret | Fixed charges (her gün/tek sefer) | `Stay Addon` | Servis ürünleri | `pms_fixed_charges` |
| İşlem kodu | Departman kodları (gelir/ödeme), grup ve alt grup | `Transaction Code` (`gl_code`) | Yok (ürün/sipariş) | `pms_transaction_codes` + `pms_transaction_code_groups`; Logo hesabı ayrı eşleme tablosunda |
| Folyo | Rezervasyon başına 8 pencere, passer-by, master, PM odası | Rezervasyon başına folyo, tek pencere | Sipariş | `pms_folios` (`window` 1–8, tip: misafir/master/passer-by/PM/cari) |
| Yönlendirme | Routing (işlem koduna göre başka pencere/folyoya) | `Company Billing Rule` | Yok | `pms_routing_rules` |
| Folyo kaydı | Posting (iş günü, kasiyer, ters kayıt, transfer) | `Folio Charge` + `Folio Ledger Entry` (borç/alacak, defter) | Sipariş satırı | `pms_postings` (tek tablo: harcama + ödeme, işaretli tutar) + `pms_posting_taxes` |
| Defter | Guest / Deposit / AR ledger | `ledger`: Guest, Deposit, AR, Package | Yok | `pms_postings.ledger` alanı aynı dört defter |
| Fatura | Fatura numarası serisi, iptal, kopya | `Folio.invoice_number`, `Cancelled Invoice`, `Credit Note` | `order_invoice` | `pms_invoices` + `pms_invoice_lines` + `pms_number_sequences` |
| Kasa | Kasiyer, kasa kapanışı | `Cashier`, `Cashier Session`, `Cash Transfer` | Yok | `pms_cashiers` + `pms_cashier_shifts` + `pms_cashier_shift_totals` |
| Döviz | Döviz bozdurma | `Exchange Rate`, `Exchange Transaction` | Para birimi | `pms_exchange_rates` + döviz işlemi `pms_postings` üzerinden |
| Cari | City ledger, yaşlandırma | `City Ledger Account/Entry` | Yok | `pms_ar_accounts` + `pms_ar_invoices` + `pms_ar_payments` + `pms_ar_allocations` |
| Gece kapanışı | Adım adım kapanış, raporlar | `Night Audit Run` (adım durumu, rapor paketi) | Yok | `pms_business_dates` + `pms_night_audit_runs` + `pms_night_audit_steps` + `pms_daily_statistics` |
| POS | Fidelio/Simphony arayüzü | `POS Outlet`, `External POS Charge` | Yok | `pms_outlets` + `pms_pos_*` tabloları (bkz. Simphony belgesi) |
| Muhasebe | Arka ofis arayüzü | `erpnext_bridge`: iş günü başına tek yevmiye | Yok | `pms_logo_*` tabloları; iş günü başına fiş |

## 2.2 Kamra'dan alınan kurallar

- **İş günü** gece kapanışıyla ilerler; kapanmış güne kayıt yok.
- **Gece kapanışı kontrol listesi:** açık kasa, kapanmamış POS hesabı, çıkışı yapılmamış misafir, no-show adayı, fiyat sapması,
  yüksek bakiye, kredi limiti aşımı, oda durumu uyuşmazlığı. Her kontrol *engeller / uyarır / bilgi verir*.
- **Kapanış rapor paketi** JSON olarak saklanır (o günün yönetici raporu sonradan değişmez).
- **Dış POS harcaması:** oda + rezervasyon doğrulanır, `room_charges_allowed` kapalıysa reddedilir, kayıt sonrası bakiye döner.
- **Muhasebe köprüsü:** iş günü başına tek fiş; her defter kaydının karşı tarafı işlem kodunun hesabı; vergi ayrı satır;
  gönderim kaydı ile aynı gün iki kez gönderilmez; ters kayıtta fiş iptal edilip yeniden gönderilir.

## 2.3 QloApps'ten alınanlar

- **Yönetim paneli düzeni:** solda menü, üstte tesis seçici, liste + filtre + toplu işlem ekranları.
- **Müsaitlik ve rezervasyon ekranı** (Book Now): tarih aralığı + oda tipi + kişi → müsait odalar listesi, tek tıkla sepete.
- **Bağlantılı oda** ve **oda kapatma tarihleri** (sebepli).
- **Anlık görüntü:** rezervasyon/fatura satırına o anki tesis, oda tipi adı ve vergi oranı yazılır.

## 2.4 Kamra ve QloApps'te olup bilerek almadıklarımız

- Kamra'daki yapay zekâ asistanı, MCP, banket, tatil evi (villa) satışı, Hindistan/Suudi vergi paketleri.
- QloApps'teki e-ticaret kalıntıları (kargo, depo, ürün kombinasyonu).
- Kamra'nın `Room Type` içine gömülü fiyat alanları — fiyatlar ayrı tabloda tutulacak (Fidelio gibi).
