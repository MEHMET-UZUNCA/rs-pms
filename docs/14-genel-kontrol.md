# 14. Genel kontrol (27.11.2026)

Bütün depo baştan sona gözden geçirildi: kod stili, çeviriler, yetki ve tesis ayrımı, iş kuralları, veritabanı, zamanlanmış işler,
kurulum / işletim, belgeler. Bulunanların bir kısmı hemen düzeltildi; kalanlar önceliğe göre aşağıda.

## Kontrol edilenler ve sonuç

| Alan | Nasıl | Sonuç |
|---|---|---|
| Testler | PHPUnit, SQLite ve MySQL | 225 test, ikisinde de geçiyor |
| Kod stili | `vendor/bin/pint --test` | 30 dosyada yalnız biçim farkı vardı → düzeltildi (`pint`), artık temiz |
| Çeviriler | en / tr anahtar karşılaştırması + koddaki bütün `__('pms.…')` anahtarları | İki dosya eşit (1.400+ anahtar). Eksik: rezervasyon durumu "inquiry", profil durumu "merged" → eklendi |
| Yetki | Bütün rotalar `permission:` ara katmanıyla mı | Evet; yetkisiz olanlar yalnız giriş / şifre / dil ve kendi içinde yetki denetleyen iki ekran |
| Tesis ayrımı | Kayıt (rezervasyon, folyo, profil, blok, fiyat …) açan her uçta "bu otelin mi" kontrolü | Hepsinde var |
| Ekran genişliği / klavye | `tests/browser` betikleri (docs/13) | Taşma yok, 17 adımlık klavye akışı geçiyor |

## Hemen düzeltilenler

1. **Overbooking kontrolü yoktu.** Rezervasyon kartı, otel dolu olsa da sessizce kayıt açabiliyordu (web API kontrol ediyordu, kart etmiyordu).
   Artık: kayıt müsaitliği eksiye düşürürse geri alınır ve uyarı çıkar. Yeni **Reservation → Overbooking** ekranında oda tipi ve gün bazında
   izin verilen fazla satış girilir; bu sınır içindeki kayıtlar uyarısız geçer. Sınır üstünü yalnız `reservations.overbooking` yetkisi olan
   ("Yine de kaydet") yapabilir (SUP, IT1, FOM, FOS, REA). Yalnız not / kart gibi değişiklikler, gün zaten dolu olsa bile engellenmez.
   Web sitesi hiçbir zaman müsaitliğin üstüne satmaz. MySQL'de işaretsiz sayı hatası da bu sırada bulunup düzeltildi.
2. **Erken çıkışta çıkış tarihi** eski tarihte kalıyordu (raporlarda yanlış görünürdü) → bugüne çekiliyor, asıl tarih `original_departure_date`'te.
3. Eksik iki çeviri, kod stili, `docs/eksikler.md`'deki eskimiş "yapılacaklar" listesi.

## Kalanlar — canlıya geçmeden önce (öncelik 1)

| # | Ne | Neden önemli | Not |
|---|---|---|---|
| 1 | **Fidelio veri aktarımı** | Profiller, ileri tarihli rezervasyonlar, içerideki misafirler ve açık bakiyeler, FA / cari bakiyeleri olmadan geçiş yapılamaz | PROISV görünümleri hazır; aktarım komutu + doğrulama raporu yazılacak |
| 2 | **Yedekleme** | PMS veritabanı her gün ve gün içinde yedeklenmeli; şu an yalnız mimari belgesinde yazıyor, kurulu / denenmiş bir şey yok | Sunucuda günlük `mysqldump` + binlog, başka diske / makineye kopya, haftada bir geri yükleme denemesi |
| 3 | **Sunucu kurulum belgesi** | README yalnız geliştirme kurulumunu anlatıyor | Nginx/PHP-FPM, `.env` (APP_DEBUG=false), cron (`schedule:run`), Simphony dinleyicisi için Supervisor / systemd, log döndürme, HTTPS |
| 4 | **Raporlar** (ertelenmişti) | Gece kapanışı sonrası en az: gelen / giden / içeride, doluluk, finans (departman kodu dökümü, kasa), kat hizmetleri | Başlangıç seti docs/10-raporlar.md |
| 5 | **Simphony'de tek kayıt yolu** | Canlı akış `MessageHandler`; ChatGPT'nin yerelde yazdığı ikinci yol GitHub'a gelmedi | Gelince birleştirilecek |
| 6 | **Logo aktarımı** | Muhasebe (cari faturalar, günlük gelir fişi) | Logo REST / firma bilgileri bekleniyor (docs/05) |

## Kalanlar — iyileştirme (öncelik 2)

| # | Ne | Neden | Tahmini iş |
|---|---|---|---|
| 7 | **Değişiklik kaydı (audit log)** | `pms_audit_logs` tablosu var ama hiç yazılmıyor. Rezervasyonda fiyat / tarih / oda değişikliğini kimin yaptığı, fiyat kodu ve kullanıcı / rol değişiklikleri izlenemiyor (yalnız durum geçmişi, folyo kayıtları ve oda değişimleri izleniyor) | Orta |
| 8 | **Servis hata mesajları yalnız Türkçe** | 66 mesaj (ör. "Girişten önce oda atanmalı") dil dosyasında değil; arayüz İngilizceyken Türkçe çıkıyor | Orta (mekanik) |
| 9 | **Reinstate (iptali geri alma) ve Undo Check-in** | Fidelio'da günlük kullanılan iki işlem yok; yanlış iptal / yanlış girişte şu an elle düzeltme gerekiyor | Küçük |
| 10 | **Depozito / ön ödeme takibi** | `pms_deposit_requests` tablosu var, ekranı yok; garantili rezervasyonda "ön ödeme bekleniyor / alındı" izlenemiyor | Orta |
| 11 | **Kişisel veri (KVKK / KKTC kişisel verilerin korunması)** | Profilde "kişisel veriyi sakla" işareti var ama süre dolan misafirin anonimleştirilmesi / silinmesi yok | Küçük–orta |
| 12 | **Oturum süresi** | Ön büro bilgisayarları ortak; oturum 120 dk boşta kalınca kapanıyor | Ayar (`SESSION_LIFETIME`), otelle karar |
| 13 | Walk-in işareti, paylaşımlı oda (share), bekleme listesi | Fidelio'da var; otelin kullanıp kullanmadığı sorulacak | Küçük |
| 14 | Pansiyon değişikliğinde fiyat farkının otomatik kaydı | Multiboard'da pansiyon değişince oda fiyatı aynı kalıyor | Küçük |
| 15 | Envanter kalemi ücretinin otomatik alınması | Şimdilik Fixed Charge olarak elle | Küçük |

## Otelden beklenenler (özet)

Ayrıntı `docs/eksikler.md`: 2027 fiyatlarının tamamı, KDV sayfaları (P4556), Simphony ödeme tipleri ve BI API, Logo bilgileri, rezervasyon tipi /
pazar / kaynak kodlarının Fidelio listesi, oda özellikleri ve envanter kalemleri, web rezervasyon kodları, SMTP, 2404 / 2405 teyidi.
