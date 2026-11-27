# 11. Web API (v1)

Otelin web sitesi **ayrı bir projedir**; oda tiplerini, online fiyatları ve müsaitliği bu API'den okur. Web sitesi PMS veritabanına
doğrudan bağlanmaz: veritabanı iç ağda kalır, fiyat kuralları tek yerde (PMS) çalışır, web sitesi yalnız izin verilen bilgiyi görür.

- Adres: `https://<pms-sunucusu>/api/v1/...`
- Biçim: JSON (UTF-8). Tarihler `YYYY-MM-DD`, tutarlar ondalık sayı (nokta ile).
- Sürüm: adresteki `v1`. Geriye uyumsuz değişiklik yeni sürümle (`v2`) gelir; v1'e alan eklenebilir, alan çıkarılmaz.
- Hız sınırı: istemci başına dakikada 120 istek (aşılırsa `429`).

## 11.1 Kimlik doğrulama

Her istemcinin (web sitesi, ileride kanal yöneticisi) kendi anahtarı vardır. Anahtar sunucuda açılır, **yalnız bir kez gösterilir**;
veritabanında yalnız özeti (SHA-256) saklanır.

```bash
php artisan pms:api-client "Web sitesi"
```
```bash
php artisan pms:api-client --list
```
```bash
php artisan pms:api-client --revoke=1
```

Her istekte başlık:

```
Authorization: Bearer rspms_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

Anahtar web sitesinin **sunucu tarafında** (ör. `.env`) tutulur; tarayıcıya ya da JavaScript'e konmaz.
Anahtar yoksa ya da kapatılmışsa cevap `401 {"error": "unauthorized"}`.

## 11.2 Neler döner

- **Oda tipleri:** PMS'te "online satılabilir" işaretli, sanal olmayan oda tipleri.
- **Fiyat kodları:** Ayarlar → Fiyat kodları'nda **"Online / Web (OTA)"** işaretli ve aktif kodlar. Online'a ayrı fiyat
  (ör. `27WEB`) tanımlanır ve yalnız o işaretlenir; acenta / şirket / complimentary kodları web'de görünmez.
- **Fiyat:** PMS'in rezervasyonda kullandığı hesapla aynıdır (dönem, oda tipi, kişi sayısı, Default, hafta sonu fiyatı, çocuk yaş kategorisi).
- **Kurallar:** satış dönemi (Startsell / Endsell bugüne göre), en az / en çok gece, erken rezervasyon günleri, oda tipinin kişi kapasitesi.

## 11.3 Uçlar

### GET /api/v1/room-types

```json
{
  "data": [
    {
      "code": "YMF", "name": "Deluxe,Dağ,French", "description": null,
      "max_adults": 2, "max_children": 0, "max_occupancy": 2, "rooms": 35,
      "features": [{ "code": "MV", "name": "Mountain View" }, { "code": "FB", "name": "French Bed" }]
    }
  ]
}
```

### GET /api/v1/rate-codes

```json
{
  "data": [
    {
      "code": "27IND", "name": "2027 IND", "board": "HBD", "board_name": "Half Board Dinner", "currency": "EUR",
      "sell_from": "2026-01-01", "sell_to": "2027-12-31", "min_nights": 2, "max_nights": null,
      "min_advance_days": null, "max_advance_days": null
    }
  ]
}
```

### GET /api/v1/availability

| Parametre | Zorunlu | Açıklama |
|---|---|---|
| `arrival` | evet | Giriş tarihi (`2027-03-10`); bugünden önce olamaz |
| `departure` | evet | Çıkış tarihi; girişten sonra, en çok 30 gece |
| `adults` | evet | Yetişkin sayısı (1–8) |
| `children` | hayır | Çocuk yaşları, virgülle (`5,9`); yaş kategorisine (CA1, CA2) PMS çevirir |
| `room_type` | hayır | Yalnız bu oda tipi (`YMF`) |
| `rate_code` | hayır | Yalnız bu fiyat kodu |

Örnek: `GET /api/v1/availability?arrival=2027-03-10&departure=2027-03-13&adults=1&children=9`

```json
{
  "arrival": "2027-03-10", "departure": "2027-03-13", "nights": 3, "adults": 1, "children": [9],
  "data": [
    {
      "room_type": "YMF", "name": "Deluxe,Dağ,French", "available": 12,
      "rates": [
        {
          "rate_code": "27IND", "rate_name": "2027 IND", "board": "HBD", "currency": "EUR",
          "total": 2160.00,
          "nightly": [
            { "date": "2027-03-10", "amount": 720.00 },
            { "date": "2027-03-11", "amount": 720.00 },
            { "date": "2027-03-12", "amount": 720.00 }
          ],
          "total_base_currency": 119656.87, "base_currency": "TRY"
        }
      ]
    }
  ]
}
```

- `available`: konaklama gecelerinin en düşük müsait oda sayısı (kesin rezervasyonlar, kesin bloklar ve arızalı odalar düşülmüş).
  `0` ise oda tipi o tarihlerde dolu; web sitesi "dolu" gösterir.
- Bir fiyat kodu, gecelerden birinde fiyatı yoksa o oda tipi için listelenmez. Hiç fiyatı olmayan oda tipi listede yer almaz.
- `total_base_currency`: fiyat kodu döviz ise, PMS'teki günün **Ratecode Rate** kuruyla TL karşılığı (Ayarlar → Döviz kurları).
- `nightly.amount` pansiyon (kahvaltı / akşam yemeği) dahil gece fiyatıdır.

## 11.4 Hatalar

| Kod | Ne zaman | Gövde |
|---|---|---|
| 401 | Anahtar yok / yanlış / kapatılmış | `{"error": "unauthorized", "message": "..."}` |
| 422 | Parametre hatalı (tarih, kişi sayısı, 30 geceden uzun, geçmiş tarih) | `{"message": "...", "errors": {...}}` ya da `{"error": "validation", "message": "..."}` |
| 429 | Hız sınırı aşıldı | Laravel standart cevabı |

## 11.5 Rezervasyon (v1.1)

Ön koşul: **Ayarlar → Rezervasyon seçenekleri → Web sitesi rezervasyonları**'nda rezervasyon tipi, pazar ve kaynak kodu seçilir
(ör. GTD / IND / DIR ya da otelin web kodları). Seçili değilse uçlar `503 {"error": "not_configured"}` döner.
Yeni web rezervasyonunun durumu aynı ekrandan: kesin (`confirmed`) ya da opsiyon (`tentative`). Ön büro web'den gelenleri
**Navigatör → Web (7 gün)** görünümünde görür.

### POST /api/v1/reservations

```json
{
  "external_reference": "W-1001",
  "arrival": "2027-03-10", "departure": "2027-03-13",
  "adults": 2, "children": [9],
  "room_type": "YMF", "rate_code": "27IND",
  "guest": { "last_name": "Weber", "first_name": "Anna", "email": "anna@example.com", "phone": "+49 151 000 000", "nationality": "DE", "language": "de" },
  "eta": "18:30", "comments": "Sea view if possible"
}
```

Cevap `201`:

```json
{ "data": { "confirmation_number": "000123", "status": "confirmed", "external_reference": "W-1001", "arrival": "2027-03-10", "departure": "2027-03-13",
  "nights": 3, "room_type": "YMF", "rate_code": "27IND", "adults": 2, "children": 1, "currency": "EUR", "total": 1920.00, "guest": "Weber, Anna",
  "cancellation_number": null } }
```

- **Fiyat PMS'te hesaplanır**; web'in tutarı gönderilmez / kullanılmaz. `total` PMS'in kesin tutarıdır, web sitesi bunu gösterir.
- `external_reference` web sitesinin kendi rezervasyon numarasıdır; aynı numarayla tekrar gelirse yeni rezervasyon açılmaz, aynısı `200`
  ve `"duplicate": true` ile döner (bağlantı koparsa web sitesi güvenle yeniden dener).
- Aynı e-postayla kayıtlı bireysel profil varsa o kullanılır, yoksa yeni profil açılır.
- Kurallar: oda tipi online satılabilir, fiyat kodu "Online / Web" işaretli ve aktif, kişi sayısı oda kapasitesi içinde, geçmiş tarih ve 30 geceden uzun olamaz,
  oda tipinde müsait oda olmalı, her gece fiyatı bulunmalı.

### GET /api/v1/reservations/{onay no}?last_name=Weber

Rezervasyonun güncel durumu (aynı biçim). Soyad eşleşmezse ya da rezervasyon web'den gelmemişse `404`.

### POST /api/v1/reservations/{onay no}/cancel

Gövde: `{"last_name": "Weber"}`. Giriş gününden önce iptal edilir (iptal nedeni "Misafir iptal etti", iptal numarası verilir).
Giriş günü ve sonrasında `409 {"error": "too_late"}` — otel iptal eder.

### Hatalar (rezervasyon)

| Kod | Ne zaman |
|---|---|
| 409 `not_available` | Oda tipi o tarihlerde dolu |
| 409 `no_rate` | Gecelerden birinde fiyat yok |
| 409 `too_late` | İptal giriş günü ya da sonrasında |
| 422 | Alan hatası, bilinmeyen oda tipi / fiyat kodu, geçmiş tarih, kapasite |
| 503 `not_configured` | Web rezervasyon kodları ayarlanmamış |

Hız sınırı: rezervasyon açma ve iptal için dakikada 30 istek.

## 11.6 Sırada

| Uç | Ne yapacak | Not |
|---|---|---|
| `GET /api/v1/calendar` | Ay takvimi: gün gün en düşük fiyat ve müsaitlik | Web sitesinin takvim görünümü için |
| Ödeme | Sanal POS ön provizyon / ödeme bilgisi | Otelin kararına göre; kart bilgisi PMS'e gelmez, yalnız ödeme sağlayıcısının işlem numarası |
