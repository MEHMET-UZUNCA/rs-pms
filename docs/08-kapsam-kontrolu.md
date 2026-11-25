# 8. Kapsam kontrolü — Fidelio Suite8 işlevleri

Veritabanının eksiksiz olması için iki kontrol yapıldı:

1. **Veri kontrolü:** PROISV şemasındaki 13 view'ın canlı veritabanındaki **391 kolonunun tamamı** RS PMS'e eşlendi
   ([fidelio/kolon-kolon.md](fidelio/kolon-kolon.md)). Eşlemede geçen 184 tablo.kolon referansının hepsinin veritabanı tasarımında
   bulunduğu betikle doğrulandı.
2. **İşlev kontrolü:** Oracle'ın Suite8 8.9 kılavuzlarının içindekiler bölümleri (Setup, Reservations, Cashiering, Front Desk,
   Rooms Management, Miscellaneous, CRM) tek tek tarandı. Her işlev için karşılık aşağıda.

Gösterim: ✅ tasarımda var · ➕ bu kontrolde eklendi · 🔗 RS ONE'da (birleşmede ortak) · ⏭ kapsam dışı / sonraya

## 8.1 Kasa (Cashiering)

| Suite8 işlevi | Durum | RS PMS |
|---|---|---|
| Department Codes, Groups, Main Groups | ✅ | `pms_transaction_codes`, `pms_transaction_code_groups` (iki seviye) |
| Articles, Article Groups | ➕ | `pms_articles`, `pms_article_groups` |
| Arrangement Codes | ✅ | `pms_packages` + `pms_package_elements` |
| Billing Instruction Codes | ➕ | `pms_billing_instruction_codes` |
| Billing Window Types | ➕ | `pms_window_types` |
| Cashier Setup, interface cashiers | ✅➕ | `pms_cashiers` (`is_interface`) |
| Credit Card Types / Schemas / Checks | ✅ | Ödeme işlem kodları + `pms_payment_tokens.brand` |
| Credit Card / City Ledger / Advanced Billing Authorization | ➕ | `pms_billing_authorizations` |
| Credit Limit | ✅ | `pms_reservations.credit_limit`, `pms_routing_rules.credit_limit` |
| Currencies, Exchange Rates, Exchange Rate Fee | ✅➕ | `pms_currencies`, `pms_exchange_rates` (`exchange_fee_pct`) |
| Currency Exchange (döviz bozdurma) | ✅ | `pms_postings.kind = exchange` |
| Financial Accounts, FA Groups, Permanent Staff Accounts | ✅➕ | `pms_folios` (`passer_by` / `house_account` / `master`), `pms_folio_groups` |
| Financial Account Numbers, Number Cycles | ✅ | `pms_number_sequences` |
| Folio Styles, Text Templates | ➕ | `pms_document_templates` |
| Petty Cash, Paid Outs | ✅ | `pms_postings.kind = paid_out` |
| Rebate Configuration, Rebate Accounts | ✅ | `pms_rebate_codes`, `pms_rebate_code_rules`, `pms_reservation_rebates` |
| Tax Definition, Tax Details | ✅ | `pms_taxes`, `pms_transaction_code_taxes`, `pms_posting_taxes` |
| Tax Exemption | ➕ | `pms_tax_exemptions` |
| Allowances | ✅ | `pms_package_elements.is_allowance`, `kind = allowance` |
| Adjustments, Split, Transfers | ✅ | Ters kayıt + `transfer_from_id`, `origin_*` |
| Posting Daily Charges, Fixed Charges | ✅ | Gece kapanışı + `pms_fixed_charges` |
| Proforma, Advance Invoice, Information Folio | ✅ | `pms_invoices.type = proforma`, `pms_document_templates` |
| Billing History, Invoice Reprint | ✅ | `pms_invoices.print_count`, `pms_documents` |
| Deposits | ✅ | `pms_deposit_requests`, `ledger = deposit` |
| Close Cashier, Cashier Status | ✅ | `pms_cashier_shifts`, `pms_cashier_shift_totals` |
| Batch Posting, Posting Journal | ✅ | `pms_postings` sorguları |
| Batch Settlements (kart gün sonu) | ✅ | Ödeme kodu bazında kasa kapanışı |
| Passer By | ✅ | `pms_folios.type = passer_by` |
| Accounts Receivable: Accounts, Account Types | ✅➕ | `pms_ar_accounts`, `pms_ar_account_types` |
| A/R Transactions, Payments, Adjust, Transfer, Aging | ✅ | `pms_ar_invoices`, `pms_ar_payments`, `pms_ar_allocations` |
| Reminder Letters & Statements | ✅➕ | `pms_ar_statements`, `pms_ar_reminders` |
| Document History | ➕ | `pms_documents` |
| Travel Agent Processing (komisyon) | ➕ | `pms_commission_codes`, `pms_commission_rules`, `pms_commissions` |
| Tour Operator Invoice | ✅ | Cari fatura (`pms_ar_invoices`) acente bazında |
| Voucher Management | ➕ | `pms_voucher_types`, `pms_vouchers` (🔗 RS ONE hediye çekleri) |
| Article Posting | ✅➕ | `pms_articles` → `pms_postings` |
| Kur farkı | ✅ | `kind = exchange_difference`, kod 550 |
| Ticketing, Transponder, Yearly Tickets | ⏭ | Kayak/aquapark bilet sistemi — otelde yok |

## 8.2 Rezervasyon

| Suite8 işlevi | Durum | RS PMS |
|---|---|---|
| Reservation, Days functionality (gün detayı) | ✅ | `pms_reservations`, `pms_reservation_nights` |
| Inventory tab, Miscellaneous Items | ➕ | `pms_items`, `pms_reservation_items` |
| Profiles on reservation (agent, company, source, group, booker) | ✅ | `*_profile_id`, `booker_name` |
| Package grid, Meal Plan | ✅ | `pms_reservation_packages` |
| Attributes, Special Attributes | ✅ | `pms_special_requests`, `pms_profile_preferences` |
| Credit Cards | ✅ | `pms_payment_tokens` |
| Fixed Charges | ✅ | `pms_fixed_charges` |
| Notes, Note Categories | ✅➕ | `pms_notes`, `pms_note_categories` (çok amaçlı) |
| Amenity, Amenity Cycles | ➕ | `pms_amenities`, `pms_reservation_amenities` |
| Walk In, Day use | ✅ | `is_walk_in`, `is_day_use` |
| Cancellation / Reinstate Reasons | ✅ | `pms_reason_codes` |
| Promotions, Promotion Rules | ✅ | `pms_promotions`, `pms_reservation_night_promotions` |
| Superblocking, Block Reservation, Block Status, Cut Off | ✅ | `pms_blocks`, `pms_block_inventory` |
| Group Reservations, Rooming List | ✅ | `pms_blocks.type = group`, `group_profile_id` |
| Party reservations | ✅ | `pms_bookings` |
| Share Reservations (master/independent share) | ✅ | `share_group_id`, `pms_reservation_guests` |
| Multi guest | ✅ | `pms_reservation_guests` |
| Split, Swap room, Room Move Reasons | ✅ | `pms_reservation_room_moves`, `pms_reason_codes` |
| Suite add on, Virtual Suite | ➕ | `pms_room_components` |
| Waitlist, Waitlist Reasons | ✅ | `status = waitlist`, `waitlist_priority`, `pms_reason_codes` |
| Offer, Acceptable reservation | ✅ | `status = inquiry / tentative`, `option_date` |
| Guarantee Codes | ✅ | `pms_reservation_types` |
| Child Age Categories | ✅ | `pms_child_age_bands`, `child_band_1..3` |
| Deposits Requested | ✅ | `pms_deposit_requests` |
| Post No Show Fees | ✅ | Gece kapanışı + iptal politikası |
| Messages | ➕ | `pms_guest_messages` |
| Traces, Trace Types, Automatic Traces | ✅ | `pms_traces` (otomatik iz: kural ayarı) |
| Hints, Alerts | ✅ | `pms_alerts` |
| Guest Transportation, Pickup Types | ➕ | `pms_transport_types`, `pms_reservation_transports` |
| Luggage, Luggage Labels | ➕ | `pms_luggage` |
| Vouchers | ➕ | `pms_vouchers` |
| Virtual Numbers | ➕ | `pms_virtual_numbers`, `pms_virtual_number_assignments` |
| Registration Card | ✅ | `pms_document_templates.type = registration_card` |
| Scan Passport & ID Card | ✅ | `pms_profile_identities.file_path` |
| Correspondence, Confirmation letters | ✅ | `pms_documents` |
| History & Future, User Log | ✅ | Sorgular + `pms_audit_logs` |
| Undo Check In / Check Out | ✅ | Durum geçişi + `pms_reservation_status_logs` |
| Pre Arrival / Departure Keys, Transponder cards | ✅➕ | `pms_key_cards` |
| Leisure Bookings, Table Reservation | 🔗 | RS ONE (SPA, restoran) |
| Linked Documents | ➕ | `pms_attachments` |

## 8.3 Fiyat yönetimi

| Suite8 işlevi | Durum | RS PMS |
|---|---|---|
| Rate Codes, Rates, Rate Seasons | ✅ | `pms_rate_codes`, `pms_rate_amounts`, `pms_seasons` |
| Day Types, Day Types Calendar, Day Type Schema | ➕ | `pms_day_types`, `pms_day_type_calendar`, `pms_rate_amounts.day_type_id` |
| Frequencies | ✅ | `pms_rate_amounts.weekdays`, `pms_fixed_charges.frequency` |
| Packages, Master/Sub Packages, Package Report Groups | ✅ | `pms_packages`, `pms_package_elements` |
| BAR Availability, Rate Availability (Level) | ✅ | `pms_rate_restrictions` |
| Rate Difference Reasons | ✅ | `pms_reason_codes.category = rate_override` |
| Turnaway Reasons | ➕ | `pms_turnaways` |
| Policies (iptal, depozito) | ✅ | `pms_cancellation_policies`, `pms_deposit_policies` |
| Channel Codes, Source Codes, Market Segments, Main Market Segments | ✅ | `pms_channel_codes`, `pms_source_codes`, `pms_market_segments`, `pms_market_groups` |

## 8.4 Oda yönetimi ve kat hizmetleri

| Suite8 işlevi | Durum | RS PMS |
|---|---|---|
| Room Types, Rooms, Hotel Segments | ✅ | `pms_room_types`, `pms_rooms`, `pms_room_classes` |
| Room Features | ✅ | `pms_room_features` |
| Room Sections, Attendants, Attendant Groups | ✅ | `pms_hk_sections`, `pms_hk_attendants`, `pms_hk_assignments` |
| Room Status, Room Status Reasons | ✅ | `pms_rooms.hk_status`, `pms_room_status_logs`, `pms_reason_codes` |
| Out of Order / Out of Service | ✅ | `pms_room_outages` |
| Discrepant Rooms, Person Discrepancies | ✅ | `pms_room_discrepancies` |
| Queue Rooms | ➕ | `pms_reservations.queued_at` |
| Room Assignment, Assignment Groups | ✅ | Oda atama servisi + `is_room_locked` |
| Overbooking (otel / oda tipi) | ✅➕ | `pms_inventory_daily.overbooking_limit`, `pms_overbooking_limits` |
| House Status | ✅ | `pms_inventory_daily` |
| Room History | ✅ | Sorgu |
| Maintenance (guest service tasks) | ➕ | `pms_service_requests` (🔗 RS ONE iş takip) |
| Lost & Found | ✅ | `pms_lost_found` |
| Room Owners (devre mülk) | ⏭ | Otelde yok |
| Kiosk | ⏭ | Sonraya |

## 8.5 Profiller (CRM)

| Suite8 işlevi | Durum | RS PMS |
|---|---|---|
| Individual / Company / Travel Agent / Source / Group / Vendor / Meeting Planner | ✅ | `pms_profiles.type` |
| Address Types, Communication Types, Address & Letter Greetings | ✅ | `pms_profile_addresses`, `pms_profile_communications`, `salutation`, `letter_greeting` |
| Countries, Regions, States, Languages | ✅ | `pms_countries`, adres `state` |
| Personal Document Types | ✅ | `pms_profile_identities.document_type` |
| Linked Profiles, Family Member Roles | ✅ | `pms_profile_relations` |
| Membership Types, Bonus/Rebate Membership | ✅➕ | `pms_membership_types`, `pms_profile_memberships` |
| Marketing Info | ➕ | `pms_marketing_codes`, `pms_profile_marketing_codes` |
| Guest Comments | ✅ | `pms_notes` |
| Merge, Potential Duplicates | ✅ | `merged_into_id`, `merged_at` |
| Rate Linkage (anlaşmalı fiyat) | ✅ | `pms_profile_negotiated_rates` |
| Mailing, Easy Letter, Opportunities, Tasks & Activities, Calendar | 🔗 | RS ONE (kampanya, otomasyon, iş takip) |
| Bonus Point Cards | 🔗 | RS ONE sadakat |

## 8.6 Diğer

| Suite8 işlevi | Durum | RS PMS |
|---|---|---|
| Night Audit, Automatic Night Audit, Night Audit Reports | ✅ | `pms_night_audit_*`, `pms_business_dates` |
| Users, User Groups, User Rights, User Log | ✅ | `users`, `pms_roles`, `pms_permissions`, `pms_audit_logs` |
| Wake Up, Group Wake Up | ➕ | `pms_wakeup_calls` |
| Key interface | ➕ | `pms_key_cards` |
| Telephone Booth, PBX call accounting, Guest Rights | ⏭ | Santral entegrasyonu aşamasında (`telephone` gelir kovası hazır) |
| Police Interface | ✅ | `pms_guest_registrations` |
| Budget (manager report, revenue) | ➕ | `pms_budgets` |
| Manual Statistics | ➕ | `pms_manual_statistics` |
| Logbook | ➕ | `pms_logbook_entries` |
| Back Office Export | ✅ | Logo aktarımı (`pms_logo_*`) |
| Interfaces (IFC8, POS) | ✅ | Simphony (`pms_pos_*`) |
| Reports, Cube reports, Custom SQL | ✅ | Fidelio uyumlu `PRO_ISV_*` view'ları (aynı ad ve kolonlar) |
| Conference & Catering: Function Spaces, Seating, Capacities, Event Types, Status Definition, Menus, Menu Items, Misc Items, Conference Packages, Lost To, Responsible Departments | ✅ | Modül O: `pms_function_spaces`, `pms_event_bookings`, `pms_events`, `pms_menus`… (23 tablo) |
| Form Customization, Hotel Connector, V8 Online, OTA Upload | ⏭ | Kanal yöneticisi aşamasında |
