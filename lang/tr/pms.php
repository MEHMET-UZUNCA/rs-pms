<?php

/*
 * RS PMS — Türkçe. Fidelio'da İngilizce kalan yerleşik terimler (Check In, In House, Folio …) personelin alıştığı biçimde bırakılabilir;
 * bu dosyada Türkçe karşılıklar var, kullanıcı İngilizceye geçerek Fidelio terimlerini görür.
 */
return [
    'app' => 'RS PMS',
    'language' => 'Dil',
    'languages' => ['en' => 'English', 'tr' => 'Türkçe'],

    'menu' => [
        'reservation' => 'Rezervasyon', 'front_office' => 'Ön Büro', 'cashiering' => 'Kasa', 'housekeeping' => 'Kat Hizmetleri',
        'profiles' => 'Müşteri', 'conference' => 'Toplantı / Banket', 'miscellaneous' => 'Çeşitli', 'setup' => 'Ayarlar',
        'reservation_navigator' => 'Rezervasyon Navigatörü', 'quick_reservation' => 'Hızlı Rezervasyon', 'availability' => 'Müsaitlik',
        'rate_query' => 'Fiyat Sorgulama', 'block_reservation' => 'Blok / Allotman', 'arrivals' => 'Gelecekler', 'in_house' => 'İçeridekiler',
        'room_rack' => 'Oda Planı', 'house_status' => 'Ev Durumu', 'billing' => 'Folyo', 'posting' => 'Kayıt At', 'passer_by' => 'Ayakta Satış',
        'close_cashier' => 'Kasa Kapanışı', 'accounts_receivable' => 'Cari Hesaplar', 'room_status' => 'Oda Durumu',
        'attendants' => 'Görev Dağıtımı', 'discrepant_rooms' => 'Uyuşmazlıklar', 'profile_navigator' => 'Profil Navigatörü',
        'conference_diary' => 'Konferans Günlüğü', 'events' => 'Etkinlikler', 'night_audit' => 'Gece Kapanışı', 'logbook' => 'Vardiya Defteri',
        'reports' => 'Raporlar', 'outlets' => "Outlet'ler", 'department_codes' => 'Departman Kodları', 'taxes' => 'Vergiler', 'users' => 'Kullanıcılar',
        'coming_soon' => 'Sırası gelince açılacak',
    ],

    'common' => [
        'property' => 'Tesis', 'user' => 'Kullanıcı', 'business_date' => 'İş günü', 'logout' => 'Çıkış', 'home' => 'Başlangıç',
        'available_screens' => 'Menü çubuğundan bir ekran seçin. Hazır olan ekranlar:',
        'new' => 'Yeni', 'edit' => 'Düzenle', 'copy' => 'Kopyala', 'delete' => 'Sil', 'up' => 'Yukarı', 'down' => 'Aşağı', 'close' => 'Kapat',
        'ok' => 'Tamam', 'cancel' => 'İptal', 'search' => 'Ara', 'quick_search' => 'Hızlı arama', 'show_inactive' => 'Pasifleri göster',
        'inactive' => 'Pasif', 'active' => 'Aktif', 'all' => 'Tümü', 'none' => '<Yok>', 'same' => '<Aynı kod>', 'no_records' => 'Kayıt yok.',
        'records' => ':count kayıt', 'select_row' => 'Bir satır seçin.', 'saved' => ':name kaydedildi.', 'deleted' => ':name silindi.',
        'delete_confirm' => 'Seçili kayıt silinsin mi? Kullanımdaysa silinmez, pasife almanız gerekir.',
        'yes' => 'Evet', 'no' => 'Hayır', 'balance' => 'Bakiye', 'amount' => 'Tutar', 'date' => 'Tarih', 'description' => 'Açıklama',
        'code' => 'Kod', 'name' => 'Ad', 'type' => 'Tip', 'group' => 'Grup', 'number' => 'No', 'sort' => 'Sıra',
        'setup_title' => 'Ayarlar', 'not_loaded' => 'Bilgi alınamadı.', 'connection_error' => 'Bağlantı hatası.',
    ],

    'login' => [
        'title' => 'Giriş', 'username' => 'Kullanıcı adı', 'password' => 'Şifre', 'remember' => 'Beni hatırla', 'submit' => 'Giriş',
        'failed' => 'Kullanıcı adı ya da şifre hatalı.', 'throttled' => 'Çok fazla hatalı deneme. :seconds saniye sonra tekrar deneyin.',
    ],

    'setup' => [
        'cashiering' => 'Kasa', 'reservation' => 'Rezervasyon', 'system' => 'Sistem',
        'department_code_groups' => 'Departman kodu grupları', 'payment_types' => 'Ödeme tipleri', 'rate_codes' => 'Fiyat kodları',
        'room_types' => 'Oda tipleri', 'users_roles' => 'Kullanıcılar ve roller',
    ],

    'outlet' => [
        'title' => "Outlet'ler", 'subtitle' => 'Simphony gelir merkezleri (RVC) ve RS ONE departmanları', 'count' => ':count outlet',
        'rvc' => 'Simphony RVC', 'rvc_number' => 'Simphony RVC no', 'source' => 'Kaynak', 'default_code' => 'Varsayılan departman kodu',
        'default_auto' => '— otomatik (en küçük numara) —', 'default_hint' => "Simphony'den satış kalemi eşlemesi olmayan tutarlar bu koda yazılır.",
        'rvc_hint' => "Simphony RVC numarası, Simphony EMC'deki gelir merkezi numarasıdır (\"#\"); Simphony'den gelen kayıt bu numarayla outlet'e bağlanır.",
        'rsone_department' => 'RS ONE departmanı', 'code_count' => 'Kod sayısı', 'department_codes' => 'Departman kodları',
        'filter_codes' => 'Süz: numara ya da ad', 'only_selected' => 'Yalnız seçilenler', 'selected_count' => 'kod seçili',
        'linked_outlet' => 'Bağlı outlet', 'other_outlet_hint' => "Başka outlet'e bağlı kodlar soluk gösterilir; önce o outlet'ten çıkarılmalıdır.",
        'short' => 'Kısa', 'discount' => '(indirim)', 'new_title' => 'Yeni outlet', 'edit_title' => 'Outlet — Düzenle: :name', 'copy_title' => 'Outlet — Kopyala: :name',
        'copy_suffix' => '(kopya)',
        'types' => ['restaurant' => 'Restoran', 'bar' => 'Bar', 'room_service' => 'Oda servisi', 'minibar' => 'Minibar', 'banquet' => 'Banket',
            'spa' => 'Spa', 'shop' => 'Mağaza', 'other' => 'Diğer'],
        'sources' => ['simphony' => 'Simphony', 'rsone' => 'RS ONE', 'manual' => 'Elle (PMS)'],
    ],

    'dc' => [
        'title' => 'Departman Kodları', 'subtitle' => 'Gelir, ödeme ve sistem kodları', 'count' => ':count kod',
        'number' => 'Numara', 'code' => 'Kısa kod', 'manual' => 'Elle', 'unit' => 'Birim', 'vat' => 'KDV', 'outlet' => 'Outlet',
        'search_hint' => 'No, kısa kod ya da ad', 'folio_text' => 'Folyo metni', 'folio_text_hint' => '(boşsa açıklama basılır)',
        'arrangement' => 'Arrangement', 'government_code' => 'Resmi kod', 'allow_manual_post' => 'Elle kayıt yapılabilir',
        'return_dep' => 'İade kodu', 'unit_description' => 'Birim', 'default_price' => 'Varsayılan fiyat', 'physical_origin' => 'Physical Origin',
        'tax' => 'Vergi', 'tax_hint' => 'Vergi (tarihli) — boş bitiş: süresiz', 'valid_from' => 'Başlangıç', 'valid_to' => 'Bitiş',
        'tax_inclusive' => 'Tutar vergi dahil girilir', 'disabled' => 'Kapalı', 'in_prearrival' => 'Ön kayıtta',
        'in_billing' => 'Folyoda', 'in_ar' => 'Cari hesapta', 'in_payments' => 'Ödemelerde', 'payment_type' => 'Ödeme türü',
        'foreign_currency' => 'Döviz', 'direct_debit' => 'Direct Debit Payment', 'non_hotel_revenue' => 'Otel dışı gelir',
        'cc_commission' => 'Kart komisyonu kodu', 'percentage' => 'Yüzde', 'statistic_type' => 'İstatistik tipi', 'food_beverage' => 'Yiyecek / içecek',
        'credit_limit' => 'Kredi limiti', 'folio_subfunction' => 'Folio Subfunction', 'adjustment_dept' => 'Düzeltme kodu (Adjustment Dept)',
        'open_cash_drawer' => 'Kasa çekmecesini aç', 'hostess_enabled' => 'Hostess Enabled', 'post_tax_only' => 'Yalnız vergi kaydı',
        'rs_pms' => 'RS PMS', 'is_discount' => 'İndirim / rebate kodu', 'is_no_show' => 'No-show kodu', 'discount_of' => 'İndirimin ait olduğu asıl kod',
        'tax_history_hint' => 'Vergi değişikliği geçmişi bozmaz: eski satırı bitiş tarihiyle kapatın, yeni satırı başlangıç tarihiyle ekleyin.',
        'new_title' => 'Yeni departman kodu', 'edit_title' => 'Departman kodu — Düzenle: :name', 'copy_title' => 'Departman kodu — Kopyala: :name',
        'types' => ['revenue' => 'Debit — gelir', 'payment' => 'Credit — ödeme', 'paid_out' => 'Paid-out', 'deposit' => 'Depozito',
            'ar_transfer' => 'Cari aktarım', 'internal' => 'İç / sistem'],
        'payment_kinds' => ['cash' => 'Nakit', 'card' => 'Kredi kartı', 'bank_transfer' => 'Havale / EFT', 'city_ledger' => 'Cari (City Ledger)',
            'foreign_cash' => 'Döviz nakit', 'cheque' => 'Çek', 'voucher' => 'Voucher'],
        'buckets' => ['room' => 'Oda (Logis)', 'breakfast' => 'Kahvaltı', 'lunch' => 'Öğle yemeği', 'dinner' => 'Akşam yemeği',
            'allinc_food' => 'Her şey dahil yiyecek', 'allinc_beverage' => 'Her şey dahil içecek', 'food' => 'Yiyecek', 'beverage' => 'İçecek',
            'minibar' => 'Minibar', 'telephone' => 'Telefon', 'spa' => 'Spa', 'laundry' => 'Çamaşırhane', 'other' => 'Diğer'],
        'fb' => ['food' => 'Yiyecek', 'beverage' => 'İçecek', 'other' => 'Diğer'],
    ],

    'res' => [
        'navigator' => 'Rezervasyon Navigatörü', 'name' => 'Ad', 'room' => 'Oda', 'conf_no' => 'Rez. no', 'company_agent' => 'Şirket / Acente',
        'arrival' => 'Geliş', 'departure' => 'Çıkış', 'date_hint' => '(t, +1, 25.11)', 'all_statuses' => 'Tüm durumlar',
        'guest' => 'Misafir', 'nights' => 'Gece', 'room_type' => 'Tip', 'rate_code' => 'Fiyat kodu', 'pax' => 'Kişi', 'status' => 'Durum',
        'limit_hint' => '(ilk :limit — sorguyu daraltın)', 'keys_hint' => '↑/↓ gez · F3 ara · Alt+1…0 filtre',
        'check_in' => 'Giriş', 'check_in_confirm' => 'Seçili rezervasyonun girişi yapılsın mı?', 'checked_in' => ':name — oda :room giriş yapıldı.',
        'folio' => 'Folyo', 'cancel' => 'İptal', 'coming_soon' => 'Rezervasyon kartıyla açılacak',
        'contact' => 'İletişim', 'address' => 'Adres', 'profiles' => 'Profiller', 'folios' => 'Folyolar', 'window' => ':n. pencere',
        'history' => 'Geçmiş konaklama', 'note' => 'Not', 'room_type_short' => 'Oda / Tip', 'stay' => 'Konaklama', 'nights_count' => ':n gece',
        'adults' => ':count yetişkin', 'children' => ':count çocuk', 'not_assigned' => 'atanmadı', 'simphony' => 'Simphony',
        'room_charges_open' => 'Oda hesabı açık', 'room_charges_closed' => 'Oda hesabı kapalı', 'none' => '—', 'no_folio' => 'yok',
        'fa_number' => 'Hesap no', 'validity' => 'Geçerlilik', 'open' => 'Açık', 'closed' => 'Kapalı', 'last_postings' => 'Son kayıtlar',
        'business_date_title' => 'İş günü :date',
        'filters' => ['iceride' => 'İçeridekiler', 'gelecek' => 'Bugün gelecekler', 'gidecek' => 'Bugün gidecekler', 'cikmis' => 'Çıkış yapmış',
            'ileri' => 'İleri tarihli', 'fa' => 'İçerideki FA', 'fa-acilacak' => 'Bugün açılacak FA', 'fa-kapanacak' => 'Bugün kapanacak FA',
            'fa-kapali' => 'Kapanmış FA', 'fa-ileri' => 'İleri tarihli FA'],
        'statuses' => ['confirmed' => 'Kesin', 'tentative' => 'Opsiyon', 'waitlist' => 'Bekleme listesi', 'in_house' => 'İçeride',
            'checked_out' => 'Çıkış yaptı', 'cancelled' => 'İptal', 'no_show' => 'No-show'],
    ],

    'hk' => [
        'fo_status' => ['vacant' => 'Boş', 'occupied' => 'Dolu'],
        'hk_status' => ['clean' => 'Temiz', 'dirty' => 'Kirli', 'inspected' => 'Kontrol edildi', 'pickup' => 'Toparlanacak'],
        'service_status' => ['in_service' => 'Hizmette', 'out_of_order' => 'Arızalı (OOO)', 'out_of_service' => 'Hizmet dışı (OOS)'],
        'short' => ['vacant_clean' => 'VC', 'vacant_dirty' => 'VD', 'occupied_clean' => 'OC', 'occupied_dirty' => 'OD', 'out_of_order' => 'OOO', 'out_of_service' => 'OOS'],
        'discrepancy' => ['skip' => 'Skip', 'sleep' => 'Sleep', 'person' => 'Kişi sayısı'],
    ],
    'validation' => [
        'outlet_code_regex' => 'Kod yalnız harf, rakam, tire ve alt çizgi içerebilir.',
        'outlet_code_unique' => 'Bu kodla bir outlet zaten var.',
        'rvc_unique' => "Bu Simphony RVC numarası başka bir outlet'te kullanılıyor.",
        'dc_number_regex' => 'Numara yalnız rakamlardan oluşur (en çok 6 hane).',
        'dc_number_unique' => 'Bu numarayla bir departman kodu zaten var.',
        'payment_kind_required' => 'Ödeme kodu için ödeme türü seçilmeli.',
    ],
];
