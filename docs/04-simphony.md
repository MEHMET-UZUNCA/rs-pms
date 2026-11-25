# 4. Simphony entegrasyonu

Restoran, bar, oda servisi ve minibar satışları **Oracle Simphony**'de yapılır. Outlet'ler (Simphony'deki *Revenue Center*, RVC)
PMS'e buradan gelir. İki ayrı akış vardır:

| Akış | Yön | Ne zaman | Amaç |
|---|---|---|---|
| **1. Oda hesabına yazma** | Simphony → PMS (anlık) | Garson "oda hesabına" ödeme tipini seçtiğinde | Misafirin harcaması folyosuna düşer |
| **2. Günlük satış özeti** | Simphony → PMS (günde bir) | Simphony gün kapanışından sonra | Outlet cirosunun tamamı (nakit, kart dahil) Logo'ya gider, gece kapanışı raporunda görünür |

## 4.1 Bağlantı yöntemi

Simphony üçüncü parti PMS'lerle **TCP tabanlı PMS arayüzü** üzerinden konuşur. Simphony'de her RVC bir PMS arayüzüne bağlanır;
"Oda hesabı" ödeme tipi (tender) bu arayüze yönlendirilir.

| Seçenek | Açıklama | Değerlendirme |
|---|---|---|
| **A. Simphony PMS arayüzü (TCP)** | RS PMS bir TCP dinleyici açar; Simphony sorgu ve kayıt mesajlarını doğrudan gönderir | **Önerilen.** Ara yazılım ve ek lisans gerektirmez |
| B. FIAS (IFC8 üzerinden) | Oracle'ın IFC8 ara katmanı; Fidelio/Opera ile bugün muhtemelen böyle bağlı | IFC8 lisansı ve sunucusu gerekir; geçişte geçici çözüm olabilir |
| C. REST / OHIP | Oracle'ın yeni bulut entegrasyon platformu | Simphony sürümüne ve lisansa bağlı |

> **Doğrulanacak:** Mesaj biçimleri Oracle'ın *Simphony Enhanced PMS Interface* ve *IFC8 FIAS* belgelerinden birebir alınacak.
> Otelin Simphony sürümü, bugün Fidelio'ya nasıl bağlandığı (IFC8 var mı) ve Simphony yetkili servisinin desteği netleşmeden kodlamaya başlanmayacak.

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
