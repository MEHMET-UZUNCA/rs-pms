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
| 3 | Oda tipi müsaitliği | Rezervasyon kartında müsaitlik kontrolü, Web API availability | Var | Müsaitlik ekranı (oda tipi × gün; satılan, opsiyon, blok, arızalı, doluluk) |
| 4 | Oda blokaj / oda planı: sürükle-bırak ile oda ve tarih değişimi, sürükleyerek rezervasyon açma | Room Rack: sürükle-bırak ile oda / tarih değişimi, kenardan uzatma, boş hücrelerden yeni rezervasyon | Var | |
| 5 | Oda planından çıkmadan tahsilat, folyo, check-out | Oda planında çubuk menüsü: kart, folyo / ödeme, giriş, çıkış | Var | |
| 6 | Oda durumları temizlik ve doluluğa göre renkli | Housekeeping ve Room Rack renkli | Var | |
| 7 | Profil eşleştirme, mükerrer profil bulma ve birleştirme | Profil birleştirme (aday bulma: ad, doğum tarihi, iletişim, belge; bütün bağlantılar taşınır) | Var | |
| 8 | Folyo: ödeme, posting, faturalama aynı ekranda | Billing: kayıt, ödeme, düzeltme, aktarma, 8 pencere, check-out; folyo yazdırma, fatura kesme ve yazdırma | Var | Faturanın Logo'ya aktarımı Logo entegrasyonuyla |
| 9 | Birden fazla para biriminde işlem | Dövizli ödeme (nakitte Cash Rate, diğerinde Posting Rate), döviz tutarı ve kur kayıtta | Var | |
| 10 | Cari hesap, kasa takibi, çek/senet vade | Kasiyer vardiyası ve kasa kapanışı (sayılan / fark, rapor); PM hesapları | Var | Cari ve çek/senet Logo'da kalır |
| 11 | E-Fatura / E-Arşiv | — | Gerek yok | KKTC'de GİB e-fatura yok; fatura Logo'dan |
| 12 | Kat hizmetleri: temizlik durumu, minibar, arıza bildirimi | Housekeeping, OOO/OOS, House Status, Lost & Found; minibar Simphony RVC 5 | Var | Kat görevlisine oda dağıtımı (Attendants) otel kullanmıyor |
| 13 | Online rezervasyon motoru, kanal yönetimi; gelen rezervasyon ön büroda | Web API: müsaitlik, fiyat, rezervasyon açma / durum / iptal; ön büroda "Web (7 gün)" görünümü | Var | Rezervasyon motoru web projesinde; kanal yöneticisi bağlantısı sonra |
| 14 | Görev yönetimi: misafir istek / şikâyeti görev olur, süre aşımında yöneticiye bildirim | Misafir istekleri: departman, son saat, öncelik, durum ve geçmiş; geciken işler üst çubukta | Var | |
| 15 | Kimlik / pasaport okuma (web kamerası) | Profilde kimlik belgeleri (şifreli); PROISV görünümleri (polis bildirimi) | Kısmen | Kimlik okuyucu donanımı seçilince bağlanır |
| 16 | Online check-in | — | Yok | Web projesiyle birlikte (misafir bilgileri ve belge önceden) |
| 17 | Mobil uygulama (oda durumu, görev, arıza sahadan) | Ekranlar telefonda çalışır (duyarlı) | Kısmen | Kat hizmetleri için telefona uygun sade ekran |
| 18 | Oda değişimi: boş oda listesi + değişim nedeni, Oda Değişim Listesi | Oda değişim nedeni (kart), Room Moves listesi (neden sonradan da girilir) | Var | |
| 19 | Grup rezervasyonu, çoklu seçimle toplu check-in, grup folyo yönlendirme | Bloklar ve Gruplar ekranı: kota, isim listesiyle rezervasyon, otomatik oda atama, toplu giriş, ana hesaba yönlendirme | Var | **Grup / blok rezervasyon**: bir grup altında çok oda, toplu oda atama, toplu check-in / check-out, ana folyoya yönlendirme (routing) |
| 20 | Geç çıkış (Late C/Out) saati | ETA / ETD kartta, Late C/O işareti kartta ve navigatörde | Var | Ücret kasadan kayıtla ya da Fixed Charge ile |
| 21 | Kirli odaya check-in yapılamaz (parametre) | Rezervasyon seçenekleri: kirli odaya giriş izin ver / uyar / engelle | Var | |
| 22 | Rezervasyon kartı sekmeleri (Misafirler, Fiyatlandır, Folyo, Diğer) | Kartın 10 sekmesinin hepsi çalışıyor (Inventory, Packages, Features, Credit Card dahil); Folyo Alt+B | Var | |

## Önerilen sıra

1. ~~Fixed Charges ve geç çıkış~~ — yapıldı.
2. ~~Ön büro kontrol paneli (#1)~~ — yapıldı.
3. ~~Grup / blok rezervasyon ve routing (#19)~~ — yapıldı. Sonra: toplu çıkış, kotanın gece gece düzenlenmesi, opsiyon tarihinde otomatik bırakma (release).
4. ~~Oda planından işlemler ve sürükle-bırak (#4, #5), Availability ekranı (#3)~~ — yapıldı.
5. ~~Fatura / folyo yazdırma, dövizli ödeme, kasa kapanışı (#8–10)~~ — yapıldı.
6. ~~Profil birleştirme (#7), oda değişim nedeni ve listesi (#18), kirli odaya giriş ayarı (#21)~~ — yapıldı.
7. ~~Misafir istekleri / şikâyetler (#14)~~ — yapıldı.
8. Web rezervasyon yazma API'si, online check-in, kimlik okuyucu (#13, #15, #16) — web projesi ve donanımla birlikte.
