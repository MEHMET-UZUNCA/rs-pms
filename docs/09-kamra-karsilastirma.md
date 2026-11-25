# 9. Kamra karşılaştırması

Kamra PMS deposundaki **148 veri tipinin (doctype) tamamı** RS PMS tasarımıyla tek tek karşılaştırıldı (betikle, eksiksiz).

Gösterim: ✅ tasarımda zaten var · ➕ bu karşılaştırmada eklendi · 🔗 RS ONE / Simphony / Logo'da · ⏭ kapsam dışı

| Durum | Sayı |
|---|---|
| ✅ | 78 |
| ➕ | 28 |
| 🔗 | 23 |
| ⏭ | 19 |

| Kamra veri tipi | Durum | RS PMS karşılığı |
|---|---|---|
| `agent_action_log` | ⏭ | Yapay zekâ / MCP / Kamra platformu — kapsam dışı |
| `ai_assistant_settings` | ⏭ | Yapay zekâ / MCP / Kamra platformu — kapsam dışı |
| `allowance_request` | ➕ | pms_approval_requests |
| `banquet_checklist_template` | ➕ | pms_checklist_templates |
| `banquet_checklist_template_task` | ➕ | pms_checklist_steps |
| `banquet_dish` | ✅ | pms_menu_items |
| `banquet_function_item` | ✅ | pms_event_items |
| `banquet_function_task` | ✅ | pms_event_tasks |
| `banquet_menu` | ✅ | pms_menus |
| `banquet_menu_course` | ✅ | pms_menu_menu_items.course |
| `banquet_menu_dish` | ✅ | pms_menu_menu_items |
| `banquet_open_item` | ➕ | pms_event_open_items |
| `banquet_payment_term` | ✅ | pms_event_payment_schedules |
| `banquet_quote_revision` | ✅ | pms_event_quotes |
| `banquet_receipt` | ✅ | pms_postings (etkinlik folyosu) |
| `banquet_selection` | ✅ | pms_event_menus / pms_event_items |
| `banquet_service_item` | ✅ | pms_event_resources |
| `cancellation_tier` | ✅ | pms_cancellation_policy_rules |
| `cancelled_invoice` | ✅ | pms_invoices.status = cancelled |
| `cash_transfer` | ➕ | pms_cash_transfers |
| `cashier` | ✅ | pms_cashiers (+ max_cash_refund, allowed_payment_codes) |
| `cashier_pin` | ➕ | pms_cashiers.pin_hash, pin_attempts, locked_until |
| `cashier_session` | ✅ | pms_cashier_shifts + pms_cashier_shift_totals |
| `cashier_terminal` | ➕ | pms_cashier_terminals |
| `cashier_transaction` | ✅ | pms_postings |
| `channel_ari_outbox` | ➕ | pms_channel_ari_queue |
| `channel_ari_state` | ➕ | pms_channel_ari_queue.sent_values |
| `channel_manager_connection` | ➕ | pms_channel_connections |
| `channel_provider_connection` | 🔗 | RS ONE bildirim kanalları |
| `channel_room_mapping` | ➕ | pms_channel_mappings |
| `city_ledger_account` | ✅ | pms_ar_accounts (+ restricted_reason) |
| `city_ledger_entry` | ✅ | pms_ar_invoices / pms_ar_payments |
| `company_billing_rule` | ✅ | pms_routing_rules |
| `company_payment_request` | ✅ | pms_ar_payments + pms_approval_requests |
| `copilot_conversation` | ⏭ | Yapay zekâ / MCP / Kamra platformu — kapsam dışı |
| `corporate_account` | ✅ | pms_profiles (type = company) |
| `credit_note` | ✅ | pms_invoices.type = credit_note |
| `daily_kpi_snapshot` | ✅ | pms_daily_statistics |
| `discount_voucher` | ✅ | pms_promotions (+ promo_code, max_uses, times_used) |
| `erpnext_sync_log` | ✅ | pms_logo_export_batches (Logo karşılığı) |
| `exchange_rate` | ✅ | pms_exchange_rates (+ max_exchange_amount) |
| `exchange_transaction` | ➕ | pms_currency_exchanges |
| `experience` | ✅ | pms_items / pms_amenities |
| `external_pos_charge` | ✅ | pms_pos_postings |
| `floor` | ✅ | pms_floors |
| `folio` | ✅ | pms_folios |
| `folio_charge` | ✅ | pms_postings |
| `folio_ledger_entry` | ✅ | pms_postings.ledger |
| `folio_payment` | ✅ | pms_postings (kind = payment) |
| `folio_reprint` | ✅ | pms_invoices.print_count + pms_documents |
| `group_booking` | ✅ | pms_blocks (+ attrition_pct, master_reservation_id, deposit_due_date) |
| `group_room_block` | ✅ | pms_block_inventory |
| `guest` | ✅ | pms_profiles (+ photo_path, marketing_consent_source) |
| `holiday_package` | ✅ | pms_rate_codes (+ package_nights) |
| `hosting_enquiry` | ⏭ | Yapay zekâ / MCP / Kamra platformu — kapsam dışı |
| `housekeeping_task` | ✅ | pms_hk_assignments (+ priority, due_by, escalation_level, reject_reason) |
| `hurdle_rate` | ➕ | pms_occupancy_rate_rules |
| `ingredient` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `ingredient_ledger_entry` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `ingredient_stock` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `itinerary` | ✅ | pms_bookings |
| `kamra_app` | ⏭ | Yapay zekâ / MCP / Kamra platformu — kapsam dışı |
| `kamra_connect_settings` | ⏭ | Yapay zekâ / MCP / Kamra platformu — kapsam dışı |
| `kamra_custom_report` | ➕ | pms_saved_reports |
| `kamra_notification` | ➕ | pms_notifications |
| `kamra_notification_read` | ➕ | pms_notification_reads |
| `kamra_service_account` | ⏭ | Yapay zekâ / MCP / Kamra platformu — kapsam dışı |
| `kitchen_printer` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `kitchen_printer_route` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `laundry_order` | ➕ | pms_laundry_orders |
| `laundry_order_item` | ➕ | pms_laundry_order_items |
| `laundry_rate` | ➕ | pms_laundry_rates |
| `long_stay_discount` | ✅ | pms_promotions (min_nights + yüzde) |
| `lost_and_found_item` | ✅ | pms_lost_found |
| `mcp_oauth_client` | ⏭ | Yapay zekâ / MCP / Kamra platformu — kapsam dışı |
| `mcp_oauth_grant` | ⏭ | Yapay zekâ / MCP / Kamra platformu — kapsam dışı |
| `meal_plan` | ✅ | pms_packages (type = board) |
| `menu_item` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `menu_item_ingredient` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `night_audit_log` | ✅ | pms_night_audit_steps |
| `night_audit_run` | ✅ | pms_night_audit_runs |
| `outbound_webhook` | ➕ | pms_webhooks |
| `outbound_webhook_delivery` | ➕ | pms_webhook_deliveries |
| `payment_gateway_settings` | ✅ | pms_settings |
| `petty_cash_voucher` | ✅ | pms_postings (kind = paid_out) + pms_approval_requests |
| `pos_order` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `pos_order_item` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `pos_outlet` | ✅ | pms_outlets |
| `pos_outlet_waiter` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `pos_table_reservation` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `print_job` | 🔗 | Simphony (restoran POS) / RS ONE stok |
| `proforma_folio` | ✅ | pms_invoices.type = proforma |
| `property` | ✅ | pms_properties + pms_settings |
| `property_faq` | ✅ | pms_settings |
| `property_photo` | ✅ | pms_media |
| `property_setting` | ✅ | pms_settings |
| `rate_guardrail` | ➕ | pms_rate_guardrails |
| `rate_plan` | ✅ | pms_rate_codes |
| `rate_restriction` | ✅ | pms_rate_restrictions |
| `reason_code` | ✅ | pms_reason_codes |
| `recreation_booking` | 🔗 | RS ONE SPA / rekreasyon |
| `recreation_customer` | 🔗 | RS ONE SPA / rekreasyon |
| `recreation_facility` | 🔗 | RS ONE SPA / rekreasyon |
| `recreation_membership` | 🔗 | RS ONE SPA / rekreasyon |
| `recreation_plan` | 🔗 | RS ONE SPA / rekreasyon |
| `recreation_plan_facility` | 🔗 | RS ONE SPA / rekreasyon |
| `recreation_therapist` | 🔗 | RS ONE SPA / rekreasyon |
| `recreation_treatment` | 🔗 | RS ONE SPA / rekreasyon |
| `recreation_visit` | 🔗 | RS ONE SPA / rekreasyon |
| `reservation` | ✅ | pms_reservations (+ checked_in_by, checked_out_by, room_assigned_by/at) |
| `reservation_night_rate` | ✅ | pms_reservation_nights |
| `revenue_budget` | ✅ | pms_budgets |
| `room` | ✅ | pms_rooms |
| `room_block` | ✅ | pms_room_outages |
| `room_discrepancy` | ✅ | pms_room_discrepancies |
| `room_type` | ✅ | pms_room_types |
| `room_type_media` | ✅ | pms_media |
| `season` | ✅ | pms_seasons + pms_season_dates |
| `security_deposit` | ➕ | pms_security_deposits |
| `sellable_unit` | ✅ | pms_room_components |
| `sellable_unit_room` | ✅ | pms_room_components |
| `service_ticket` | ✅ | pms_service_requests |
| `shift_handover` | ✅ | pms_logbook_entries + pms_cashier_shifts |
| `sop_step` | ➕ | pms_checklist_steps |
| `sop_template` | ➕ | pms_checklist_templates |
| `stay_addon` | ✅ | pms_reservation_items / pms_fixed_charges |
| `stay_instruction` | ✅ | pms_traces |
| `stay_occupant` | ✅ | pms_reservation_guests |
| `tax_withheld` | ➕ | pms_tax_withholdings |
| `ticket_event` | ➕ | pms_service_request_events |
| `transaction_code` | ✅ | pms_transaction_codes |
| `travel_agent` | ✅ | pms_profiles (type = travel_agent) |
| `turnover_profile` | ➕ | pms_hk_task_types |
| `unit_owner` | ⏭ | Devre mülk / ev sahibi yönetimi — otelde yok |
| `unit_owner_cost` | ⏭ | Devre mülk / ev sahibi yönetimi — otelde yok |
| `unit_owner_room_type` | ⏭ | Devre mülk / ev sahibi yönetimi — otelde yok |
| `unit_owner_unit` | ⏭ | Devre mülk / ev sahibi yönetimi — otelde yok |
| `unit_owner_user` | ⏭ | Devre mülk / ev sahibi yönetimi — otelde yok |
| `vendor` | ⏭ | Tedarikçi faturası (borçlar) — Logo Tiger'da |
| `vendor_bill` | ⏭ | Tedarikçi faturası (borçlar) — Logo Tiger'da |
| `vendor_bill_payment` | ⏭ | Tedarikçi faturası (borçlar) — Logo Tiger'da |
| `venue` | ✅ | pms_function_spaces |
| `venue_amenity` | ✅ | pms_event_attributes |
| `venue_booking` | ✅ | pms_event_bookings + pms_events |
| `venue_section` | ✅ | pms_function_spaces.parent_id |
| `whatsapp_message` | 🔗 | RS ONE bildirimler (SMS/WhatsApp/e-posta) |
| `zatca_invoice` | ⏭ | Ülkeye özel (Hindistan/Suudi) — KKTC için gerekmez |
| `zatca_settings` | ⏭ | Ülkeye özel (Hindistan/Suudi) — KKTC için gerekmez |
