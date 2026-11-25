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

IFC8, Oracle'ın Opera/Suite8'e özel ara katmanıdır; RS PMS'e bağlanamaz. Bu yüzden RS PMS, Simphony'nin bugün
konuştuğu tarafı (Fidelio + IFC8) **taklit eden** bir TCP dinleyici sunar ve Simphony'deki PMS arayüzü ayarı RS PMS'i gösterecek şekilde değiştirilir.

| Seçenek | Açıklama | Değerlendirme |
|---|---|---|
| **A. Simphony PMS arayüzü (TCP), sorgu + kayıt tek bağlantı** | RS PMS dinleyici açar; Simphony'nin "üçüncü parti PMS" arayüzü RS PMS'e yönlendirilir | **Önerilen.** IFC8 devreden çıkar, ek lisans yok |
| B. FIAS protokolü | RS PMS, FIAS mesajlarını (IFC8'in Simphony tarafına konuştuğu dil) konuşur | Simphony 18'in bu bağlantıyı IFC8 olmadan kabul edip etmediği doğrulanmalı |
| C. REST / OHIP | Oracle'ın bulut entegrasyon platformu | Simphony 18 için uygun değil |

### Paralel çalışma döneminde
Fidelio canlıyken Simphony bağlantısı Fidelio'da kalır. RS PMS'te Simphony arayüzü **test modunda** çalışır:
- Simphony'nin test istasyonu (ya da test RVC'si) RS PMS'e bağlanır.
- Geçiş gecesi yalnız Simphony'deki arayüz adresi değiştirilir; geri dönüş gerekirse eski adrese alınır.

> **Doğrulanacak (kodlamadan önce):**
> 1. Simphony 18'deki mevcut PMS arayüzü ayarlarının ekran görüntüleri (EMC → Property → Interfaces; RVC parametreleri; "Oda hesabı" tender ayarı).
> 2. IFC8'in hangi protokolle ayarlandığı (FIAS sürümü), IFC8 ayar dosyası/ekranı.
> 3. Simphony yetkili servisinden: Simphony 18'in üçüncü parti PMS'e doğrudan bağlanabildiği arayüz tipi ve mesaj belgesi
>    (Oracle *Simphony PMS Interface* / *IFC8 FIAS Specification*).
> Mesaj biçimleri bu belgelerden birebir alınacak; tahminle kodlanmayacak.

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

## 4.3 Akış 2 — Günlük satış özeti

- Kaynak: Simphony raporlama servisi (*Reporting & Analytics / BI API*). Erişim yoksa geçici olarak gün sonu CSV dışa aktarımı kullanılır.
- Her outlet ve gün için: çek sayısı, kişi sayısı, net satış, vergi, servis, indirim, ödeme tipine göre kırılım.
- `pms_pos_daily_sales` + `pms_pos_daily_sales_lines`'a yazılır.
- **Mutabakat:** Günlük özetteki "oda hesabı" ödeme toplamı = o gün PMS'e gelen Simphony kayıtlarının toplamı olmalı.
  Fark varsa gece kapanışında *uyarı* olarak çıkar.
- Logo'ya: oda hesabı **dışındaki** ödemelerle kapanan satışlar outlet ciro fişi olarak gider
  (oda hesabına yazılanlar zaten folyo üzerinden günlük gelir fişine giriyor; çift kayıt olmaz).

> **Karar gerekli:** Simphony cirosu bugün Logo'ya başka bir yoldan gidiyorsa Akış 2'nin Logo kısmı kapatılır, yalnız rapor ve mutabakat için kullanılır.

## 4.4 Outlet tanımları

- Outlet'ler `pms_outlets`'te tutulur; `simphony_rvc_number` ile eşleşir.
- İlk kurulumda Simphony'deki RVC listesi içe aktarılır, sonra PMS'te işlem kodlarına eşlenir.
- RS ONE'daki SPA ve restoran departmanları da outlet olarak tanımlanır (`source_system = rsone`), böylece tüm harcamalar aynı raporda görünür.

## 4.5 Teknik

- Dinleyici: `php artisan pms:simphony-listen` (Supervisor altında sürekli çalışır). Her mesaj önce `pms_pos_messages`'a ham olarak yazılır, sonra işlenir.
- Cevap süresi hedefi: < 1 saniye (Simphony'nin zaman aşımı içinde). İşlem tek veritabanı işlemiyle yapılır, Redis kilidi folyo bazında.
- Bağlantı canlılık mesajları (heartbeat) `last_heartbeat_at`'i günceller; 5 dakika gelmezse yönetici ekranında uyarı.
- İzin verilen IP listesi (`allowed_ips`) dışından gelen bağlantı reddedilir.
