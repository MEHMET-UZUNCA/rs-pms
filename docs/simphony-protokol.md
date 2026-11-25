# Simphony ↔ PMS mesaj protokolü (MF_ENH, Rocks Hotel)

Kaynak: **gerçek trafik** — IFC8 logu `M87POS_SPH_IFC1` (26–27.06.2025, 2.100 mesaj çifti), Oracle *Simphony Enhanced PMS Interface
Specifications* (F16307) ve EMC ayarları (docs/04-simphony.md §4.1). RS PMS dinleyicisi bu biçimi birebir konuşur; Simphony'de
hiçbir şey değişmez. Örneklerdeki misafir adları, oda ve rezervasyon numaraları **değiştirilmiştir**.

## 1. Çerçeve

```
SOH <kasa no 9 hane> <arayüz adı 16 hane, "MF_ENH          "> STX FS <sıra 2 hane><R ya da boşluk> <mesaj adı> FS <alan> FS <alan> … ETX <checksum 4 hex> EOT
```
- SOH=01h, STX=02h, ETX=03h, EOT=04h, FS=1Ch. Kodlama ASCII (Türkçe karakterler `?` olarak gelir: "Kapat?ld?").
- Kasa no 9 hane (EMC seçenek 5 açık): `000000006`. Cevapta aynı kaynak alanı geri gönderilir.
- Sıra: her kasa 01–99 artırır; cevap aynı sıra numarasıyla döner. `R` = Simphony'nin tekrar gönderimi → **son cevap aynen tekrar edilir** (logda hiç görülmedi ama Oracle belgesi şart koşuyor).
- Checksum: SOH'tan sonraki ilk karakterden ETX dahil bütün baytların 16 bitlik toplamı, 4 büyük harf hex (`0x1041` → `1041`). Logdaki cevaplarla doğrulandı.
- Ping kapalı (seçenek 9); bağlantı sürekli açık kalır, tek bağlantı (Service Host CAPS-ROCKS).

## 2. Mesaj türleri (logdaki sayılarla)

| Simphony → PMS | Adet | PMS → Simphony | Ne |
|---|---|---|---|
| `IFC_CHG_PST` (oda/misafir ile) | 343 + 361 | `IFC_GST_SEL` / `IFC_CHG_PST` | Oda hesabı: önce misafir seçtirme, sonra kayıt |
| `IFC_CHG_PST` (boş misafir) | 85 | `IFC_CHG_PST` | Nakit/kart vb. ödeme ("Non Front-Office") → sanal oda |
| `FO_FI_FINAL_TENDER` | 389 | `FO_87_DONE` | Çekin metin dökümü (fiş) |
| `FO_FI_RES_QUERY` | 489 | `FO_87_RES_LIST` | Oda sorgusu (kasadaki "PMS Results" penceresi) |
| `FO_FI_RES_DETAIL` | 347 | `FO_87_RES_DETAIL` | Seçilen rezervasyonun detayı |
| `FO_FI_GST_DETAIL` | 12 | `FO_87_GST_DETAIL` | Misafir (profil) detayı |
| `FO_FI_GST_MESSAGE` | 8 | `FO_87_IFC_ERROR` ("No message found") | Misafir mesajı |
| `FO_FI_GST_LOCATOR` | 2 | `FO_87_IFC_ERROR` ("Message/Locator not found") | Misafir yer bilgisi (locator) |
| `FO_FI_VERSION` | 51 | `FO_87_VERSION` | Kasa açılışında sürüm el sıkışması |
| — | — | `FO_87_IFC_ERROR` | Genel hata cevabı: `FO_87_IFC_ERROR FS <metin>` |

## 3. Oda sorgusu

```
→ FO_FI_RES_QUERY FS <oda no ya da ad>
← FO_87_RES_LIST FS <başlık> FS <adet> { FS <rezervasyon no, 30 hane sola dayalı> FS <satır> FS " L" }…
   başlık: "Room   Name                           Arrival/Departure     Payment           "
   satır : "1327   SOYAD, AD Bayan                26-JUN-25 / 28-JUN-25             "   (oda 7, ad 31, tarih 22, ödeme 18 hane)
→ FO_FI_RES_DETAIL FS <rezervasyon no, 30 hane>
← FO_87_RES_DETAIL FS 10 FS "Name           :    SOYAD, AD Bayan" … (10 satır, her biri "Etiket(15) :    değer", 74 haneye boşlukla)
   Etiketler: Name, Arrival (dd.mm.yyyy), Departure, Payment Method, Remarks 1, Remarks 2, Adults / Kids ("2 / 0"), Group, Ratecode, Messages
→ FO_FI_GST_DETAIL FS <rezervasyon no>
← FO_87_GST_DETAIL FS 10 FS Name, Address 1, Address 2, ZIP / City, State, Country, Phone, E-Mail, Language, VIP
```

## 4. Oda hesabı (ödeme tipi 601 "Oda Hesabı")

```
1) → IFC_CHG_PST FS <oda no (kasiyerin girdiği)> FS 601
   ← IFC_GST_SEL FS <aynı giriş> FS IFC_CHG_PST FS <adet> { FS "<oda>/<SOYAD>" 40 haneye boşlukla }…
      Bulunamazsa: ← IFC_CHG_PST FS N FS D FS "Guest not found" (30 hane)
2) → IFC_CHG_PST FS <giriş> FS <seçilen satır metni> FS S<sıra 1..n> FS 601 FS <tutar> FS 16 FS <satış 1..16>
        FS <indirim adedi> FS <indirimler> FS <servis adedi> FS <servisler> FS 63 FS <vergi 1..63>
        FS <çek no> FS <garson no> FS <servis dönemi> FS <RVC no> FS <kişi> FS <YYYYMMDD> FS <HH:MM:SS> FS <önceki ödeme>
   ← IFC_CHG_PST FS N FS P FS "<oda>/<SOYAD>" (30 hane) FS <tutar> FS <rezervasyon no>
      Ret: ← IFC_CHG_PST FS N FS D FS <sebep, 30 hane>
3) → FO_FI_FINAL_TENDER FS <YYYYMMDD> FS <HH:MM:SS> FS <RVC no> FS <çek no> FS <fiş metni, satırlar LF ile>
   ← FO_87_DONE FS Check detail received
```
- İlk alan `N` = misafir mesajı yok (SUPPRESSGSTMSG=1). Durum `P` kaydedildi, `D` reddedildi (ör. "Guest not found"), `E` hata.
- Kabul cevabındaki metin fişe basılır ("Oda Hesabi 180.00TL / 1327/SOYAD").
- Seçim listesi: aynı odada birden çok misafir varsa her biri ayrı satır (S1, S2, S3 …). Logda 343 kaydın 258'i S1.
- Tutar alanları `-` işaretli olabilir (iptal/düzeltme; REVERSEGST=1 → kişi sayısı da eksi). Otomatik ters kayıt kapalı.

## 5. Oda hesabı dışındaki ödemeler (Non Front-Office)

```
→ IFC_CHG_PST FS " " FS " " FS <ödeme tipi> FS <tutar> FS 16 FS <satışlar> … (oda hesabıyla aynı gövde)
← IFC_CHG_PST FS N FS P FS "<sanal oda>/<ad>" FS <tutar> FS <sanal odanın rezervasyon no>
```

| Simphony ödeme tipi | Fidelio'da yazıldığı yer (logdan) |
|---|---|
| 601 | Misafirin folyosu (oda hesabı) |
| 131 | 9901 POS CASH |
| 231 | 9902 POS (kredi kartı) |
| 402 | 9902 POS |
| 404 | 9903 ROOM SERVICE CASH |
| 401 | 9401 PERSONEL CARI |

İkram (ödenmez) çekler PMS'e gönderilmez; Simphony'de kalır (otelden alınan bilgi).

## 6. Tutar alanları

- Satış kalemi (sales itemizer) 1–16: kullanılanlar 1, 2, 3, 4, 6, 7, 8. Hangi kalemin hangi departman koduna gittiği IFC8 ayarında (`SPH.XML`).
- İndirim kalemleri satış kalemleriyle aynı sırada (FULL_DSVC=1). Servis ücreti logda hiç yok.
- Vergi: Simphony **VAT** modunda; vergi kalemi 1–63 **vergi dahil matrah** taşır (Oracle belgesi). Logda 1 ve 2 kullanılıyor.
- Sondaki 8 alan: çek no, çek garsonu, servis dönemi (POSTING_ADD_INFO=0), RVC no (EMC "#", ör. 15 = Rocks Patisserie), kişi, tarih, saat, önceki ödeme.

## 7. Açık noktalar

- `SPH.XML` (IFC8: `C:\FIDELIO\IFC8\SPH\SPH.XML`): satış kalemi × outlet → departman kodu ve ödeme tipi → sanal oda eşlemesi.
- Simphony ödeme tipi listesi (131, 231, 401, 402, 404, 601 adları), satış kalemi ve vergi kalemi adları.
- `FO_FI_GST_MESSAGE` / `FO_FI_GST_LOCATOR` başarılı cevap biçimi (logda yalnız "bulunamadı" görüldü) — ilk sürümde aynı hata cevabı verilir.
