# Fidelio Suite8 — kaynak view'lar ve eşleme

## Kaynak

| Kaynak | İçerik |
|---|---|
| **PROISV view'ları** (otelden alınan `Help.zip`) | Protel'in Suite8 veritabanı (`V8`) üzerinde `PROISV` şemasında hazırladığı 12 view. Belge tarihi 12.05.2010, Oracle 10.2. |
| Oracle *Suite8 Demand 360 BI Interface* | Suite8'in kendi dışa aktarım view'ları: `V8_EXP_TCDEMAND_PROP_BASE`, `V8_EXP_TCDEMAND_RES_BASE`, `V8_EXP_TCDEMAND_BLOCK_BASE`. Suite8 rezervasyon tablosunun kolon öneki `YRES_`, sistem tarihi fonksiyonu `v8_sys_fideliodate`. |

### Canlı veritabanındaki view'lar (otelden alınan liste)

Suite8'in ana şeması **`V8LIVE`**. `PROISV`'deki view'lar `V8LIVE`'da da var; ek olarak:

| View | Şema | Durum |
|---|---|---|
| PRO_ISV_POSTINGS | PROISV, V8LIVE | **Folyo kayıtları** — kolonlar bekleniyor |
| PRO_EXCHANGERATES | V8LIVE | Döviz kurları — kolonlar bekleniyor |
| PRO_ISV_ZCUR | V8LIVE | Para birimleri (muhtemelen Suite8 `ZCUR` tablosu) — kolonlar bekleniyor |
| PRO_DAILYROOMRATETOTAL | V8LIVE | Günlük oda fiyatı toplamı — kolonlar bekleniyor |
| PRO_DAILY_NOF_ROOM_BED_YCAT | V8LIVE | Oda kategorisine göre günlük oda/yatak — kolonlar bekleniyor |
| PRO_OOOROOMBEDS_YCAT | V8LIVE | Oda kategorisine göre OOO — kolonlar bekleniyor |
| PRO_YRMS_BEDS_COUNT_BY_YCAT | V8LIVE | Oda kategorisine göre yatak sayısı (Suite8 `YRMS` oda tablosu) — kolonlar bekleniyor |
| PRO_YEARVIEW_NEW | V8LIVE | Yıllık görünüm — kolonlar bekleniyor |

Fatura, cari, depozito ve tanımlar (oda, fiyat kodu, işlem kodu) için view yok; bunlar `V8LIVE` tablolarından okunacak.

## Suite8 kod değerleri (view açıklamalarından)

| Alan | Değerler |
|---|---|
| Rezervasyon durumu (`RESERVATIONSTATUS`) | 0 Tentative · 1 Definite · 3 İptal / müsaitliği etkilemeyen |
| Hesaplanan durum (`CALCULATEDSTATUS`) | 0 beklenen (ileri) · 1 bugün gelecek · 2 bugün giriş yaptı · 3 içeride · 4 bugün çıkacak · 5 bugün çıktı · 6 geçmiş · 7 günübirlik beklenen · 8 günübirlik içeride · 9 günübirlik geçmiş · 10 bekleme listesi · 11 teklif · 12 iptal · 13 no-show |
| Profil tipi (`PROFILE_TYPE`) | 1 Company · 2 Individual |
| Profil iç kategorisi (`PROFILE_INTERNALCATEGORY`) | 1 Company · 2 Travel Agent · 3 Source · 4 Individual · 5 Vendors · 6 Meeting Planner |
| Profil durumu (`ACTIVE_STATUS`) | 1 Aktif · 0 Pasif · 3 Sonlandırılmış (personel) · 4 Birleştirilmiş |
| Cinsiyet (`SEX`) | 1 Erkek · 2 Kadın · 3 Bilinmiyor |
| Ücretsiz/ev kullanımı (`CH`) | 1 Complimentary · 2 House Use |
| Çocuk kategorileri | `FREECHILD`, `CA1`, `CA2`, `CA3`, `OTHERCA` |
| Kart rolü (`CARDROLE`) | 1 A/R No · 2 IATA No · 3 Vergi No · 4 Sadakat/uçuş kartı · 5 Kurumsal ID · … |
| Promosyon kural tipi | 1 Aksiyon (gün indirimi) · 2 Erken rezervasyon (yüzde) |

## Önemli tespit: Suite8 de gecelik satır kullanıyor

Rezervasyon view'ları **rezervasyon × gün** satırı döner (`DETAIL_ID`, `DETAIL_DATE`, `CURRENT_DETAIL_ID`, `LAST_DETAIL_ID`).
Her günün kendi oda tipi, oda numarası, fiyat kodu, tutarı ve kişi sayısı var. Bu, RS PMS'teki `pms_reservation_nights` tasarımıyla birebir örtüşüyor.

Ayrıntılı eşleme: [eslesme.md](eslesme.md)
