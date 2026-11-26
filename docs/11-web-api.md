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

## 11.5 Sırada (v1.1)

| Uç | Ne yapacak | Not |
|---|---|---|
| `POST /api/v1/reservations` | Web'den rezervasyon: misafir bilgisi, oda tipi, fiyat kodu, tarihler; PMS onay numarası döner | Ödeme (sanal POS) bilgisi otelin kararına göre; fiyat PMS'te yeniden hesaplanır, web'in gönderdiği tutara güvenilmez |
| `GET /api/v1/reservations/{onay no}` | Rezervasyon durumu | Misafirin soyadıyla birlikte sorgulanır |
| `POST /api/v1/reservations/{onay no}/cancel` | İptal | İptal kuralları fiyat koduna göre |
| `GET /api/v1/calendar` | Ay takvimi: gün gün en düşük fiyat ve müsaitlik | Web sitesinin takvim görünümü için |

Web rezervasyonları PMS'te **kaynak kodu** (ör. `WEB`) ve **pazar kodu** ile işaretlenir; Fidelio'daki "Acceptable Reservations"
gibi onay bekleyen listeye düşmesi isteniyorsa ayar olarak eklenir.
