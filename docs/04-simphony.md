# 4. Simphony entegrasyonu

Restoran, bar, oda servisi ve minibar satışları **Oracle Simphony**'de yapılır. Outlet'ler (Simphony'deki *Revenue Center*, RVC)
PMS'e buradan gelir. İki ayrı akış vardır:

| Akış | Yön | Ne zaman | Amaç |
|---|---|---|---|
| **1. Oda hesabına yazma** | Simphony → PMS (anlık) | Garson "oda hesabına" ödeme tipini seçtiğinde | Misafirin harcaması folyosuna düşer |
| **2. Günlük satış özeti** | Simphony → PMS (günde bir) | Simphony gün kapanışından sonra | Outlet cirosunun tamamı (nakit, kart dahil) Logo'ya gider, gece kapanışı raporunda görünür |

## 4.1 Bağlantı yöntemi

### Bugünkü durum (otelden alınan bilgi)

- **Simphony 18** sürümü.
- **Kayıtlar (posting)** Fidelio'ya **IFC8 üzerinden** gidiyor.
- **Oda sorguları** Simphony'den Fidelio'ya doğrudan bağlı.

```
Bugün:   Simphony 18 ──(sorgu)──────────────► Fidelio Suite8
         Simphony 18 ──(kayıt)──► IFC8 ─────► Fidelio Suite8

Hedef:   Simphony 18 ──(sorgu + kayıt)──────► RS PMS (Simphony PMS arayüzü dinleyicisi)
```

IFC8, Oracle'ın Opera/Suite8'e özel ara katmanıdır; Simphony tarafında Simphony'nin PMS protokolünü konuşur, Fidelio tarafında FIAS.
RS PMS, **Simphony'nin bugün IFC8 ile konuştuğu protokolü doğrudan konuşan bir TCP sunucusu** açar. Simphony EMC'deki PMS arayüzü
kaydının adresi RS PMS'i gösterecek şekilde değiştirilir; IFC8 devreden çıkar, ek lisans gerekmez.

### Oracle belgelerinden (Simphony 18.2) kesinleşenler

Kaynaklar: *Simphony Property Management System Interface Reference* (F12084, 18.2) ve
*Simphony Enhanced PMS Interface Specifications* (F16307, 2.9 ve sonrası). İkisi de Oracle'ın üçüncü parti PMS yazanlar için yayımladığı belgelerdir.

| Konu | Bilgi |
|---|---|
| Bağlantı | Yalnız TCP/IP. **Simphony istemci, PMS sunucu**: RS PMS bir portu dinler, Simphony bağlanır. Varsayılan port 5009 (Enhanced) — EMC'de ayarlanır |
| Bağlantı sayısı | Her fiziksel Simphony sunucusu (Service Host) için tek kalıcı bağlantı; bütün kasalar bu bağlantıdan gelir |
| Canlılık | Simphony ~5 dakikada bir "ping" gönderir (`SOH <boş kaynak> STX ETX EOT`); PMS aynısını geri gönderir |
| Çerçeve | `SOH <kaynak: kasa no 2/9 hane + arayüz adı 16> STX <veri> ETX [checksum] EOT` — TCP'de checksum yok sayılır |
| Tekrar | Her kasa 01–99 sıra numarası verir; aynı numara + `R` bayrağı gelirse PMS **son cevabını aynen tekrarlar** (çift kayıt olmaz) |
| Zaman aşımı | Simphony tarafında ayarlanabilir (önerilen 30 sn); cevap hedefimiz < 1 sn |

**İki protokol var; hangisinin kullanıldığı EMC'deki arayüz kaydından görülür:**

| | Standart PMS arayüzü (eski MICROS biçimi) | Enhanced PMS arayüzü |
|---|---|---|
| Biçim | Sabit uzunluklu alanlar | Alanlar FS (1Ch) ile ayrılmış, mesaj adlı (`IFC_CHG_PST` …) |
| Oda sorgusu | `1` Inquire: 16/19 karakter giriş → 8 satır × 16 karakter cevap | Oda hesabı isteğiyle birlikte: `IFC_CHG_PST` → `IFC_GST_SEL` misafir listesi |
| Kayıt | `2` Outlet Charge Posting: RVC, çek no, kişi sayısı, garson, ödeme tipi, tutar; **4 satış toplamı**, indirim, 2 servis, **4 vergi** | `IFC_CHG_PST`: **16 satış**, 16 indirim, 14 servis ücreti, 64 vergi kalemi, çek no, garson, RVC, kişi, tarih/saat |
| Cevap | Kabul: metin (fişe basılır) · Ret: `/` ile başlar · Misafir seçtir: `?` + 8 seçenek | `P` kaydedildi / `D` reddedildi / `E` hata + 30 karakter mesaj |
| Fiş dökümü | Yok | `FO_FI_FINAL_TENDER`: çekin metin hali (ürün ürün) — folyoda fiş detayı olarak saklanır |
| Nakit/kart çekler | Yok | **Var:** ödeme tipi arayüze bağlanırsa nakit ve kartla kapanan çekler de ("Non Front-Office") aynı mesajla gelir |

RS PMS **ikisini de** destekleyecek; ayar ekranından seçilir. Rocks Hotel'de kullanılan: **Enhanced** (ISL MF_ENH).

### Rocks Hotel'deki mevcut ayar (EMC → Setup → Interfaces, kayıt #101 "Onburolfc")

| Alan | Değer | Anlamı |
|---|---|---|
| Communication Name / ISL Script | **MF_ENH** | MICROS-Fidelio **Enhanced** PMS arayüzü — mesajlar `IFC_CHG_PST`, `IFC_GST_SEL`, `FO_FI_FINAL_TENDER` |
| Interface Type / Communications Type | 0 – PMS/SIM / **1 – TCP** | |
| TCP Host / Port | **192.168.200.11 : 5010** | IFC8'in dinlediği adres |
| Timeout / Ping Frequency / Encoding | 30 sn / 0 / ASCII | |
| Service Host | 99 – CAPS-ROCKS (192.168.200.9) | Bağlantıyı açan Simphony sunucusu |
| Seçenek 1, 2, 3, 5 | Açık | 19 haneli giriş, arayüz logu, girişsiz sorgu, **9 haneli kasa no** |
| Seçenek 4, 7, 8, 9, 10, 11, 12, 13 | Kapalı | 4 haneli kişi sayısı, mobil MICROS yok, çevrimdışı kayıt yok, ping yok, 3 haneli RVC, 4 haneli çek no, hediye/sadakat kartı yok, otomatik ters kayıt yok |

Seçenek listesi 13'te biter (tam liste alındı). Ping kapalı olduğundan bağlantının canlılığı ping ile değil, gelen mesajlarla izlenir;
otomatik ters kayıt kapalı olduğundan iptal edilen çekler Simphony'den ayrı (eksi tutarlı) kayıt olarak gelir.

**Kasadaki PMS ekranları bir ISL betiğiyle çalışır.** EMC → *Extension Application* → **ONBURO** (DbID 83) içeriği:

| # | Anahtar | Dosya | Ne |
|---|---|---|---|
| 1 | html | — | Kasada misafir listesi / rezervasyon detayı penceresi (HTML) |
| 2 | McrsExtDialogsML | McrsExtDialogsML.dll | Pencereyi açan Simphony eklentisi |
| 3 | **MF_ENH** | **MF_ENH.isl** | Fidelio PMS arayüz betiği — mesajları kuran ve çözen asıl kod |
| 4 | MF_ENH_android | MF_ENH_android.isl | Android kasalar için aynı betik |

HTML penceresinden çıkan cevap biçimi (IFC8'in kasaya döndürdüğü):
- **Misafir listesi:** satırlar `^` ile ayrılır; ilk satır başlıktır (`Room RmT #   Name        Arrive Dep   Rate   Balnc  Agnt/Srce  Group/Comp S`),
  sonraki her satır `rezervasyon_no*sabit genişlikli metin` (ör. `111*315  TTW  Piccard Jean  20/10 28/01 130.00 3271.00 …`). En çok 20 satır gösterilir.
- **Kasiyerin seçimi:** pencere `gst_dtl:<rezervasyon_no>` (Detay), `gst_rsv:<rezervasyon_no>` (Rezervasyon) ya da `close:0` döndürür.
- **Rezervasyon detayı:** `Etiket : değer` satırları `^` ile (Guest Name, Arrival, Departure, Room Type, Time, Room, Adult/Chld, Rate, All Postings, Packages …).

Betik (MF_ENH.isl) mesajların tam biçimini tanımlar; RS PMS dinleyicisi bu betiğin beklediği cevapları birebir üretecek. Betiğin metni istendi (docs/eksikler.md).

**Paralel deneme:** Simphony birden çok arayüz kaydı tutabilir (RVC başına 16 bağlantı numarası). Canlı #101'e dokunmadan
ikinci bir kayıt (#102, RS PMS adresi) açılır; ayrı bir bağlantı numarasına, **ayrı bir test ödeme tipine** ("Oda Hesabı TEST") ve
bir test kasasındaki düğmeye bağlanır. Böylece aynı çek iki sisteme birden gitmez: hangi ödeme tipi seçilirse o arayüze gider.

### Simphony EMC'de yapılacak değişiklik (geçiş gecesi)
1. *Setup → Interfaces* → mevcut PMS arayüzü kaydı → *Options*: **TCP Host Name** = RS PMS sunucusunun IP'si, **TCP Port** = RS PMS'te ayarlanan port.
2. Service Host'u yeniden başlat (veritabanı güncellemesi). Geri dönüş: eski IP/port geri yazılır.
3. *RVC Parameters → Interfaces* bağlantı numarası ve *Tender/Media → Interface Options* değişmez (bugünkü ayar kullanılır).
## 4.2 Akış 1 — Oda hesabına yazma

```
Garson: "Oda hesabı" → oda no (ve/veya ad) girer
   │
   ▼
Simphony ──(1) Sorgu: oda 214?──────────────► RS PMS
          ◄─(2) Cevap: Ahmet Yılmaz, giriş 12.05,
                çıkış 15.05, harcama izni var ───┘
   │ Garson misafiri seçer, hesabı kapatır
   ▼
Simphony ──(3) Kayıt: RVC 3, çek 1045, 1.250 TL
                (yiyecek 900, içecek 250, servis 100) ─► RS PMS
                                                         │ doğrula → pms_pos_postings
                                                         │ eşle   → pms_postings (her alt toplam ayrı işlem kodu)
          ◄─(4) Cevap: Kabul / Ret (neden) ──────────────┘
```

### Doğrulama kuralları (sırayla)
1. Mesaj daha önce işlendi mi? (`idempotency_key = rvc + çek no + tutar + zaman`) → işlendiyse ilk cevabı tekrar gönder.
2. Oda var mı, içeride (in_house) rezervasyon var mı?
3. Rezervasyonda `room_charges_allowed = true` mu?
4. Kredi limiti aşılıyor mu? (`credit_limit`, yönlendirme kuralı limiti)
5. İş günü açık mı?
6. Paylaşan rezervasyon varsa: hangi misafire yazılacağı Simphony'den gelen seçime göre.

### Eşleme
- Simphony çekteki tutarı **alt toplamlara** (yiyecek, içecek, servis ücreti, vergi, bahşiş, indirim) bölerek gönderir.
- `pms_pos_revenue_mappings`: (outlet, alt toplam no) → işlem kodu. Örnek: RVC 3 / 1 → `2010 Restoran Yiyecek`.
- Yönlendirme kuralları (`pms_routing_rules`) uygulanır: "Şirket yalnız odayı öder" ise restoran harcaması misafirin penceresine gider.
- **Pansiyon hakkı:** Rezervasyonda HB/FB/AI paketi varsa ve outlet hakka uygunsa, hak tutarı kadar `allowance` kaydı düşülür.

### Ters kayıt
Simphony'de iptal edilen ya da düzeltilen çek, negatif tutarlı yeni kayıt olarak gelir. Orijinal kayıt bulunursa `reversal_of_id` ile bağlanır.

## 4.3 Akış 2 — Satışların aktarılması (bütün çekler)

Oda hesabına yazılanlar Akış 1 ile zaten gelir. Nakit, kredi kartı ve diğer ödemelerle kapanan **bütün satışlar** için Oracle'ın sunduğu yollar:

| Yöntem | Ne gelir | Ne zaman | Gerekenler | Değerlendirme |
|---|---|---|---|---|
| **A. Reporting & Analytics BI API** (REST) | Çek çek, ürün ürün (`getGuestChecks`), günlük toplamlar: ödeme tipi, vergi, indirim, servis, ürün, RVC (`get…DailyTotals`), tanımlar (RVC, ürün, ödeme tipi, vergi) | Gün içinde istenen sıklıkta (ör. 15 dk) ve gün sonunda | R&A'da BI API hesabı (kurum kodu, kullanıcı, istemci kimliği), sunucudan R&A adresine erişim | **Önerilen.** Oracle'ın bu iş için yaptığı yol; Simphony kasalarını etkilemez |
| B. Enhanced PMS arayüzü, ödeme tiplerini bağlayarak | Her ödeme anında: satış/indirim/servis/vergi kırılımı + fiş dökümü | Anlık | Nakit/kart ödeme tiplerinin arayüz bağlantısı açılır | Gerçek zamanlı ama **PMS'e bağımlılık yaratır**: arayüz cevap vermezse kasada nakit/kart kapanışı da takılabilir. Ancak Oracle'ın R&A erişimi yoksa |
| C. R&A zamanlanmış rapor (CSV/Excel) | Günlük satış, ödeme tipi, RVC özetleri | Günde bir | R&A'da zamanlanmış rapor + paylaşılan klasör/e-posta | A yoksa geçici yol |
| D. Simphony veritabanından okuma | Her şey | İstenen sıklıkta | Veritabanı kullanıcısı | Oracle desteklemez, sürüm yükseltmede bozulabilir — **kullanılmayacak** |

### Önerilen akış (A)
```
Simphony 18 ──► R&A (Reporting & Analytics) ──(BI API, REST)──► RS PMS: pms:simphony-sync (zamanlanmış görev)
                                                                 ├─ tanımlar: RVC, ödeme tipi, vergi, indirim, servis, ürün grupları
                                                                 ├─ 15 dk'da bir: kapanan çekler (getGuestChecks) → pms_pos_checks / satırlar
                                                                 └─ gün sonu: günlük toplamlar → pms_pos_daily_sales (+ satırlar)
```
- **Mutabakat (gece kapanışında):** R&A'daki "oda hesabı" ödeme toplamı = o gün Akış 1 ile folyolara yazılan toplam. Fark varsa uyarı ve çek listesi.
- **Logo:** oda hesabı **dışındaki** ödemelerle kapanan satışlar outlet ciro fişi olarak gider (oda hesabındakiler folyodan gider; çift kayıt olmaz).
- **Raporlar:** outlet × gün × ödeme tipi cirosu, ürün grubu satışları, kişi başı ortalama — RS PMS'te ve RS ONE'da aynı veriden.

> **Karar gerekli:** Simphony cirosu bugün Logo'ya başka bir yoldan gidiyorsa Akış 2'nin Logo kısmı kapatılır, yalnız rapor ve mutabakat için kullanılır.

## 4.4 Outlet tanımları

- Outlet'ler `pms_outlets`'te tutulur; `simphony_rvc_number` ile eşleşir. Ekran: **Ayarlar → Outlet'ler** (açma, düzenleme, kopyalama,
  silme/pasife alma, sıralama; outlet'e bağlı departman kodlarının seçimi; varsayılan kod).
- Rocks Hotel outlet'leri — Simphony EMC *Revenue Centers* listesinden (RVC no = listedeki "#"; "RVC ID" sütunu 41, 42 … Simphony'nin iç numarasıdır):

  | RVC | Simphony adı | RS PMS | Departman kodları |
  |---|---|---|---|
  | 1 | LA HABANERA | HABE | 395–399, 544–546; banket: 440–442, 500–502, 610–612, 620–622 |
  | 2 | L HIRONDELLE | HIRO | 385–388, 450–452, 543 |
  | 3 | CAFE ROCKS | CAFE | 320–328 |
  | 4 | POOL BAR | POOL | 330–338 |
  | 5 | MINIBAR | MINI | 350–358 |
  | 6 | ROOM SERVICE | RSVC | 340–348 |
  | 7 | BANQUET | BANQ | 370–378 |
  | 8 | SPA CENTER | SPA | 513–515, 526–528, 547–549 |
  | 9 | LAUNDRY | LAUN | 510–512 |
  | 10 | CASINO | CASI | 670–692 |
  | 11 | KUAFOR | KUAF | 640–642 |
  | 12 | CATCH SEAFOOD | CTCH | 650–661 |
  | 13 | ONYX | ONYX | 480–488 |
  | 14 | BYBLOS | BYBL | 700–722 |
  | 15 | ROCKS PATISSERIE | PATI | 820–832 |
  | 16 | SIRALI ET | SIRA | 490–495 |
  | 17 | LYRA | LYRA | 810–818, 117–119 |
  | — | (Simphony'de yok) | DRAG Dragon Rest | 410–411 — elle kayıt |

  > Doğrulanacak: PMS mesajındaki "Revenue Center Number" Oracle belgesine göre RVC numarasıdır ("#"). IFC8 ayarındaki RVC → departman
  > kodu eşlemesiyle teyit edilecek; farklıysa outlet ekranından düzeltilir.
- İlk kurulumda Simphony'deki RVC listesi içe aktarılır, sonra PMS'te işlem kodlarına eşlenir.
- RS ONE'daki SPA ve restoran departmanları da outlet olarak tanımlanır (`source_system = rsone`), böylece tüm harcamalar aynı raporda görünür.

## 4.5 Teknik

- Dinleyici: `php artisan pms:simphony-listen` (Supervisor altında sürekli çalışır). Standart ve Enhanced protokolü konuşur; ping'e
  cevap verir; aynı sıra numarası + `R` gelirse son cevabı tekrarlar. Her mesaj önce `pms_pos_messages`'a ham olarak yazılır, sonra işlenir.
- Satış aktarımı: `php artisan pms:simphony-sync` (zamanlanmış görev; BI API).
- Cevap süresi hedefi: < 1 saniye (Simphony'nin zaman aşımı içinde). İşlem tek veritabanı işlemiyle yapılır, Redis kilidi folyo bazında.
- Bağlantı canlılık mesajları (heartbeat) `last_heartbeat_at`'i günceller; 5 dakika gelmezse yönetici ekranında uyarı.
- İzin verilen IP listesi (`allowed_ips`) dışından gelen bağlantı reddedilir.

## 4.6 Otelden istenenler (Simphony)

| # | Ne | Nereden | Ne için |
|---|---|---|---|
| 1 | EMC *Setup → Interfaces* → PMS arayüzü kaydının **General** ve **Options** sekmelerinin ekran görüntüsü | Simphony EMC | Standart mı Enhanced mı; port, zaman aşımı, 2/9 haneli kasa no, 3/5 haneli RVC, 8 haneli çek no, prorate seçenekleri |
| 2 | EMC *RVC Parameters → Interfaces* ve oda hesabı ödeme tipinin (*Tender/Media → Options → Interface Options*) ekran görüntüsü | Simphony EMC | Hangi RVC'ler ve ödeme tipleri PMS'e bağlı |
| 3 | ~~RVC listesi~~ ✓ alındı | EMC *Revenue Centers* | Outlet'lere Simphony RVC no girildi |
| 4 | Satış toplamı (Sales Itemizer) 1–16 ve vergi (Tax) 1–8 tanımları | EMC *Descriptors → Sales Itemizers*, *Tax Rates* | Yiyecek/içecek/diğer → departman kodu eşlemesi (ör. Cafe Rocks itemizer 1 → 320) |
| 5 | IFC8'in Simphony tarafı ayarı (sürücü, port) | IFC8 yapılandırması | Bugünkü port ve protokolü teyit |
| 6 | Reporting & Analytics erişimi var mı? Varsa BI API hesabı (kurum kodu, API kullanıcısı) | Simphony yetkili servisi | Satış aktarımı (Akış 2) |

Kaynaklar: Oracle Help Center — Simphony PMS Interface Reference 18.2 (F12084), Simphony Enhanced PMS Interface Specifications (F16307),
Reporting and Analytics Business Intelligence API Guide 20.1.
