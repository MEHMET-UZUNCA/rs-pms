# Fidelio PROISV view'ları — kolon kolon eşleme

Aktarım kaynağı `PROISV` şemasındaki 13 view; kolon listesi **canlı veritabanından** (otelin gönderdiği `all_tab_columns` çıktısı). **Her kolonun** RS PMS karşılığı aşağıdadır; açıkta kalan kolon yoktur (betikle doğrulandı).
Toplam **13 view, 391 kolon**.

- `Türetilir`: Kolon başka alanlardan hesaplanır, ayrıca saklanmaz.
- `Kullanılmaz`: Fidelio'ya özel gösterim/hız alanı; karşılığı gerekmez.

## PRO_ISV_POSTINGS (45 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | POSTING_ID | pms_postings.legacy_id |
| 2 | CASHIER_ID | pms_cashiers.legacy_id |
| 3 | CASHIER_NUMBER | pms_cashiers.number |
| 4 | CASHIER_INITIALS | pms_cashiers.initials |
| 5 | POSTING_DATE | pms_postings.business_date |
| 6 | POSTING_PCDATE | pms_postings.created_at |
| 7 | POSTING_QUANTITY | pms_postings.quantity |
| 8 | POSTING_UNITPRICE | pms_postings.unit_price |
| 9 | POSTING_SUMUPAMOUNT | pms_postings.amount |
| 10 | POSTING_FOREIGNPRICE | pms_postings.foreign_unit_price |
| 11 | POSTING_FOREIGNSUMUPAMOUNT | pms_postings.foreign_amount |
| 12 | POSTING_CURRENCY | pms_postings.foreign_currency |
| 13 | POSTING_EXCHRATE | pms_postings.exchange_rate |
| 14 | POSTING_CURRENCY_ID | pms_currencies.legacy_id |
| 15 | CDT | pms_postings.kind (1 harcama, 5 ödeme) |
| 16 | PAYMENT_TYPE | pms_transaction_codes.payment_kind |
| 17 | POSTING_LEDGERSTATUS | pms_postings.ledger + legacy_ledger_status (ham değer) |
| 18 | POSTING_WINDOW_ID | pms_folios.legacy_id |
| 19 | POSTING_DC_DESCRIPTION | pms_postings.description / pms_transaction_codes.name |
| 20 | POSTING_DC_NUMBER | pms_transaction_codes.code |
| 21 | POSTING_DC_ID | pms_transaction_codes.legacy_id |
| 22 | POSTING_FA_ID | pms_folios.legacy_id (rezervasyonsuz hesap) |
| 23 | POSTING_FA_LONGDESC | pms_folios.name |
| 24 | POSTING_FA_ACTVALIDFROM | pms_folios.actual_from |
| 25 | POSTING_FA_ACTVALIDUNTIL | pms_folios.actual_until |
| 26 | POSTING_FA_EXPVALIDFROM | pms_folios.valid_from |
| 27 | POSTING_FA_EXPVALIDUNTIL | pms_folios.valid_until |
| 28 | ROOMNUMBER | pms_postings.room_id |
| 29 | POSTING_RES_ID | pms_postings.reservation_id |
| 30 | POSTING_RES_ACTARRTIME | Türetilir (pms_reservations.checked_in_at) |
| 31 | POSTING_RES_ACTDEPTIME | Türetilir (pms_reservations.checked_out_at) |
| 32 | POSTING_RES_EXPARRTIME | Türetilir (pms_reservations.eta) |
| 33 | POSTING_RES_EXPDEPTIME | Türetilir (pms_reservations.etd) |
| 34 | POSTING_RES_ROOMNUMBER | Türetilir (pms_reservations.room_id) |
| 35 | POSTING_PROFILE_ID | pms_folios.payee_profile_id |
| 36 | POSTING_ORG_PROFILE_ID | pms_postings.origin_profile_id |
| 37 | POSTING_ORG_RES_ID | pms_postings.origin_reservation_id |
| 38 | POSTING_ORG_FA_ID | pms_postings.origin_folio_id |
| 39 | POSTING_COMMENT | pms_postings.note |
| 40 | AGENCY_NAME | Türetilir (profil adı) |
| 41 | AGENCY_ID | pms_reservations.agent_profile_id |
| 42 | COMPANY_NAME | Türetilir (profil adı) |
| 43 | COMPANY_ID | pms_reservations.company_profile_id |
| 44 | POSTING_RESSTATUS | Türetilir (pms_reservations.status) |
| 45 | BASECURRENCY | pms_properties.base_currency |

## PRO_GIYKIMBIL (38 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | SIRANO | Kullanılmaz (liste sıra no) |
| 2 | PROFILE_ID | pms_profiles.legacy_id |
| 3 | YDET_ID | pms_reservation_nights.legacy_id |
| 4 | TCKIMLIKNO | pms_profile_identities.document_number |
| 5 | HES_CODE | pms_profile_identities.registry.hes_code (geçmiş kayıt) |
| 6 | SOYADI | pms_profiles.last_name |
| 7 | ADI | pms_profiles.first_name |
| 8 | BABAADI | pms_profiles.father_name |
| 9 | ANAADI | pms_profiles.mother_name |
| 10 | DOGUMYERI | pms_profiles.birth_place |
| 11 | DOGUMTARIHI | pms_profiles.birth_date |
| 12 | DOGUMTARIHI2 | Türetilir |
| 13 | XCMS_XCOU_ID_NATION | pms_countries.legacy_id |
| 14 | UYRUGU | pms_profiles.nationality_code |
| 15 | UYRUK_KODU | pms_countries.kbs_code |
| 16 | KIMLIKBELGESITURU | pms_profile_identities.document_type |
| 17 | KIMLIKSERINO | pms_profile_identities.serial_number |
| 18 | NUFUSAKAYITLIOLDUGUIL | pms_profile_identities.registry.il |
| 19 | NUFUSAKAYITLIOLDUGUILCE | pms_profile_identities.registry.ilce |
| 20 | NUFUSAKAYITLIOLDUGUMAHALLE | pms_profile_identities.registry.mahalle |
| 21 | NUFUSCILT | pms_profile_identities.registry.cilt |
| 22 | NUFUSAILESIRA | pms_profile_identities.registry.aile_sira |
| 23 | NUFUSSIRANO | pms_profile_identities.registry.sira_no |
| 24 | CINSIYET | pms_profiles.gender |
| 25 | MEDENIHALI | pms_profiles.marital_status |
| 26 | ISI | pms_profiles.occupation |
| 27 | IKAMETADRESI | pms_profile_addresses (home) |
| 28 | TELEPHONE | pms_profile_communications (phone) |
| 29 | MOBILETELEPHONE | pms_profile_communications (mobile) |
| 30 | GELISTARIHI | Türetilir (checked_in_at) |
| 31 | GELISTARIHI2 | Türetilir (checked_in_at) |
| 32 | AYRILISTARIHI | Türetilir (checked_out_at) |
| 33 | AYRILISTARIHI2 | Türetilir (checked_out_at) |
| 34 | VERILENODANO | Türetilir (room_id) |
| 35 | ARACPLAKANO | pms_reservations.vehicle_plate |
| 36 | RESORT | pms_properties.code |
| 37 | YCAD_ID | pms_room_classes.legacy_id |
| 38 | YCAD_LONGDESC | pms_room_classes.name |

## PRO_ISV_DAILY_ROOMBED_RMTYP (7 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | ROOMTYPE_ID | pms_room_types.legacy_id |
| 2 | DDATE | pms_daily_statistics.business_date |
| 3 | HOTELSEGMENT | pms_room_classes.code |
| 4 | ROOMTYPE_SHORTDESC | pms_room_types.code |
| 5 | ROOMTYPE_LONGDESC | pms_room_types.name |
| 6 | NOOFROOMS | pms_daily_statistics.rooms_available |
| 7 | NOOFBEDS | pms_daily_statistics.beds_available |

## PRO_ISV_IND_FAM_LINKS (8 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | PROFILE_ID | pms_profile_relations.profile_id |
| 2 | PROFILE_GLOBAL_ID | pms_profiles.legacy_global_id |
| 3 | LINKED_PROFILE_ID | pms_profile_relations.related_profile_id |
| 4 | LINKED_PROFILE_GLOBAL_ID | pms_profiles.legacy_global_id |
| 5 | LP_ACTIVE_STATUS | Türetilir |
| 6 | LP_NAME1 | Türetilir |
| 7 | LP_FORMATTEDNAME | Türetilir |
| 8 | LINK_TYPE | pms_profile_relations.relation |

## PRO_ISV_MEMBERCARDINFO (5 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | PROFILE_ID | pms_profile_memberships.profile_id |
| 2 | CARDROLE | pms_profile_memberships.role |
| 3 | SHORTDESC | pms_membership_types.code |
| 4 | LONGDESC | pms_membership_types.name |
| 5 | CARDVALUE | pms_profile_memberships.number |

## PRO_ISV_MULTIGUEST_LIST (2 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | RESERVATION_ID | pms_reservation_guests.reservation_id |
| 2 | MULTIGUEST_PROFILE_ID | pms_reservation_guests.profile_id |

## PRO_ISV_OOOROOMBEDS_ROOMTYPE (9 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | ROOMTYPE_ID | pms_room_types.legacy_id |
| 2 | DDATE | pms_daily_statistics.business_date |
| 3 | HOTELSEGMENT | pms_room_classes.code |
| 4 | ROOMTYPE_SHORTDESC | pms_room_types.code |
| 5 | ROOMTYPE_LONGDESC | pms_room_types.name |
| 6 | OOSROOMS | pms_daily_statistics.rooms_oos |
| 7 | OOSBEDS | pms_daily_statistics.beds_oos |
| 8 | OOOROOMS | pms_daily_statistics.rooms_ooo |
| 9 | OOOBEDS | pms_daily_statistics.beds_ooo |

## PRO_ISV_PROFILEINFO (52 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | PROFILE_ID | pms_profiles.legacy_id |
| 2 | PROFILE_GLOBALID | pms_profiles.legacy_global_id |
| 3 | PROFILE_TYPE | pms_profiles.type |
| 4 | PROFILE_INTERNALCATEGORY | pms_profiles.type |
| 5 | GUESTFULLNAME | pms_profiles.display_name |
| 6 | GUESTFULLNAME_BYFIRST | Türetilir |
| 7 | NAME1 | pms_profiles.last_name / company_name |
| 8 | NAME2 | pms_profiles.first_name |
| 9 | NAME3 | pms_profiles.middle_name |
| 10 | ADDRESSGREETING | pms_profiles.salutation |
| 11 | LETTERGREETING | pms_profiles.letter_greeting |
| 12 | SEX | pms_profiles.gender |
| 13 | PROFILELANGUAGE | pms_profiles.language_code |
| 14 | BIRTHDATE | pms_profiles.birth_date |
| 15 | WEDDINGDAY | pms_profiles.wedding_date |
| 16 | STREET1 | pms_profile_addresses.line1 |
| 17 | STREET2 | pms_profile_addresses.line2 |
| 18 | STREET3 | pms_profile_addresses.line3 |
| 19 | CITY | pms_profile_addresses.city |
| 20 | ZIPCODE | pms_profile_addresses.postal_code |
| 21 | STATE_SHORTDESC | pms_profile_addresses.state |
| 22 | STATE_LONGDESC | Türetilir |
| 23 | COUNTRY | pms_profile_addresses.country_code |
| 24 | COUNTRY_LONGDESC | Türetilir (pms_countries) |
| 25 | NATION | pms_profiles.nationality_code |
| 26 | NATION_LONGDESC | Türetilir (pms_countries) |
| 27 | EMAIL | pms_profile_communications (email) |
| 28 | TELEPHONE | pms_profile_communications (phone) |
| 29 | FAX | pms_profile_communications (fax) |
| 30 | MOBILETELEPHONE | pms_profile_communications (mobile) |
| 31 | WEBSITE | pms_profile_communications (web) |
| 32 | VIPCODE | pms_profiles.vip_level_id |
| 33 | TAXOFFICE | pms_profiles.tax_office |
| 34 | TAXNUMBER | pms_profiles.tax_number |
| 35 | CREATIONDATE | pms_profiles.created_at |
| 36 | CREATED_USER | pms_profiles.legacy_created_by |
| 37 | ACTIVE_STATUS | pms_profiles.status |
| 38 | LASTUPDATETIME | pms_profiles.updated_at |
| 39 | LASTUPDATEUSER | pms_profiles.legacy_updated_by |
| 40 | LASTUPDATEUSERNAME | pms_profiles.legacy_updated_by |
| 41 | ORIGINATED_HOTEL_NAME | pms_profiles.origin_hotel_name |
| 42 | ORIGINATED_HOTEL_ID | pms_profiles.origin_hotel_legacy_id |
| 43 | PRIMARY_CONTACT_ID | pms_profiles.primary_contact_profile_id |
| 44 | MERGEDTO_PROFILE_ID | pms_profiles.merged_into_id |
| 45 | MERGETIME | pms_profiles.merged_at |
| 46 | PERSONAL_DOC_TYPE | pms_profile_identities.document_type |
| 47 | PERSONAL_DOC_NUMBER | pms_profile_identities.document_number |
| 48 | TCIDNO | pms_profile_identities.document_number (tc_id) |
| 49 | OCCUPATION | pms_profiles.occupation |
| 50 | NOMAILING | pms_profiles.no_mailing |
| 51 | NOEMAILING | pms_profiles.no_emailing |
| 52 | STOREPRIVATEDATA | pms_profiles.store_private_data |

## PRO_ISV_PROMOTIONS (9 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | RESDETAIL_ID | pms_reservation_night_promotions.reservation_night_id |
| 2 | RESDETAIL_DATE | Türetilir |
| 3 | PROM_SHORTDESC | pms_promotions.code |
| 4 | PROM_LONGDESC | pms_promotions.name |
| 5 | RULE_SHORTDESC | pms_promotions.rule_code |
| 6 | RULE_LONGDESC | pms_promotions.rule_name |
| 7 | RULE_TYPE | pms_promotions.rule_type |
| 8 | LOCAL_AMOUNT | pms_reservation_night_promotions.amount |
| 9 | FOREIGN_AMOUNT | pms_reservation_night_promotions.foreign_amount |

## PRO_ISV_RESERVATIONINFO (122 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | RESERVATION_ID | pms_reservations.legacy_id |
| 2 | DETAIL_DATE | pms_reservation_nights.stay_date |
| 3 | ARRIVALDATE | pms_reservations.arrival_date |
| 4 | DEPARTUREDATE | pms_reservations.departure_date |
| 5 | HOTELSEGMENT | pms_room_classes.code |
| 6 | HOTELSEGMENT_LONGDESC | Türetilir |
| 7 | PARTY_ID | pms_bookings.legacy_id |
| 8 | PARTY_LONGDESC | pms_bookings.name |
| 9 | ROOMNUMBER | pms_reservation_nights.room_id |
| 10 | ROOMTYPE | pms_reservation_nights.room_type_id |
| 11 | ROOMTYPE_LONGDESC | Türetilir |
| 12 | RATEROOMTYPE | pms_reservation_nights.charge_room_type_id |
| 13 | RATEROOMTYPE_LONGDESC | Türetilir |
| 14 | DETAIL_NOOFROOMS | pms_reservations (gün bazında oda sayısı; >1 ise bölünür) |
| 15 | NOOFROOMS | pms_reservations (oda sayısı kadar kayıt) + pms_bookings |
| 16 | GUESTNAME | Türetilir (pms_profiles.display_name) |
| 17 | GUESTFIRSTNAME | Türetilir |
| 18 | GUESTMIDDLENAME | Türetilir |
| 19 | GUESTLASTNAME | Türetilir |
| 20 | NOOFADULTS | pms_reservation_nights.adults |
| 21 | FREECHILD | pms_reservation_nights.free_children |
| 22 | CHILDREN | pms_reservation_nights.children |
| 23 | CA1 | pms_reservation_nights.child_band_1 |
| 24 | CA2 | pms_reservation_nights.child_band_2 |
| 25 | CA3 | pms_reservation_nights.child_band_3 |
| 26 | OTHERCA | pms_reservation_nights.child_other |
| 27 | VIPCODE | pms_reservations.vip_level_id |
| 28 | CHTEXT | Türetilir (CH metni) |
| 29 | RATECODE | pms_reservation_nights.rate_code_id |
| 30 | MAINMARKETCODE | pms_market_groups.code |
| 31 | MAINMARKETCODE_LONG | Türetilir |
| 32 | MARKETCODE | pms_reservation_nights.market_segment_id |
| 33 | MARKETCODE_LONG | Türetilir |
| 34 | SOURCECODE | pms_reservations.source_code_id |
| 35 | CHANNELCODE | pms_reservations.channel_code_id |
| 36 | CURRENCY | pms_reservation_nights.currency_code |
| 37 | EXCHANGERATE | pms_reservation_nights.exchange_rate |
| 38 | FOREIGNPOSTAMOUNT | Türetilir (gece kaydının foreign_amount toplamı) |
| 39 | POSTAMOUNT | Türetilir (gece kaydı pms_postings.amount) |
| 40 | COUNTRY | pms_profile_addresses.country_code |
| 41 | NATION | pms_profiles.nationality_code |
| 42 | AGENTNAME | Türetilir |
| 43 | AGENTNAME_ID | pms_reservations.agent_profile_id |
| 44 | COMPANYNAME | Türetilir |
| 45 | COMPANYNAME_ID | pms_reservations.company_profile_id |
| 46 | SOURCENAME | Türetilir |
| 47 | GROUPNAME | pms_blocks.name / group_profile_id |
| 48 | BILLINGINFO | pms_reservations.payment_instructions |
| 49 | REMARKS | pms_reservations.guest_comments |
| 50 | CRSRESNR | pms_reservations.external_reference |
| 51 | RATECODE_BOARDTYPETEXT | Türetilir (pms_packages.name) |
| 52 | POSDISCOUNT | pms_reservations.pos_discount_code |
| 53 | CREATED_DATE | pms_reservations.created_at |
| 54 | CREATED_USER | pms_reservations.legacy_created_by |
| 55 | SALEDATE | pms_reservations.sale_date |
| 56 | GUARANTEECODE | pms_reservations.reservation_type_id |
| 57 | RESERVATIONSTATUS | pms_reservations.status |
| 58 | RESERVATIONSTATUSTEXT | Türetilir |
| 59 | CALCULATEDSTATUS | pms_reservations.status (+ is_day_use) |
| 60 | CALCULATEDSTATUSTEXT | Türetilir |
| 61 | BOOKER | pms_reservations.booker_name |
| 62 | COUNTRY_LONGDESC | Türetilir (pms_countries) |
| 63 | NATION_LONGDESC | Türetilir (pms_countries) |
| 64 | RATEMANUAL | pms_reservation_nights.manual_amount |
| 65 | RATECODEAMOUNT | pms_reservation_nights.amount |
| 66 | NETRATECODEAMOUNT | pms_reservation_nights.net_amount |
| 67 | NETPOSTAMOUNT | Türetilir |
| 68 | FOREIGNRATEMANUAL | pms_reservation_nights.manual_foreign_amount |
| 69 | FOREIGNRATECODEAMOUNT | pms_reservation_nights.foreign_amount |
| 70 | FOREIGNNETRATECODEAMOUNT | pms_reservation_nights.foreign_net_amount |
| 71 | FOREIGNNETPOSTAMOUNT | Türetilir |
| 72 | TOTAL_POSTAMOUNT | Türetilir |
| 73 | TOTAL_NETPOSTAMOUNT | Türetilir |
| 74 | TOTAL_FOREIGNPOSTAMOUNT | Türetilir |
| 75 | TOTAL_FOREIGNNETPOSTAMOUNT | Türetilir |
| 76 | PACKAGEAMOUNT | pms_reservation_nights.package_amount |
| 77 | PACKAGEFOREIGNAMOUNT | pms_reservation_nights.package_foreign_amount |
| 78 | PACKAGEAMOUNT_INCLUDED | pms_reservation_nights.package_included_amount |
| 79 | PACKAGEFOREIGNAMOUNT_INCLUDED | pms_reservation_nights.package_included_foreign_amount |
| 80 | CREDITLIMIT | pms_reservations.credit_limit |
| 81 | RATEDIFFREASON | pms_reservation_nights.override_reason_code_id |
| 82 | CH | pms_reservation_nights.is_complimentary / is_house_use |
| 83 | RATECODE_BOARDTYPE | pms_rate_codes.package_id |
| 84 | DETAIL_ID | pms_reservation_nights.legacy_id |
| 85 | PROFILE_TYPE | Türetilir (pms_profiles.type) |
| 86 | PROFILE_ID | pms_reservations.profile_id |
| 87 | CURRENT_DETAIL_ID | Türetilir |
| 88 | LAST_DETAIL_ID | Türetilir |
| 89 | SHARENUMBER | pms_reservations.share_group_id |
| 90 | GUESTNAME_BYFIRST | Türetilir |
| 91 | JULIANARRIVALDATE | Kullanılmaz (Jülyen gösterim) |
| 92 | JULIANARRIVALTIME | Kullanılmaz |
| 93 | ARRIVALTIME | pms_reservations.eta |
| 94 | DEPARTURETIME | pms_reservations.etd |
| 95 | ARRIVALDATETIME | Türetilir (arrival_date + eta) |
| 96 | DEPARTUREDATETIME | Türetilir (departure_date + etd) |
| 97 | JULIANDEPARTUREDATE | Kullanılmaz |
| 98 | JULIANDEPARTURETIME | Kullanılmaz |
| 99 | EXPECTED_ARRIVALTIME | pms_reservations.eta |
| 100 | ACTUAL_ARRIVALTIME | pms_reservations.checked_in_at |
| 101 | ACTARRTIMENULL | Kullanılmaz (Fidelio hız alanı) |
| 102 | EXPECTED_DEPARTURETIME | pms_reservations.etd |
| 103 | ACTUAL_DEPARTURETIME | pms_reservations.checked_out_at |
| 104 | ACTDEPTIMENULL | Kullanılmaz |
| 105 | ORIGINAL_DEPARTURETIME | pms_reservations.original_departure_date (+ etd) |
| 106 | ORIGINAL_DEPARTUREDATE | pms_reservations.original_departure_date |
| 107 | CREATED_USERNAME | pms_reservations.legacy_created_by |
| 108 | LASTUPDATETIME | pms_reservations.updated_at |
| 109 | LASTUPDATE_USER | pms_reservations.legacy_updated_by |
| 110 | LASTUPDATE_USERNAME | pms_reservations.legacy_updated_by |
| 111 | DISPAGENTNAME | Türetilir |
| 112 | DISPCOMPANYNAME | Türetilir |
| 113 | DISPGROUPNAME | Türetilir |
| 114 | DISPSOURCENAME | Türetilir |
| 115 | ROOMARRIVAL | Türetilir (istatistik) |
| 116 | ADULTARRIVAL | Türetilir (istatistik) |
| 117 | ROOMDEPARTURE | Türetilir (istatistik) |
| 118 | ADULTDEPARTURE | Türetilir (istatistik) |
| 119 | CURRENTROOM | Türetilir |
| 120 | CURRENTROOMTYPE | Türetilir |
| 121 | GENDER | Türetilir (pms_profiles.gender) |
| 122 | BIRTHDAY | Türetilir (pms_profiles.birth_date) |

## PRO_ISV_RESERVATIONREVENUE (86 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | RESERVATION_ID | Türetilir (rezervasyon/gecelik satır) |
| 2 | FA_ID | pms_folios.legacy_id |
| 3 | DDATE | Türetilir (rezervasyon/gecelik satır) |
| 4 | CDAY | Türetilir (rezervasyon/gecelik satır) |
| 5 | CMONTH | Türetilir (rezervasyon/gecelik satır) |
| 6 | CYEAR | Türetilir (rezervasyon/gecelik satır) |
| 7 | PROFILE_ID | Türetilir (rezervasyon/gecelik satır) |
| 8 | DETAIL_ID | Türetilir (rezervasyon/gecelik satır) |
| 9 | ROOMTYPE_ID | Türetilir (rezervasyon/gecelik satır) |
| 10 | ROOMTYPE | Türetilir (rezervasyon/gecelik satır) |
| 11 | RATEROOMTYPE | Türetilir (rezervasyon/gecelik satır) |
| 12 | ROOMTYPE_SEGMENT | pms_room_classes (otel segmenti) |
| 13 | RATEROOMTYPE_SEGMENT | pms_room_classes (otel segmenti) |
| 14 | CURRENCY | Türetilir (rezervasyon/gecelik satır) |
| 15 | EXCHANGERATE | Türetilir (rezervasyon/gecelik satır) |
| 16 | GUESTNAME | Türetilir (rezervasyon/gecelik satır) |
| 17 | ARRDATE | Türetilir (rezervasyon/gecelik satır) |
| 18 | ARRTIME | Türetilir (rezervasyon/gecelik satır) |
| 19 | DEPDATE | Türetilir (rezervasyon/gecelik satır) |
| 20 | DEPTIME | Türetilir (rezervasyon/gecelik satır) |
| 21 | ARRIVALTIME | Türetilir |
| 22 | DEPARTURETIME | Türetilir |
| 23 | ARRIVALDATETIME | Türetilir |
| 24 | DEPARTUREDATETIME | Türetilir |
| 25 | RESERVATION_STATUS | Türetilir (rezervasyon/gecelik satır) |
| 26 | RESERVATIONSTATUSTEXT | Türetilir |
| 27 | CALCSTATUS | Türetilir (rezervasyon/gecelik satır) |
| 28 | CALCULATEDSTATUSTEXT | Türetilir |
| 29 | NOOFROOMS | Türetilir (rezervasyon/gecelik satır) |
| 30 | NOOFADULTS | Türetilir (rezervasyon/gecelik satır) |
| 31 | CHILDREN | Türetilir (rezervasyon/gecelik satır) |
| 32 | FREECHILD | Türetilir (rezervasyon/gecelik satır) |
| 33 | CA1 | Türetilir (rezervasyon/gecelik satır) |
| 34 | CA2 | Türetilir (rezervasyon/gecelik satır) |
| 35 | CA3 | Türetilir (rezervasyon/gecelik satır) |
| 36 | OTHERCA | Türetilir (rezervasyon/gecelik satır) |
| 37 | CH | Türetilir (rezervasyon/gecelik satır) |
| 38 | MARKETCODE | Türetilir (rezervasyon/gecelik satır) |
| 39 | MAINMARKETCODE | Türetilir |
| 40 | SOURCECODE | Türetilir (rezervasyon/gecelik satır) |
| 41 | CHANNELCODE | Türetilir (rezervasyon/gecelik satır) |
| 42 | RATECODE | Türetilir (rezervasyon/gecelik satır) |
| 43 | ROOMNUMBER | Türetilir (rezervasyon/gecelik satır) |
| 44 | AGENTNAME | Türetilir (rezervasyon/gecelik satır) |
| 45 | COMPANYNAME | Türetilir (rezervasyon/gecelik satır) |
| 46 | SOURCENAME | Türetilir (rezervasyon/gecelik satır) |
| 47 | GROUPNAME | Türetilir (rezervasyon/gecelik satır) |
| 48 | BLOCK_ID | pms_blocks.legacy_id |
| 49 | BLOCK_NAME | pms_blocks.code |
| 50 | NATION | Türetilir (rezervasyon/gecelik satır) |
| 51 | NATION_LONGDESC | Türetilir (rezervasyon/gecelik satır) |
| 52 | VIPCODE | Türetilir (rezervasyon/gecelik satır) |
| 53 | BOARDTYPE | Türetilir (rezervasyon/gecelik satır) |
| 54 | BOARDTYPETEXT | Türetilir |
| 55 | POSDISCOUNT | Türetilir (rezervasyon/gecelik satır) |
| 56 | POSTAMOUNT | Türetilir (rezervasyon/gecelik satır) |
| 57 | NETPOSTAMOUNT | Türetilir (rezervasyon/gecelik satır) |
| 58 | RATECODEAMOUNT | Türetilir (rezervasyon/gecelik satır) |
| 59 | NETRATECODEAMOUNT | Türetilir (rezervasyon/gecelik satır) |
| 60 | GROSSROOM | pms_daily_revenue → revenue_bucket=room (brüt); geçmiş: pms_daily_statistics |
| 61 | GROSSFB | pms_daily_revenue → revenue_bucket=fb (brüt); geçmiş: pms_daily_statistics |
| 62 | GROSSMINIBAR | pms_daily_revenue → revenue_bucket=minibar (brüt); geçmiş: pms_daily_statistics |
| 63 | GROSSTELEPHONE | pms_daily_revenue → revenue_bucket=telephone (brüt); geçmiş: pms_daily_statistics |
| 64 | GROSSMISC | pms_daily_revenue → revenue_bucket=misc (brüt); geçmiş: pms_daily_statistics |
| 65 | GROSSBREAKFAST | pms_daily_revenue → revenue_bucket=breakfast (brüt); geçmiş: pms_daily_statistics |
| 66 | GROSSLUNCH | pms_daily_revenue → revenue_bucket=lunch (brüt); geçmiş: pms_daily_statistics |
| 67 | GROSSDINNER | pms_daily_revenue → revenue_bucket=dinner (brüt); geçmiş: pms_daily_statistics |
| 68 | GROSSALLINCFOOD | pms_daily_revenue → revenue_bucket=allinc_food (brüt) |
| 69 | GROSSALLINCBVRG | pms_daily_revenue → revenue_bucket=allinc_beverage (brüt) |
| 70 | NETROOM | pms_daily_revenue → revenue_bucket=room (net); geçmiş: pms_daily_statistics |
| 71 | NETFB | pms_daily_revenue → revenue_bucket=fb (net); geçmiş: pms_daily_statistics |
| 72 | NETMINIBAR | pms_daily_revenue → revenue_bucket=minibar (net); geçmiş: pms_daily_statistics |
| 73 | NETTELEPHONE | pms_daily_revenue → revenue_bucket=telephone (net); geçmiş: pms_daily_statistics |
| 74 | NETMISC | pms_daily_revenue → revenue_bucket=misc (net); geçmiş: pms_daily_statistics |
| 75 | NETBREAKFAST | pms_daily_revenue → revenue_bucket=breakfast (net); geçmiş: pms_daily_statistics |
| 76 | NETLUNCH | pms_daily_revenue → revenue_bucket=lunch (net); geçmiş: pms_daily_statistics |
| 77 | NETDINNER | pms_daily_revenue → revenue_bucket=dinner (net); geçmiş: pms_daily_statistics |
| 78 | NETALLINCFOOD | pms_daily_revenue → revenue_bucket=allinc_food (net) |
| 79 | NETALLINCBVRG | pms_daily_revenue → revenue_bucket=allinc_beverage (net) |
| 80 | CREATED_DATE | Türetilir (rezervasyon/gecelik satır) |
| 81 | BOOKING_DATE | pms_reservations.sale_date (satış tarihi yoksa) |
| 82 | CREATED_USER_ID | Türetilir (rezervasyon/gecelik satır) |
| 83 | CREATED_USER | Türetilir (rezervasyon/gecelik satır) |
| 84 | COUNTRY | Türetilir (rezervasyon/gecelik satır) |
| 85 | COUNTRY_LONGDESC | Türetilir (rezervasyon/gecelik satır) |
| 86 | PROFILE_TYPE | Türetilir (rezervasyon/gecelik satır) |

## PRO_ISV_SAMPLE_INHOUSE (5 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | RESERVATION_ID | Kullanılmaz (view geçersiz) |
| 2 | ARRIVALDATE | Kullanılmaz (view geçersiz) |
| 3 | DEPARTUREDATE | Kullanılmaz (view geçersiz) |
| 4 | ROOMNUMBER | Kullanılmaz (view geçersiz) |
| 5 | GUESTNAME | Kullanılmaz (view geçersiz) |

## PRO_ISV_VIRTUALNUMBERS (3 kolon)

| # | Fidelio kolonu | RS PMS |
|---|---|---|
| 1 | RESERVATION_ID | pms_virtual_number_assignments.reservation_id |
| 2 | VIRTUALNUMBER | pms_virtual_numbers.number |
| 3 | ISINUSE | pms_virtual_number_assignments.is_active |

