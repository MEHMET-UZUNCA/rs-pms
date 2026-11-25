# 5. Logo aktarımı

Resmi muhasebe **Logo**'dadır. RS PMS her iş günü için muhasebe fişlerini üretir ve Logo'ya gönderir.
Kamra'nın ERPNext köprüsündeki model örnek alınmıştır: **iş günü başına tek fiş, kaynağı izlenebilir, iki kez gönderilmez.**

## 5.1 Gönderilen belgeler

| Parti tipi | İçerik | Logo'da |
|---|---|---|
| `daily_revenue` | Günün tüm gelirleri işlem kodu bazında (oda, paket elemanları, Simphony'den gelen oda harcamaları, ekstralar), vergi ayrı satır | Mahsup fişi: Misafir alacakları (B) / Gelir hesapları (A), KDV (A) |
| `collections` | Günün tahsilatları ödeme tipi ve döviz bazında (nakit, kart, havale) | Kasa / banka (B) / Misafir alacakları (A) |
| `ar_invoices` | Folyodan cariye aktarılan faturalar | Cari hesap (B) / Misafir alacakları (A) — Logo cari kodu ile |
| `ar_payments` | Cari tahsilatlar | Kasa/banka (B) / Cari (A) |
| `outlet_sales` | Simphony'de nakit/kart ile kapanan outlet satışları (isteğe bağlı) | Kasa/banka (B) / Outlet gelirleri (A) |
| (depozito) | Alınan avanslar `collections` içinde ayrı hesapta | Alınan sipariş avansları |

## 5.2 Hesap eşlemesi

`pms_logo_account_mappings`: her işlem kodu, vergi, cari hesap, outlet ve defter (guest / deposit / ar) için Logo hesap kodu,
masraf merkezi ve departman. Eşlemesi eksik kod varsa gece kapanışı **engeller** (fiş dengesiz gitmesin).

## 5.3 Kurallar

- Fiş **borç = alacak** olmadan gönderilmez.
- Aynı (tesis, iş günü, parti tipi) için aktif tek parti olur. Düzeltme gerekirse eski parti `cancelled`, yenisi `replaces_batch_id` ile.
- Gönderim kuyrukta (Redis); başarısızsa artan aralıklarla tekrar denenir, 5 denemeden sonra yönetici uyarısı.
- Her satır kaynağına bağlıdır (`source_type`, `source_id`): Logo'daki bir rakamdan PMS'teki kayda inilebilir.
- Kapanmış iş gününde düzeltme yapılmaz; düzeltme bugünün iş gününde ters kayıtla yapılır ve bugünün fişinde gider.

## 5.4 Bağlantı yöntemi — Logo Tiger

Otelde **Logo Tiger** kullanılıyor. Seçenekler (tercih sırasıyla):

| Yöntem | Açıklama | Not |
|---|---|---|
| **1. Logo REST servisi** | Tiger'ın REST katmanı üzerinden fiş ve cari kart gönderimi | Lisansta REST servisi olup olmadığı kontrol edilecek |
| 2. Logo Objects (LObjects) | Windows üzerinde çalışan COM nesneleri; PMS ile Logo arasında küçük bir aktarım servisi gerekir | REST yoksa |
| 3. XML içe aktarma | Tiger'ın mahsup fişi XML biçiminde dosya; muhasebe elle yükler | Geçici ya da yedek yöntem |

PMS tarafındaki tasarım üçünde de aynıdır (`pms_logo_export_*`); yalnız gönderici sınıf değişir.

> **Kalan sorular:**
> - Tiger sürümü ve REST servisi lisansı var mı?
> - Firma / dönem numarası, mahsup fişi tipi, şube/bölüm kodları
> - Faturayı PMS mi kesecek, Logo mu? (Tasarımda PMS keser, Logo'ya fiş gider.)
> - Cari kartlar Logo'da mı açılıyor, PMS'ten mi gönderilecek?
