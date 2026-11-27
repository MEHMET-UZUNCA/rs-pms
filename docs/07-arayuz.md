# 7. Arayüz

**İlke: Personel Fidelio Suite8'den geçerken yeniden öğrenmek zorunda kalmamalı.**
Bu yüzden ekranların **yerleşimi, adları, sekme sırası ve klavye kısayolları Fidelio Suite8 ile aynıdır.**
**Birebir kopya değil, benzer:** Otelin kullanmadığı alanlar ve menüler alınmaz (otel kararı, 26.11.2026). Bir ekran yapılırken
personelin gerçekten kullandığı alanlar sorulur; terimler ve kısayollar Fidelio'daki gibi kalır.
Görsel olarak (renk, yazı tipi, düzen inceliği) modern ve sade bir web arayüzü kullanılır; QloApps yönetim panelinin liste ve filtre
ekranları ile Kamra'nın "Bugün" ekranı bu görsel dil için örnektir. Arayüz Türkçedir; RS ONE ile aynı bileşenler (Blade + Tailwind) kullanılır.

Kaynak: Oracle *Suite8 Quick Keys / Introduction / Reservations / Cashiering / Front Desk / Rooms Management / Miscellaneous Manual V8.9*.

## 7.1 Ana düzen (Fidelio Suite8 ile aynı)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ Menü çubuğu: Rezervasyon · Ön Büro · Kasa · Kat Hizmetleri · Müşteri ·       │
│              Toplantı/Banket · Hızlı Tuşlar · Çeşitli · Ayarlar · Pencereler │
├───────────┬──────────────────────────────────────────────────────────────────┤
│ Kısayol   │ Navigator ekranı (Fidelio düzeni)                                │
│ çubuğu    │ ┌──────────────────────────────────────────────────────────────┐ │
│ (renkli   │ │ Sorgu alanları (ad, oda, rez. no, tarih, durum filtreleri)   │ │
│ gruplar)  │ ├──────────────────────────────────────────────────────────────┤ │
│           │ │ Sonuç tablosu (kolonlar kullanıcıya göre ayarlanır)          │ │
│ Favoriler │ ├──────────────────────────────┬───────────────────────────────┤ │
│ Rezerv.   │ │ Ağaç (adres, iletişim, rez., │ Bilgi paneli (Fidelio "HTML   │ │
│ Ön büro   │ │ folyo, notlar, geçmiş)       │ view"): seçili kaydın özeti   │ │
│ Kasa      │ └──────────────────────────────┴───────────────────────────────┘ │
│ ...       │                                                                  │
├───────────┴──────────────────────────────────────────────────────────────────┤
│ Durum çubuğu: Tesis · Kullanıcı · Kasiyer · İŞ GÜNÜ (tarih kutusu) · Bildirim│
└──────────────────────────────────────────────────────────────────────────────┘
```

- **Menü çubuğu** Fidelio'daki ana menülerle aynı sırada.
- **Kısayol çubuğu (Shortcut Bar):** solda renkli gruplar; kullanıcı kendi **Favoriler** grubunu oluşturur, sağ tıkla ekler/çıkarır, sürükleyerek sıralar (Fidelio ile aynı).
- **Navigator ekranları:** Rezervasyon, Profil, Kasa (Billing), Blok, Etkinlik ekranlarının hepsi aynı üç bölümlü yapıdadır: üstte sorgu, ortada sonuç tablosu, altta ağaç + bilgi paneli. Bilgi paneli çift tıkla gizlenir/açılır, kullanıcı tercihi saklanır.
- **Mavi detay oku:** Bir sayının yanındaki ok o sayının listesini açar (ör. Ev Durumu'nda "Arızalı oda 3 →").
- **Tarih kutusu:** Her ekranda tarih kutusu Fidelio'daki gibi çalışır (yazarak `+1`, `-7`, `t` = bugün; takvimden seçim).
- **Tablolar:** Kolonlar sürükle-bırakla sıralanır, gizlenir, genişlik kaydedilir (kullanıcı bazında).

### Dil

- **Varsayılan dil İngilizce**; terimler Fidelio Suite8 ile birebir aynı (Reservation Navigator, In House, Arrivals, Departures, Check In,
  Check Out, Billing, Department Codes, Business Date; kat hizmetlerinde Vacant / Occupied, Clean / Dirty / Inspected / Pick Up,
  Out of Order / Out of Service).
- İkinci dil **Türkçe**. Her kullanıcı üst çubuktaki **EN / TR** düğmesiyle dilini seçer; seçim kullanıcı kaydında saklanır
  (giriş ekranında da seçilebilir).
- Metinler `lang/en/pms.php` ve `lang/tr/pms.php` dosyalarındadır; iki dosyada aynı anahtarların bulunduğu test edilir.
  Yeni dil eklemek: `lang/<kod>/pms.php` + `config/app.php` → `locales`.

## 7.2 Klavye kısayolları (Fidelio Suite8 ile birebir)

### Genel
| Tuş | İşlev |
|---|---|
| `F1` | Yardım |
| `Alt + F4` | Açık pencereyi kapat |
| `F10` | Tüm pencereleri kapat (Close All) |
| `F8` | Oturumu kilitle / kullanıcı değiştir (Login ekranı) |
| `F12` | İmleci sorgu kutusundan sonuç tablosunun ilk satırına taşı |
| `Alt + harf` | Altı çizili harfi olan alana git (ör. Profil aramada `Alt + A` → Ad) |
| `Alt + ↓` | Açılır listeyi aç |
| `Tab` / `Shift + Tab` | Sonraki / önceki alan |
| `Ctrl + Tab` / `Ctrl + Shift + Tab` | Sonraki / önceki sekme |
| `Esc` | İşlemi iptal et, açılır listeyi kapat |
| `Home` / `End` | Alanın başı / sonu |
| `Shift + F8` | Arayüz işlevleri (anahtar kartı, uyandırma, telefon hakkı) |

### Rezervasyon ve ön büro
| Tuş | İşlev |
|---|---|
| `Ctrl + N` | Yeni rezervasyon |
| `Ctrl + Q` | Hızlı rezervasyon |
| `Ctrl + D` | Müsaitlik |
| `Ctrl + Shift + D` | Oda tipi müsaitliği |
| `Ctrl + Shift + M` | Azami müsaitlik |
| `Ctrl + R` | Fiyat sorgulama (Rate Query) |
| `Ctrl + L` | Oda planı (Room Rack) |
| `Ctrl + F` | Kat planı (Floor Plan) |
| `Ctrl + S` | Oda arama |
| `Ctrl + W` | Bekleme listesi önceliği |
| `Ctrl + Shift + S` | Otel segmenti istatistiği |
| `Ctrl + Shift + G` | Hedefler (Goals) |
| `Shift + Alt + L` | Vardiya defteri (Logbook) |
| `Shift + Alt + R` | Döviz hesaplayıcı |

### Rezervasyon kartı sekmeleri
| Tuş | Sekme |
|---|---|
| `Ctrl + 1` | Rezervasyon |
| `Ctrl + 2` | Envanter (ödünç eşya) |
| `Ctrl + 3` | Profiller (misafir, şirket, acente, kaynak, grup) |
| `Ctrl + 4` | Paketler |
| `Ctrl + 5` | Gece gece fiyat (Grid) |
| `Ctrl + 6` | Özellikler / özel istekler |
| `Ctrl + 7` | Kredi kartı |
| `Ctrl + 8` | Sabit ücretler |
| `Ctrl + 9` | Notlar |
| `Ctrl + 0` | Yemek planı / özel özellikler |

### Rezervasyon ve kasa navigatöründe hızlı filtre
| Tuş | Filtre |
|---|---|
| `Alt + 1` | İçerideki rezervasyonlar |
| `Alt + 2` | Bugün gelecekler |
| `Alt + 3` | Bugün gidecekler |
| `Alt + 4` | Çıkış yapmış rezervasyonlar |
| `Alt + 5` | İleri tarihli rezervasyonlar |
| `Alt + 6` | İçerideki rezervasyonsuz hesaplar (FA) |
| `Alt + 7` | Bugün açılacak FA'lar |
| `Alt + 8` | Bugün kapanacak FA'lar |
| `Alt + 9` | Kapanmış FA'lar |
| `Alt + 0` | İleri tarihli FA'lar |

### Kasa
| Tuş | İşlev |
|---|---|
| `Ctrl + B` | Folyo / Billing |
| `Ctrl + E` | Kayıt at (Posting) |
| `Ctrl + T` | Acente komisyonları (Travel Agent Processing) |

### Toplantı / banket
| Tuş | İşlev |
|---|---|
| `F3` | Konferans günlüğü (salon takvimi) |
| `Ctrl + F3` | Salon doluluğu |
| `Ctrl + Shift + F3` | Etkinlik boşlukları |
| `Shift + Alt + F3` | Konferans kat planı |

### Diğer
| Tuş | İşlev |
|---|---|
| `F4` | Takvim (görev ve etkinlikler) |
| `Ctrl + Shift + R` | Raporlar |
| `Alt + I` / `Alt + E` | Not alanında şablon metni içe al / dışa ver |

Tarayıcının kendi kısayollarıyla çakışanlar (`Ctrl + N`, `Ctrl + R`, `Ctrl + L`, `Ctrl + S`, `Ctrl + W`, `Ctrl + D`, `Ctrl + F`, `Ctrl + T`, `Ctrl + E`, `Ctrl + B`, `Ctrl + Q`, `F3`, `F4`, `F12`, `Alt + F4`):
uygulama bu tuşları yakalar ve Fidelio işlevini çalıştırır. `Ctrl + W`, `Ctrl + N`, `Ctrl + T` ve `Alt + F4` gibi bazı tuşları tarayıcı
sekmede yakalamaya izin vermez; bu yüzden RS PMS, resepsiyon bilgisayarlarında **uygulama modunda** (tam ekran, sekmesiz pencere,
Chrome/Edge "uygulama olarak yükle") çalıştırılır. Bu modda bütün Fidelio tuşları çalışır.

## 7.3 Ekranlar (Fidelio adları ve içerikleriyle)

| Fidelio ekranı | RS PMS ekranı | İçerik |
|---|---|---|
| Reservation Navigator | Rezervasyon Navigatörü | Sorgu + sonuç + ağaç/bilgi paneli; `Alt+1..0` filtreleri |
| New / Edit Reservation | Rezervasyon Kartı | `Ctrl+1..0` sekmeleri; profil bağlama, gece gece fiyat, paylaşım, iz, uyarı |
| Quick Reservation | Hızlı Rezervasyon | Tek ekranda ad, tarih, tip, fiyat |
| Availability / Room Type Availability | Müsaitlik | Oda tipi × gün; blok, opsiyon, OOO; kaçan talep kaydı |
| Rate Query | Fiyat Sorgulama | Tarih + kişi → fiyat kodları × oda tipleri; paket ve gün tipi takvimi |
| Billing | Folyo | Alt+F folyo yazdır, Alt+I fatura kes; ödemede döviz seçimi |
| Close Cashier | Kasa Kapanışı | Açık vardiyanın ödeme toplamları, sayılan tutar, F10 kapat, Alt+R rapor |
| Room Rack | Oda Planı | Odalar × günler; sürükle-bırak oda değiştir, uzat, kısalt; giriş/çıkış, OOO |
| Floor Plan | Kat Planı | Katlara göre oda kutuları; durum renkleri |
| Room Search | Oda Arama | Özellik, kat, tip, durum filtreleri |
| House Status | Ev Durumu | Bugün/yarın: dolu, boş, gelen, giden, OOO/OOS (mavi detay oklarıyla) |
| Arrivals / In House | Gelecekler / İçeridekiler | Toplu giriş, oda atama, anahtar, kimlik |
| Billing | Folyo | 8 pencere sekmesi, kayıt at, ters kayıt, transfer, böl, yönlendir, ödeme, fatura |
| Posting | Kayıt At | Toplu / tek kayıt girişi |
| Passer By | Ayakta Satış | Rezervasyonsuz hızlı satış |
| Close Cashier | Kasa Kapanışı | Sayım, fark, kasa raporu |
| Accounts Receivable | Cari Hesaplar | Açık kalemler, yaşlandırma, tahsilat eşleme, ekstre, ihtar |
| Housekeeping / Attendants | Kat Hizmetleri | Oda durum panosu, görev dağıtımı, telefon ekranı |
| Discrepant Rooms | Uyuşmazlıklar | Skip / sleep / kişi sayısı |
| Night Audit | Gece Kapanışı | Kontrol listesi, adım adım ilerleme, raporlar |
| Room Rack | Oda Planı | Çubuğa tıkla / Enter: menü (kart, folyo B, giriş G, çıkış O); çubuğu sürükle: oda / tarih; sağ kenar: çıkış tarihi; boş hücrelerde sürükle: yeni rezervasyon |
| Availability | Müsaitlik | Oda tipi × gün; Alt+← / Alt+→ dönem; sayıya tıkla: yeni rezervasyon |
| Block / Group Reservation | Bloklar ve Gruplar | Blok listesi (Alt+1–3), yeni grup Alt+N, yeni kontenjan Alt+K; blok ekranında bloktan rezervasyon Alt+P, oda ata Alt+A, toplu giriş Alt+G, ana hesaba yönlendir Alt+M |
| Ana sayfa | Ön büro kontrol paneli | Bugünün kutuları (giriş, çıkış, içeride, doluluk, ortalama fiyat, boş / kirli oda, kuyruk), 14 günlük doluluk tahmini, son kapanan günün gelirleri, bugün gelen / çıkan listeleri |
| Profiles | Profil Navigatörü | Bireysel, şirket, acente, kaynak, grup; birleştirme. Yeni (`Alt+N`): önce *Select Profile Type* penceresi (↑/↓, Enter). Profil kartı tek sayfa: Fidelio'nun Profile ve More Fields sekmelerinden yalnız kullanılan alanlar (soyad, ad, pozisyon, departman, dil, unvan, hitap, mektup hitabı, adres, cep / telefon / e-posta, Active?, kara liste ve nedeni (zorunlu; rezervasyonda uyarı), doğum tarihi / yeri, cinsiyet, uyruk; No Mailing, No E-Mailing, Save Profile, pazar segmenti, fiyat kodu, VIP, evlilik yıldönümü, kimlik belgeleri). Vendor, Meeting Planner, Booker, Contact tipleri ve kullanılmayan alanlar alınmadı. |
| Block Reservation | Blok / Allotman | Blok başlığı, gün × tip tablosu, rooming list, release |
| Conference Diary | Konferans Günlüğü | Salon × saat çizelgesi, sürükle-bırak etkinlik |
| Logbook | Vardiya Defteri | Departman notları, okundu bilgisi |

### Rezervasyon Navigatörü (yapıldı)

Sorgu: Ad (`Alt+A`), oda, rezervasyon no, şirket/acente, geliş, çıkış, durum; `F3` ara. Tarih kutusu Fidelio gibi: `t` iş günü,
`+1` / `-7`, `25.11`, `25.11.2026`. Hızlı filtreler `Alt+1 … Alt+0` (yukarıdaki tablo). `↑/↓` satır seçer; seçili kaydın ağacı
(iletişim, adres, profiller, folyolar, geçmiş) ve bilgi paneli altta sayfa yenilenmeden açılır. `Alt+G` giriş (check-in).

### Rezervasyon Kartı (yapıldı)

Navigatörde `Ctrl+N` (ya da `Alt+N`) yeni kart, `Enter` / çift tık / `Alt+E` seçili rezervasyonu açar. Sekmeler `Ctrl+1 … Ctrl+0`
(Fidelio sırası); çalışanlar: `Ctrl+1` Rezervasyon, `Ctrl+3` Profiller, `Ctrl+5` Gece fiyatları, `Ctrl+9` Notlar — diğerleri sırası gelince.
Misafir: soyadla var olan profil aranır ya da yeni misafir yazılır. Tarih kutuları Fidelio girişini kabul eder (`+2`, `25.11`).
Oda listesi seçili oda tipine göre süzülür ve odanın durumunu gösterir (Vacant / Dirty …). `F10` kaydet, `Esc` kapat.
Kayıt rezervasyon servisinden geçer: onay numarası, gece fiyatları (elle fiyat korunur), müsaitlik, oda çakışması ve arıza kontrolü.

### Folyo — Billing (yapıldı)

Navigatörde `Alt+B` (Billing düğmesi) seçili rezervasyonun ya da rezervasyonsuz hesabın (FA) folyosunu açar. Pencereler `Alt+1 … Alt+8`
(pencere ilk kayıtta açılır). `Alt+P` kayıt (departman kodu, birim fiyat KDV dahil, adet, açıklama), `Alt+Y` ödeme (tutar pencere bakiyesiyle
dolu gelir), `Alt+R` düzeltme (ters kayıt; bir kayıt bir kez), `Alt+T` seçili kaydı başka pencereye transfer, `Alt+O` check-out (bakiye sıfır
olmalı). Düzeltmeler varsayılan gizli, "Show corrections" ile görünür. Elle kayda kapalı kodlar (Allow Manual Post / Disabled in Billing) kullanılamaz.

### Oda Planı — Room Rack (yapıldı)

`Ctrl+L` her ekrandan açar. Odalar × günler; odası atanmış rezervasyonlar çubuk (In House yeşil, Expected mavi, Tentative turuncu),
arızalı (OOO) ve hizmet dışı (OOS) dönemler gri; dönem başından önce başlayan konaklama "‹" ile. Satırda oda tipi ve durum kısaltması
(VC, VD, OC, OD). Altta her gün dolu oda / doluluk yüzdesi ve odası atanmamış rezervasyon sayısı. 7 / 14 / 30 gün, `Alt+←` / `Alt+→`
önceki/sonraki dönem, iş gününe dön, oda tipi ve kat süzgeci. Çubuğa tıklama rezervasyon kartını açar. (Sürükle-bırakla oda değiştirme sonra.)

### Gece Kapanışı — Night Audit (yapıldı)

Çeşitli → Gece Kapanışı. Kapanış öncesi kontrol listesi: çıkışı yapılmamış misafirler ve açık kasa vardiyaları **engeller**, gelmeyen
misafirler **uyarıdır** (kapanışta No Show olur); her kontrolde ilgili rezervasyonlar ve folyo bağlantısı. Engel varken yalnız
`night_audit.override` yetkisi olan kullanıcı gerekçe yazarak geçer (gerekçe kullanıcı adıyla kaydedilir). `F10` başlatır; adımlar:
kontroller → no-show → oda ve paket ücretleri → istatistik ve gelir → iş gününü ilerlet. Yarıda kalan kapanış "devam et" ile kaldığı yerden
sürer, çift kayıt üretmez. Son kapanan günün doluluk, satılan oda, ADR, RevPAR, oda ve toplam gelir özeti ve departman kodu bazında gelir; geçmiş.

### Kat Hizmetleri — Housekeeping (yapıldı)

Kat Hizmetleri → Oda Durumu. Fidelio kodlarıyla: INS Inspected, CL Clean, DI Dirty, TU T.U (temizlik durumu) ve DND, NL No Luggage,
SO Sleep Out, NS No Service, DBL Double Lock (işaret). Oda listesi: blok+kat, ön büro durumu (Vacant/Occupied), temizlik durumu, işaret,
OOO/OOS, içerideki misafir, bugün giriş/çıkış. Sayaçlı süzgeçler `Alt+1 … Alt+8` (Vacant Dirty, Vacant Clean, Occupied Dirty, Occupied Clean,
Inspected, Arrivals, Departures, OOO/OOS), blok ve kat süzgeci. Odalar işaretlenip sağdaki düğmeyle toplu durum verilir. OOO/OOS: tarih
aralığı, sebep, not; içeride misafiri ya da o dönemde odası atanmış rezervasyonu olan odaya OOO girilemez; kaldırınca oda kirli döner.
Her değişiklik oda durum geçmişine yazılır.

### Ayar listeleri (Fidelio "Setup → Configuration" düzeni)

Yapılan ekranlar: **Ayarlar → Outlet'ler**, **Ayarlar → Departman Kodları**. Bütün ayar listeleri aynı düzende olacak.

| Tuş | İşlev |
|---|---|
| `↑` / `↓` | Satırlar arasında gezin |
| `Enter` / çift tık | Seçili kaydı düzenle |
| `Alt + N` / `Insert` | Yeni |
| `Alt + E` | Düzenle |
| `Alt + C` | Kopyala |
| `Alt + D` / `Delete` | Sil (onay sorar; kullanımdaki kayıt silinmez, pasife alınır) |
| `Alt + U` / `Alt + O` | Yukarı / aşağı taşı (sıra) |
| `F10` / `Ctrl + Enter` | Düzenleme penceresinde Tamam |
| `Esc` | İptal / pencereyi kapat |

Hızlı arama kutusu yazarken listeyi anında süzer; `Enter` sunucuda arar. "Pasifleri göster" pasif kayıtları da listeler.

## 7.4 Modern ekler (Fidelio'da olmayan, personeli yormayan)

- **Bugün ekranı** (Kamra): giriş yapan kullanıcının ilk gördüğü özet. Fidelio ekranlarına kısayollarla tek tuşla geçilir.
- **Genel arama kutusu** (üst çubuk): oda no, ad, rezervasyon no, telefon — tek kutudan.
- **Çevrimiçi giriş** onay kuyruğu, **onay istekleri** (amire giden işlemler) bildirimleri.
- **Telefon/tablet ekranları:** kat görevlisi ve teknik servis için ayrı, sade ekranlar.

## 7.5 Onay sonrası ilk iş

Kodlamadan önce **Rezervasyon Navigatörü**, **Rezervasyon Kartı** ve **Oda Planı (Room Rack)** ekranlarının Fidelio yerleşiminde
tıklanabilir görsel taslakları hazırlanıp personele gösterilecek.
