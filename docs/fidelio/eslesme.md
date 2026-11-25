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
