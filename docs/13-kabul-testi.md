# 13. Ekran ve klavye kabul testi

PHPUnit testleri iş kurallarını denetler; bu bölüm ekranların **farklı genişliklerde** ve **yalnız klavyeyle** kullanılabildiğini denetler.
Betikler `tests/browser/` altında; gerçek tarayıcıda (Playwright / Chromium) çalışır, PMS'in çalışan bir kopyasına bağlanır.

```bash
PMS_URL=http://127.0.0.1:8000 PMS_USER=KULLANICI PMS_PASS=SIFRE node tests/browser/sweep.js
```
```bash
PMS_URL=http://127.0.0.1:8000 PMS_USER=KULLANICI PMS_PASS=SIFRE node tests/browser/keys.js
```

Kullanıcı yönetici rolünde olmalı (bütün ekranlar). Giriş sınırı dakikada 20 olduğundan iki betik arasında bir dakika beklenir.

## Genişlik taraması (`sweep.js`)

30 ekran × 3 genişlik (1280×720, 1366×768, 1920×1080): sayfa yatay taşması, kendi kutusuna sığmayan düğme, JavaScript hatası,
200 dışı cevap. Sorun yoksa yalnız `sweep done` yazar.

| Tarih | Sonuç |
|---|---|
| 27.11.2026 | İlk çalıştırmada 5 ekranda yan düğme etiketi sığmıyordu (Conf. Letter, New allotment, Room History, Assign room); yan düğmeler sıkılaştırıldı, ikinci çalıştırmada sorun yok. Sayfa taşması ve JS hatası yok. |

1093×614 (1366 px dizüstü, Windows ekran ölçeği %125) ayrıca tarandı: sorun yok. Telefon genişliği (390 px) ana sayfa için denendi; tek sütun, yatay taşma yok. Diğer ekranlar masaüstü / dizüstü içindir.

## Klavye akışı (`keys.js`)

Fareye dokunmadan: giriş (Tab, Enter) → Ctrl+U navigatör → Alt+2 gelecekler → ↓ Enter rezervasyon kartı → Ctrl+5 fiyat sekmesi →
Esc → Ctrl+L oda planı → Alt+T tipe göre grupla → Ctrl+P profiller → Alt+N tip penceresi, Enter → Esc → Ctrl+H House Status →
F1 kısayollar → Ctrl+B içeridekiler → ↓ Alt+B folyo → Alt+I fatura penceresi → Esc yalnız pencereyi kapatır.

| Tarih | Sonuç |
|---|---|
| 27.11.2026 | 17 adımın 17'si geçti, sayfa hatası yok. |

## Elle bakılacaklar

- Windows'ta Alt+Shift dil değiştirir; navigatörün yeni görünümlerine (Opsiyon, İptal, No-show, Web) bu yüzden kısayol verilmedi.
- Otel bilgisayarlarında ekran ölçeği %125 ise 1366 px dizüstü fiilen ~1093 px'tir; tarandı, taşma yok. Navigatörde gerekirse Kolonlar (Alt+K) ile az kullanılan kolonlar kapatılır.
