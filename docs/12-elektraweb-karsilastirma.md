# Elektraweb Ön Büro ile karşılaştırma

Kaynak: Elektraweb ön büro tanıtım sayfası (elektraweb.com/on-buro), yardım sitesi (yardim.elektraweb.com: Önbüro Kontrol Paneli, Oda Planı Lite,
Rezervasyon Oluşturma) ve kullanım kılavuzu (kilavuz.elektraweb.com: Rezervasyon ve Resepsiyon İşlemleri, Room Rack, Grup Rezervasyon, Fatura Kes).
Sayfaların kendisi geliştirme ortamından açılamadı; liste arama sonuçlarındaki açıklamalardan çıkarıldı (27.11.2026).

Durum: **Var** = RS PMS'te çalışıyor · **Kısmen** = temeli var, eksik parçası yazılı · **Yok** = eklenecek · **Gerek yok** = otel kullanmıyor / başka sistemde.

## Ekran ekran

| # | Elektraweb | RS PMS | Durum | Not / yapılacak |
|---|---|---|---|---|
| 1 | Günlük durum ekranı (doluluk, gelir, tahmin, konaklayan; grafik ve liste) | Ana sayfa = Ön büro kontrol paneli | Var | **Ön büro kontrol paneli**: bugün giriş / çıkış / içeride, doluluk %, oda geliri, ADR, 7–14 günlük doluluk tahmini, kirli / arızalı oda sayısı; her kutudan ilgili listeye geçiş |
| 2 | Rezervasyon listesi: tek tıkla süzme ve gruplama | Reservation Navigator (F3, Alt+1–0 süzgeçler) | Var | Gruplama (acenta / oda tipi / pansiyona göre ara toplam) yok — kısa iş |
| 3 | Oda tipi müsaitliği | Rezervasyon kartında müsaitlik kontrolü, Web API availability | Kısmen | **Availability ekranı** (oda tipi × gün, satılabilir / satılan / OOO); Fidelio Rate Query benzeri |
| 4 | Oda blokaj / oda planı: sürükle-bırak ile oda ve tarih değişimi, sürükleyerek rezervasyon açma | Room Rack (oda-plani) görüntüleme | Kısmen | Oda planından **rezervasyon kartı, oda değişimi, tarih uzatma** (sürükle-bırak), boş hücreden yeni rezervasyon |
| 5 | Oda planından çıkmadan tahsilat, folyo, check-out | Folyo / Billing ekranı ayrı | Kısmen | Oda planında sağ tık / kısayol menüsü: Folyo, Ödeme, Check-in, Check-out |
| 6 | Oda durumları temizlik ve doluluğa göre renkli | Housekeeping ve Room Rack renkli | Var | |
| 7 | Profil eşleştirme, mükerrer profil bulma ve birleştirme | Profil arama; tabloda merged_into_id hazır | Kısmen | **Profil birleştirme** (Fidelio Merge): aynı ad / doğum tarihi / belge no / e-posta ile aday listesi, rezervasyon ve geçmiş tek profile taşınır |
| 8 | Folyo: ödeme, posting, faturalama aynı ekranda | Billing: kayıt, ödeme, düzeltme, aktarma, 8 pencere, check-out | Kısmen | **Fatura / folyo yazdırma** (PDF), fatura no serisi |
| 9 | Birden fazla para biriminde işlem | Kur tablosu (TCMB, marjlı); ödeme TL | Kısmen | **Dövizli ödeme** (EUR/GBP/USD nakit, kur ve TL karşılığı folyoda) |
| 10 | Cari hesap, kasa takibi, çek/senet vade | PM / sanal hesaplar; Logo entegrasyonu planlı | Kısmen | **Kasa (vardiya) kapanışı**: kasiyer bazında ödeme tipleri toplamı. Cari ve çek/senet Logo'da kalır |
| 11 | E-Fatura / E-Arşiv | — | Gerek yok | KKTC'de GİB e-fatura yok; fatura Logo'dan |
| 12 | Kat hizmetleri: temizlik durumu, minibar, arıza bildirimi | Housekeeping, OOO/OOS, House Status, Lost & Found; minibar Simphony RVC 5 | Var | Kat görevlisine oda dağıtımı (Attendants) otel kullanmıyor |
| 13 | Online rezervasyon motoru, kanal yönetimi; gelen rezervasyon ön büroda | Web API v1 (fiyat, müsaitlik); web sitesi ayrı proje | Kısmen | Web'den **rezervasyon yazma** API'si ve ön büroda "yeni web rezervasyonları" listesi; kanal yöneticisi tabloları hazır, bağlantı sonra |
| 14 | Görev yönetimi: misafir istek / şikâyeti görev olur, süre aşımında yöneticiye bildirim | — | Yok | **Misafir istekleri / şikâyetler (Traces)**: oda, departman, son saat, durum; geciken görev üst çubukta uyarı |
| 15 | Kimlik / pasaport okuma (web kamerası) | Profilde kimlik belgeleri (şifreli); PROISV görünümleri (polis bildirimi) | Kısmen | Kimlik okuyucu donanımı seçilince bağlanır |
| 16 | Online check-in | — | Yok | Web projesiyle birlikte (misafir bilgileri ve belge önceden) |
| 17 | Mobil uygulama (oda durumu, görev, arıza sahadan) | Ekranlar telefonda çalışır (duyarlı) | Kısmen | Kat hizmetleri için telefona uygun sade ekran |
| 18 | Oda değişimi: boş oda listesi + **değişim nedeni**, Oda Değişim Listesi | Oda değişimi var (eski oda Dirty), Room History'de görünür | Kısmen | **Değişim nedeni** kodları ve günlük **Oda değişim listesi** |
| 19 | Grup rezervasyonu, çoklu seçimle toplu check-in, grup folyo yönlendirme | Grup profil tipi | Yok | **Grup / blok rezervasyon**: bir grup altında çok oda, toplu oda atama, toplu check-in / check-out, ana folyoya yönlendirme (routing) |
| 20 | Geç çıkış (Late C/Out) saati | ETA / ETD kartta, Late C/O işareti kartta ve navigatörde | Var | Ücret kasadan kayıtla ya da Fixed Charge ile |
| 21 | Kirli odaya check-in yapılamaz (parametre) | Dolu odaya giriş engelli; kirli odaya izin var | Kısmen | Rezervasyon seçeneklerine "Kirli odaya giriş: izin ver / uyar / engelle" |
| 22 | Rezervasyon kartı sekmeleri (Misafirler, Fiyatlandır, Folyo, Diğer) | Kart: Rezervasyon, Profiller, Fiyatlar, Notlar, Meal Plan; Folyo Alt+B | Kısmen | Fixed Charges yapıldı (gece kapanışında otomatik). Boş sekmeler: Inventory, Packages, Features, Credit Card |

## Önerilen sıra

1. ~~Fixed Charges ve geç çıkış~~ — yapıldı.
2. ~~Ön büro kontrol paneli (#1)~~ — yapıldı.
3. Grup / blok rezervasyon ve routing (#19).
4. Oda planından işlemler ve sürükle-bırak (#4, #5), Availability ekranı (#3).
5. Fatura / folyo yazdırma, dövizli ödeme, kasa kapanışı (#8–10).
6. Profil birleştirme (#7), oda değişim nedeni ve listesi (#18), kirli odaya giriş ayarı (#21).
7. Misafir istekleri / şikâyetler (#14).
8. Web rezervasyon yazma API'si, online check-in, kimlik okuyucu (#13, #15, #16) — web projesi ve donanımla birlikte.
