# Fidelio (PROISV view'ları) → RS PMS eşlemesi

Gösterim: `VIEW.KOLON` → `rs_pms_tablo.kolon`. **(Ö)** işaretli satırlar mevcut tasarımda karşılığı olmayan, eklenmesi önerilen alanlardır (onay bekliyor).

## 1. PRO_ISV_PROFILEINFO → pms_profiles (+ alt tablolar)

Profil başına bir satır; adres/iletişim bilgilerinin yalnız birincil olanı gelir.

| Fidelio | RS PMS | Dönüşüm |
|---|---|---|
| PROFILE_ID | `pms_profiles.legacy_id` (`legacy_system = fidelio`) | |
| PROFILE_INTERNALCATEGORY | `pms_profiles.type` | 1→company, 2→travel_agent, 3→source, 4→individual, 5→vendor **(Ö)**, 6→meeting_planner **(Ö)** |
| GUESTFULLNAME | `display_name` | |
| NAME1 / NAME2 / NAME3 | bireysel: `last_name` / `first_name` / `middle_name`; şirket: `company_name` | Fidelio'da NAME1 = soyad (bireysel) |
| ADDRESSGREETING, LETTERGREETING | `salutation`, `letter_greeting` **(Ö)** | Mektup/e-posta hitabı |
| SEX | `gender` | 1→male, 2→female, 3→null |
| PROFILELANGUAGE | `language_code` | 3 harf → 2 harf tablo ile |
| BIRTHDATE | `birth_date` | |
| STREET1..3, CITY, ZIPCODE, STATE_*, COUNTRY | `pms_profile_addresses` (`is_primary = 1`) | STREET3 → `line3` **(Ö)**, eyalet → `state` **(Ö)** |
| NATION | `nationality_code` | Fidelio 3 harf → ISO 2 harf tablo ile |
| EMAIL, TELEPHONE, FAX | `pms_profile_communications` | Telefon E.164'e normalize |
| VIPCODE | `vip_level_id` | Kod tablosu |
| TAXOFFICE | `tax_office` | |
| CREATIONDATE, CREATED_USER | `created_at`, `legacy_created_by` | |
| ACTIVE_STATUS | `status` / `merged_into_id` | 1→active, 0→inactive, 4→birleştirilmiş (hedef profil ayrıca bulunmalı) |
| LASTUPDATETIME | `updated_at` | |

## 2. PRO_ISV_IND_FAM_LINKS → pms_profile_relations

| Fidelio | RS PMS |
|---|---|
| PROFILE_ID, LINKED_PROFILE_ID | `profile_id`, `related_profile_id` |
| LINK_TYPE | `relation` (değer listesi gerçek veriden çıkarılacak) |

## 3. PRO_ISV_MEMBERCARDINFO → pms_profile_memberships

| Fidelio | RS PMS |
|---|---|
| CARDROLE | `role` **(Ö)**: 1 ar_number, 2 iata, 3 vat, 4 loyalty, 5 corporate_id |
| SHORTDESC / LONGDESC | `program` |
| CARDVALUE | `number` |

CARDROLE 2 (IATA) → ayrıca `pms_profiles.iata_code`; 3 (vergi no) → `pms_profiles.tax_number`; 1 (A/R no) → `pms_ar_accounts.account_number` eşleşmesi.

## 4. PRO_ISV_RESERVATIONINFO → pms_reservations + pms_reservation_nights

Rezervasyon × gün satırı. Başlık alanları rezervasyona, gün alanları gecelik satıra gider.

| Fidelio | RS PMS | Not |
|---|---|---|
| RESERVATION_ID | `pms_reservations.legacy_id` | |
| ARRIVALDATE, DEPARTUREDATE | `arrival_date`, `departure_date` | |
| ORIGINAL_DEPARTUREDATE | `original_departure_date` **(Ö)** | Erken çıkış / uzatma raporu |
| EXPECTED_ARRIVALTIME / EXPECTED_DEPARTURETIME | `eta`, `etd` | |
| ACTUAL_ARRIVALTIME / ACTUAL_DEPARTURETIME | `checked_in_at`, `checked_out_at` | |
| RESERVATIONSTATUS + CALCULATEDSTATUS | `status` + `reservation_type_id` | 10→waitlist, 11→inquiry (teklif), 12→cancelled, 13→no_show, 2/3/4→in_house, 5/6→checked_out, 7–9 → `is_day_use` |
| NOOFROOMS | — | Suite8'de çok odalı satır olabilir; >1 ise oda sayısı kadar `pms_reservations` + ortak `pms_bookings` |
| GUESTNAME / PROFILE_ID / PROFILE_TYPE | `profile_id` | PROFILE_TYPE 1 ise şirket adına açılmış rezervasyon |
| AGENTNAME, COMPANYNAME, SOURCENAME, GROUPNAME | `agent_profile_id`, `company_profile_id`, `source_profile_id`, `group_profile_id` | View ad veriyor; ID için ek view gerekli (bkz. 9) |
| BOOKER | `pms_bookings.booker_name` | |
| SHARENUMBER | `share_group_id` | |
| CRSRESNR | `external_reference` | |
| RATECODE, MARKETCODE, SOURCECODE | `rate_code_id`, `market_segment_id`, `source_code_id` | Kodlar aynen korunur |
| CHANNELCODE | `channel_code_id` **(Ö)** | Suite8'de kanal ayrı kod listesi; tasarımdaki serbest metin `channel` yerine tablo önerilir |
| CURRENCY, EXCHANGERATE | `currency_code`, gecelik `exchange_rate` **(Ö)** | |
| VIPCODE | `vip_level_id` | |
| CH, CHTEXT | `is_complimentary` / `is_house_use` | |
| GUARANTEECODE | `reservation_type_id` / `guarantee_type` | |
| CREDITLIMIT | `credit_limit` | |
| BILLINGINFO | `payment_instructions` | |
| REMARKS | `guest_comments` | |
| POSDISCOUNT | `pos_discount_code` **(Ö)** | Simphony'de misafire otomatik uygulanacak indirim — Simphony entegrasyonunda kullanılmalı |
| SALEDATE | `sale_date` **(Ö)** | Satış tarihi (erken rezervasyon/rapor) |
| CREATED_DATE, CREATED_USER(NAME) | `created_at`, `legacy_created_by` | |
| LASTUPDATETIME, LASTUPDATE_USERNAME | `updated_at` | |
| **Gün alanları** | | |
| DETAIL_ID, DETAIL_DATE | `pms_reservation_nights.legacy_id`, `stay_date` | |
| ROOMNUMBER | `pms_reservation_nights.room_id` (+ son gün → `pms_reservations.room_id`) | |
| ROOMTYPE / RATEROOMTYPE | `room_type_id` / `charge_room_type_id` | Fiyat oda tipi tasarımda rezervasyonda; **gecelik satıra da** eklenmeli **(Ö)** |
| NOOFADULTS, CHILDREN, FREECHILD, CA1–3, OTHERCA | `adults`, `children` + `child_band_1..3` sayıları **(Ö)**, `infants` | Çocuk bantları Suite8 ile aynı mantık |
| RATECODEAMOUNT / NETRATECODEAMOUNT | `amount` / `net_amount` **(Ö)** | Brüt ve net ayrı saklanmalı |
| FOREIGN* tutarlar | `foreign_amount` **(Ö)** | Döviz fiyatlı rezervasyonlar |
| POSTAMOUNT / NETPOSTAMOUNT | Geçmiş geceler: `pms_postings` (arşivden) | Gelecek gecelerde boş |
| PACKAGEAMOUNT | `package_amount` | |
| RATECODE_BOARDTYPE(TEXT) | `pms_rate_codes.package_id` | |
| RATEDIFFREASON | `override_reason_code_id` | |

## 5. PRO_ISV_RESERVATIONREVENUE → pms_daily_revenue / istatistik / arşiv

Rezervasyon (veya **Financial Account** — Suite8'de rezervasyonsuz hesap, RS PMS'te `passer_by` / `house_account` folyosu) × gün gelir özeti.

| Fidelio | RS PMS |
|---|---|
| GROSSROOM / NETROOM | `revenue_bucket = room` |
| GROSSFB / NETFB, GROSSBREAKFAST, GROSSLUNCH, GROSSDINNER | `fnb`; kahvaltı/öğle/akşam ayrı kova **(Ö)**: `breakfast`, `lunch`, `dinner` |
| GROSSMINIBAR | `minibar` **(Ö)** |
| GROSSTELEPHONE | `telephone` **(Ö)** |
| GROSSMISC | `other` |
| ROOMTYPE_SEGMENT | `pms_room_classes` (Suite8 "hotel segment") |

Kullanım: 19 yıllık geçmiş istatistik (`pms_daily_statistics` geçmişi, `pms_profile_stats`) ve paralel çalışmada günlük gelir karşılaştırması.

## 6. PRO_ISV_PROMOTIONS → yeni tablolar **(Ö)**

Tasarımda promosyon yok. Önerilen:
```
pms_promotions:              id, property_id, code, name, rule_type (free_nights / percent / amount),
                             rule_code, rule_name, valid_from, valid_to, booking_from, booking_to, is_active
pms_reservation_night_promotions: id, reservation_night_id FK, promotion_id FK, amount, foreign_amount
```

## 7. PRO_ISV_MULTIGUEST_LIST → pms_reservation_guests

`RESERVATION_ID`, `MULTIGUEST_PROFILE_ID` → `reservation_id`, `profile_id` (`role = accompanying`).

## 8. PRO_GIYKIMBIL (KBS listesi) → kimlik bilgileri

KBS şimdilik yok, ama Fidelio'da veri tutuluyor; kaybolmaması için alanlar tasarıma eklenmeli **(Ö)**.

| Fidelio | RS PMS |
|---|---|
| TCKIMLIKNO, KIMLIKBELGESITURU, KIMLIKSERINO | `pms_profile_identities.document_number`, `document_type`, `serial_number` **(Ö)** |
| SOYADI, ADI, DOGUMYERI, DOGUMTARIHI, UYRUGU, CINSIYET | `pms_profiles` |
| BABAADI, ANAADI | `father_name`, `mother_name` **(Ö)** |
| NUFUSAKAYITLIOLDUGUIL/ILCE/MAHALLE, NUFUSCILT, NUFUSAILESIRA, NUFUSSIRANO | `pms_profile_identities.registry` JSON **(Ö)** |
| MEDENIHALI, ISI | `marital_status`, `occupation` **(Ö)** |
| IKAMETADRESI | `pms_profile_addresses` |
| GELISTARIHI, AYRILISTARIHI, VERILENODANO | rezervasyondan |
| ARACPLAKANO | `pms_reservations.vehicle_plate` **(Ö)** |
| RESORT | `property_id` |

## 9. Diğer view'lar

| View | Kullanım |
|---|---|
| PRO_ISV_DAILY_ROOMBED_RMTYP | Oda tipi × gün oda/yatak sayısı → geçmiş `pms_daily_statistics.rooms_available` |
| PRO_ISV_OOOROOMBEDS_ROOMTYPE | Oda tipi × gün OOO/OOS → geçmiş `rooms_ooo` |
| PRO_ISV_VIRTUALNUMBERS | Santral sanal dahili numara — santral entegrasyonuna kadar kapsam dışı |
| PRO_ISV_SAMPLE_INHOUSE | Örnek sorgu, geçersiz (INVALID); kullanılmaz |

## 10. Bu view'larda OLMAYAN, geçiş için gerekli veriler

Zip'teki 12 view profil, rezervasyon ve gelir özetini kapsıyor. Aşağıdakiler için ek view (ya da Suite8 tablosu) gerekli:

| Veri | Neden gerekli |
|---|---|
| **Folyo kayıtları (posting)** — tek tek, işlem kodu, kasiyer, tarih | İçerideki misafirlerin açık bakiyesi, arşivde misafir geçmişi |
| **Faturalar** ve fatura satırları | Arşiv, mükerrer numara engeli |
| **Cari hesaplar (A/R)**, açık kalemler | Açılış bakiyeleri (Logo ile mutabakat) |
| **Depozitolar** | Gelecek rezervasyonlarda alınmış avanslar |
| **Tanımlar:** oda tipleri, odalar, fiyat kodları ve fiyat tablosu, paketler, işlem (departman) kodları, market/source/channel/VIP kodları, ülke/milliyet kodları | Kurulumun Fidelio kodlarıyla aynı yapılması |
| Rezervasyon → acente/şirket/kaynak/grup **profil ID'leri** | View yalnız ad veriyor; doğru eşleme için ID gerekli |
| Allotman / bloklar | Gelecek allotmanlar |
| İzler (traces), uyarılar, sabit ücretler, yönlendirme kuralları | Gelecek rezervasyonların eksiksiz aktarımı |

---

## 11. PRO_ISV_POSTINGS → pms_postings (folyo kayıtları)

Kayıt başına bir satır. Yapı, RS PMS'teki folyo tasarımını doğruluyor: **pencere**, **pencereyi ödeyen profil**, **iş günü ile sistem tarihinin ayrı tutulması**, **ters kayıt (rebate)** ve **transferde orijinal kaynağın saklanması** Suite8'de de aynı.

| Fidelio | RS PMS | Not |
|---|---|---|
| POSTING_ID | `pms_postings.legacy_id` **(Ö)** | Arşiv ve açık bakiye devri için |
| POSTING_DATE | `business_date` | Fidelio iş günü |
| POSTING_PCDATE | `created_at` | Bilgisayar saati |
| CASHIER_ID / CASHIER_NUMBER / CASHIER_INITIALS | `cashier_shift_id` (→ `pms_cashiers.number`), `posted_by` | |
| POSTING_DC_ID / POSTING_DC_NUMBER / POSTING_DC_DESCRIPTION | `transaction_code_id` (`code` = DC_NUMBER), `description` | **DC = departman kodu** (işlem kodu); numaralar aynen korunacak |
| POSTING_QUANTITY | `quantity` | |
| POSTING_UNITPRICE / POSTING_GROSSUNITPRICE | `unit_price` (net) / `gross_unit_price` **(Ö)** | |
| POSTING_SUMUPAMOUNT | `amount` | İşaret kuralı `CDT` ile doğrulanacak |
| POSTING_FOREIGNPRICE / GROSSFOREIGNPRICE / FOREIGNSUMUPAMOUNT | `foreign_amount` | |
| POSTING_CURRENCY / POSTING_CURRENCY_ID / POSTING_EXCHRATE | `foreign_currency`, `exchange_rate` | |
| BASECURRENCY | `pms_properties.base_currency` | |
| CDT | `kind` (charge / payment) | **Değer listesi gerekli** |
| PAYMENT_TYPE | `pms_transaction_codes.payment_kind` | **Değer listesi gerekli** |
| POSTING_LEDGERSTATUS | `ledger` (guest / deposit / ar / …) | **Değer listesi gerekli** |
| POSTING_WINDOW_ID / WINDOW_NUMBER | `folio_id` (`pms_folios.window`) | |
| WINDOW_INVOICE_TO_PROFILE_ID / _NAME | `pms_folios.payee_profile_id` | |
| POSTING_RES_ID (+ ACT/EXP ARR/DEP, ROOMNUMBER) | `reservation_id`, `room_id` | |
| ROOMNUMBER | `room_id` (kayıt anındaki oda) | |
| POSTING_FA_ID / FA_LONGDESC / FA_*VALID* | `pms_folios` (`type = passer_by / house_account / master`) | **Financial Account** = rezervasyonsuz hesap. Geçerlilik tarihleri için `pms_folios.name`, `valid_from`, `valid_until` **(Ö)** |
| POSTING_PROFILE_ID | `pms_postings.payee` (folyodan) | |
| POSTING_ORG_RES_ID / ORG_FA_ID / ORG_PROFILE_ID | `transfer_from_id` zinciri; ek olarak `origin_reservation_id` **(Ö)** | Transfer edilen kaydın ilk sahibi — Suite8'de doğrudan tutuluyor, raporlarda gerekli |
| ORG_MARKETCODE_* | `market_segment_id` **(Ö)** kayıtta | Gelirin hangi segmentten geldiği, transferden sonra da korunur |
| AGENCY_ID / COMPANY_ID (+ ad) | rezervasyondan | |
| POSTING_RESSTATUS | — | Bilgi amaçlı |
| POSTING_COMMENT | `note` | |
| REBATING_POSTING_ID | `discount_of_id` **(Ö)** | **Düzeltme değil, indirim:** Suite8'de *rebate*, rezervasyona bağlanan indirim kuralıdır (Cashier Manual). Bu alan, indirimin uygulandığı asıl kaydı gösterir |
| ZRDF_SHORTDESC / ZRDF_LONGDESC | `pms_rebate_codes` **(Ö)** | Rebate (indirim) tanımı; ör. konaklamada %10, yiyecek-içecekte %8, "3 kez ücretsiz" |

Görünmeyen ama gerekli: **fatura numarası** (kayıt hangi faturada) — fatura tablosundan alınacak.

### 11.1 PROISV sürümünde olmayan kolonlar

Aktarım kaynağı **PROISV**; oradaki `PRO_ISV_POSTINGS` 45 kolon, V8LIVE sürümü 57 kolon. PROISV'de **olmayanlar** ve etkisi:

| Eksik kolon | Etkisi | Çözüm |
|---|---|---|
| POSTING_GROSSUNITPRICE, POSTING_GROSSFOREIGNPRICE | Birim fiyatın brütü yok | Brüt = `POSTING_SUMUPAMOUNT / POSTING_QUANTITY`; vergi oranı departman kodundan |
| WINDOW_NUMBER | Pencere numarası (1–8) doğrudan yok, yalnız `POSTING_WINDOW_ID` var | Arşiv için pencere kimliği yeterli. Açık folyolar geçiş gecesi Suite8 raporundan pencere bazında devralınır |
| WINDOW_INVOICE_TO_PROFILE_ID / _NAME | Pencereyi kimin ödediği yok | Arşivde boş kalır; açık folyolarda geçiş gecesi girilir |
| ORG_MARKETCODE_* | Kaydın segmenti yok | Rezervasyonun segmenti kullanılır (`PRO_ISV_RESERVATIONINFO.MARKETCODE`) |
| POSTING_PROFILE_NATION, ORG_PROFILE_NATION | Milliyet yok | Profilden alınır |
| REBATING_POSTING_ID, ZRDF_* | İndirimin hangi kayda ait olduğu ve rebate kodu yok | İndirimler zaten ayrı **DISC** departman kodlarında; tutarlar doğru aktarılır, yalnız asıl kayda bağlantı kurulmaz |

Sonuç: 19 yıllık geçmişin **arşivi ve gelir raporları** PROISV ile eksiksiz alınır. **Geçiş anındaki açık folyolar** (pencere ve ödeyen bilgisiyle) geçiş gecesi Suite8'den devralınır — tasarımda zaten böyle planlandı (06-fidelio-gecis.md).

## 12. PRO_ISV_ZCUR ve PRO_EXCHANGERATES → döviz

| Fidelio | RS PMS |
|---|---|
| ZCUR_ID / SHORTDESC / LONGDESC / ISO3 | `pms_currencies.code` (ISO3), `name`, `legacy_id` |
| ZCUR_DECDIGITS / ZCUR_ACTIVE / ZCUR_DISPLORDER | `decimals`, `is_active`, `sort` **(Ö)** |
| ZCUR_MONTHLY | `rate_period` **(Ö)** (daily / monthly) — Suite8'de bazı dövizler aylık kurla çalışıyor |
| CURRENT_RATE | son `pms_exchange_rates.posting_rate` |
| WDAT_DATE + EXCH_RATE_DAY / MONTH / YEAR | `pms_exchange_rates` (`business_date`, `posting_rate`); aylık/yıllık kur → `rate_type` **(Ö)** |
| LYEAR_* | Hesaplanır (geçen yıl karşılaştırması) |

## 13. Doluluk ve gelir özet view'ları → geçmiş istatistik

| View | Ne verir | RS PMS |
|---|---|---|
| PRO_YEARVIEW_NEW | Gün bazında ev durumu: oda/yatak, OOO/OOS, kesin/opsiyon satılan oda, blok (alınan/kalan), ücretsiz/ev kullanımı, yetişkin/çocuk, gelen/giden kırılımı | `pms_daily_statistics` (geçmiş, `dimension = total`) ve paralel çalışmada `pms_inventory_daily` ile karşılaştırma |
| PRO_YRMS_BEDS_COUNT_BY_YCAT | Oda tipi × gün: oda sayısı, kesin/opsiyon blok, kesin/opsiyon doluluk, günübirlik | `pms_daily_statistics` (`dimension = room_type`) |
| PRO_DAILY_NOF_ROOM_BED_YCAT | Oda tipi × gün oda/yatak sayısı | `rooms_available` |
| PRO_OOOROOMBEDS_YCAT | Oda tipi × gün OOO/OOS; `YCAT_NOTINAVL` | `rooms_ooo`; `YCAT_NOTINAVL = 1` → `pms_room_types.is_pseudo` |
| PRO_DAILYROOMRATETOTAL | Gün bazında oda geliri: pansiyon (`PENSION_*`) ve paket kırılımı (`BREAKDOWNTOTAL_*`), brüt/net, ücretsiz/ev kullanımı, garantili | Geçmiş `room_revenue`; pansiyon/kırılım ayrımı RS PMS'teki *oda geliri = fiyat − paket elemanları* kuralını doğruluyor |

Bu view'lar Suite8'in **opsiyon (tentative) ve kesin (definite)** ayrımını envanterde ayrı tuttuğunu gösteriyor; RS PMS'te `sold_definite` / `sold_tentative` var, bloklar için de ayrım önerilir: `block_remaining_definite` / `block_remaining_tentative` **(Ö)**.

## 14. View'lardan çıkan Suite8 tablo adları

| Tablo | Anlamı | Kaynak |
|---|---|---|
| YRES | Rezervasyon başlığı | Oracle Demand 360 belgesi (`YRES_EXPDEPTIME`) |
| YDET | Rezervasyon gün detayı | `YDET_DATE` |
| YRMS | Odalar | `YRMS_YCAT_ID` |
| YCAT | Oda tipleri ("setup table of the room types", 10 satır) | Tablo açıklaması |
| YCAD | Otel segmentasyonu (oda tipi grubu) | Tablo açıklaması |
| YBLD | Bloklar / allotman | `YBLD_DEFNOOFROOMS` |
| ZCUR | Para birimleri | `PRO_ISV_ZCUR` |
| ZRDF | Rebate (indirim) tanımları | `PRO_ISV_POSTINGS`, Cashier Manual |
| WDAT | Takvim günleri | `WDAT_DATE` |
| v8_sys_fideliodate | Fidelio iş günü fonksiyonu | Oracle Demand 360 belgesi |

Fatura, cari, depozito, fiyat kodu, departman kodu tabloları `V8LIVE` tablo listesinden belirlenecek.

## 15. Canlı departman kodları (son 30 gün, otelden alınan çıktı)

`PRO_ISV_POSTINGS` üzerinden son 30 günde kullanılan kodlar. Kodlar ve numaralar RS PMS'e **aynen** aktarılacak.

### Kod değerleri (çıktıdan)

| Alan | Değer | Anlamı |
|---|---|---|
| CDT | 1 | Harcama (gelir) |
| CDT | 5 | Ödeme |
| PAYMENT_TYPE | 0 | Nakit |
| PAYMENT_TYPE | 2 | Cari (City Ledger) |
| PAYMENT_TYPE | 3 | Kredi kartı |
| PAYMENT_TYPE | 4 | Döviz nakit |
| PAYMENT_TYPE | boş | Banka havalesi |
| POSTING_LEDGERSTATUS | 0, 1, 2, 6 | **Doğrulanacak.** 0 çoğunluk (misafir defteri olmalı); 1 yalnız ödemelerde ve transferde (depozito olabilir); 2 ödemelerde ve bazı oda kodlarında; 6 yalnız oda/kahvaltı/akşam yemeğinde ve 7'şer adet (aynı birkaç rezervasyon) |

### Kod aralıkları

| Aralık | Grup | Kodlar |
|---|---|---|
| 100–199 | Konaklama ve pansiyon | 100 Room, 102 DISC Room, 105 Room No-Show, 107 Room Supplement, 110 Pension Breakfast, 115 Casino Room No-Show, 130 Pension Dinner |
| 300–899 | Outlet'ler (Simphony) | Her outlet için **4 kod**: FOOD, BVRG, DISC FOOD, DISC BVRG |
| 500–699 | Diğer hizmetler | 510 Laundry (+511 DISC), 513 Sauna-Massage (+514 DISC), 520 Transfer, 550 **Kur Farkı**, 580 Others %0, 630 Otopark |
| 900–999 | Ödemeler | 900 Cash, 903 City Ledger, 910 Visa Card, 920 Bank Payment, 950 Pay US Dollar, 951 Pay Euro |

### Outlet'ler (Simphony RVC'leri ile eşlenecek)

| Outlet | Yiyecek | İçecek | İnd. yiyecek | İnd. içecek |
|---|---|---|---|---|
| Cafe Rocks | 320 | 321 | 322 | 324 |
| Pool Bar | 330 | 331 | 332 | 334 |
| Room Service | 340 | 341 | 342 | 344 |
| Minibar | 350 | 351 | 352 | 354 |
| L'Hirondelle | 385 | 386 | 387 | 388 |
| Sıralı Et | 490 | 493 | 491 | 494 |
| Catch Seafood | 650 | 660 | 651 (+652 Reb.) | 661 |
| Casino | 670 | 680 | — | — |
| Lyra | 810 | 813 | 811 | 814 (+816 Other) |
| Rocks Pattiserie | 820 | 830 | 821 | 831 |

Sonuçlar:
- Simphony eşlemesi (`pms_pos_revenue_mappings`) **outlet × {yiyecek, içecek, indirim yiyecek, indirim içecek}** olarak kurulacak; indirim, negatif gelir olarak ayrı kodda.
- Pansiyon (110 kahvaltı, 130 akşam yemeği) oda fiyatından ayrılan paket elemanları — tasarımdaki `pms_package_elements` ile birebir.
- **Kur farkı (550)** ayrı gelir kodu: dövizle ödemede kayıt kuru ile ödeme kuru arasındaki fark otomatik bu koda yazılmalı **(Ö)**.
- Casino ayrı no-show kodu (115) ve outlet'i var: casino misafirleri ayrı izleniyor.

## §16 Departman kodu → vergi (Rocks Hotel)

Fidelio'da vergi, her departman kodunun düzenleme penceresinde **tarihli** olarak tanımlıdır (Department Codes → Edit → "Tax":
`1.01.2001 – … VAT 5 %`). Görülen örnekler: 100 Room ve 107 Room Supplement → VAT 5 %. Tax Definition ekranı yalnız vergi
departmanlarını (TX0–TX5) listeler, kod bağlantısı yoktur. Veritabanı sorgusu çalıştırılamadığı için otelin kararıyla:

| Kodlar | Vergi | Durum |
|---|---|---|
| Bütün gelir kodları | VAT5 (%5) | Otel kararı |
| 552–554 "Others %16" | VAT16 | Addan |
| 555–557 "Others %20" | VAT20 | Addan |
| 580–582 "Others %0" | VAT0 | Addan |
| Çamaşırhane 510–512, Transfer 520–522 | VAT16 (%16) | Fidelio raporu P4556 (25.11.2026): 510 Laundry, 520 Transfer "VAT 16 %" grubunda |
| Spa (513–515, 526–528, 547–549), Kuaför (640–642) | VAT5 | **Teyit edilecek** — farklı olabilir |
| 560–561 "VAT Discount %3 / %13", 780–782 paket farkları, 798 Deposit Retain | VAT5 | **Teyit edilecek** |
| 550–551 kur farkı, 880 Exchange Rate Difference, ödeme / paid-out / iç / depozito kodları | Vergisiz | — |

P4556'nın VAT 5 % sayfasında görülenler: 100 Room, 102 DISC Room, 110 Pension Breakfast, 130 Pension Dinner, 386 BVRG L HIRONDELLE.

Teyit için en iyi kaynak Fidelio raporu **P4556 Department code totals by not yet printed invoices (daily) with tax group**:
her vergi grubunun altında o gün kullanılan departman kodlarını listeler.

Departman kodu penceresindeki diğer alanlar (RS PMS'e taşınacak): Folio Text, Type (Debit/Credit), Group (ör. "10 Room Revenue (3)"),
Arrangement (ör. ACC Accommodation Expense), Government Code, Allow Manual Post, Return Dep., Unit Description, Default Price(s),
Physical Origin, Disabled (in Prearrival / in Billing / in AR / in Payments), Payment Type, Foreign Currency, Direct Debit Payment, Paidout,
Non-Hotel Revenue, CC Commission + yüzde, Statistic type (ör. Logis), Credit Limit, Folio Subfunction, **Adjustment Dept** (indirim kodu,
ör. 100 → 102, 107 → 108), Open Cash Drawer, Hostess Enabled, Post Tax Only.
