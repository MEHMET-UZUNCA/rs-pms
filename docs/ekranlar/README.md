# Ekran taslakları

Fidelio Suite8 yerleşiminde hazırlanan ekran taslakları (örnek veri). Canlı, tıklanabilir hali: Design canvas "RS PMS Ekran Taslakları".

Adlandırma: `<ekran no>-<ekran adı>-<sıra no>-<durum>.png` — ekranlar numara sırasıyla, her ekranın durumları kendi içinde sıralı.

## Klasörler

| Klasör | İçerik |
|---|---|
| `goruntuler/` | Ekran görüntüleri (PNG, 2880×1800 — yüksek çözünürlük) |
| `videolar/` | Kullanım videoları (MP4, 1440×900) |
| `kaynak/` | Taslak kaynak dosyaları (`.dc.html`) ve yerel görüntüleyici (`support.js`) |

## 01 — Rezervasyon Navigatörü

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/01-rezervasyon-navigatoru-01-iceridekiler.png` | İlk açılış: içerideki rezervasyonlar (`Alt+1`) |
| `goruntuler/01-rezervasyon-navigatoru-02-bugun-gelecekler-secili.png` | Bugün gelecekler filtresi (`Alt+2`), seçili rezervasyonun alt paneli |
| `videolar/01-rezervasyon-navigatoru-filtreler-ve-secim.mp4` | Filtreler arasında geçiş ve satır seçimi |

## 02 — Rezervasyon Kartı (`Ctrl+1` … `Ctrl+0`)

| Dosya | Sekme |
|---|---|
| `goruntuler/02-rezervasyon-karti-01-rezervasyon.png` | `Ctrl+1` Rezervasyon |
| `goruntuler/02-rezervasyon-karti-02-envanter.png` | `Ctrl+2` Envanter |
| `goruntuler/02-rezervasyon-karti-03-profiller.png` | `Ctrl+3` Profiller |
| `goruntuler/02-rezervasyon-karti-04-paketler.png` | `Ctrl+4` Paketler |
| `goruntuler/02-rezervasyon-karti-05-gece-fiyatlari.png` | `Ctrl+5` Gece fiyatları |
| `goruntuler/02-rezervasyon-karti-06-ozellikler.png` | `Ctrl+6` Özellikler |
| `goruntuler/02-rezervasyon-karti-07-kredi-karti.png` | `Ctrl+7` Kredi kartı |
| `goruntuler/02-rezervasyon-karti-08-sabit-ucretler.png` | `Ctrl+8` Sabit ücretler |
| `goruntuler/02-rezervasyon-karti-09-notlar.png` | `Ctrl+9` Notlar |
| `goruntuler/02-rezervasyon-karti-10-yemek-plani.png` | `Ctrl+0` Yemek planı |
| `videolar/02-rezervasyon-karti-sekmeler-ctrl1-ctrl0.mp4` | Sekmeler arasında sırayla geçiş |

## 03 — Oda Planı (Room Rack, `Ctrl+L`)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/03-oda-plani-01-14-gun.png` | 14 günlük oda planı, durum renkleri, temizlik durumu |
| `videolar/03-oda-plani-gezinme-ve-karta-gecis.mp4` | Navigatörden oda planına geçiş, 7/30/14 gün görünümü, rezervasyon kartına geçiş |

## 04 — Giriş (gerçek uygulama)

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/04-giris-01-pencere.png` | Giriş penceresi: kullanıcı adı (ya da e-posta) ve şifre |

## 05 — Ayarlar → Outlet'ler (gerçek uygulama)

Fidelio ayar listesi düzeni: solda ayar ağacı, ortada hızlı arama + liste, sağda komut düğmeleri.

| Dosya | Ne gösteriyor |
|---|---|
| `goruntuler/05-outletler-01-liste.png` | 15 outlet, seçili satır (↑/↓ ile gezinme), iş günü durum çubuğunda |
| `goruntuler/05-outletler-02-ayarlar-menusu.png` | Menü çubuğu → Ayarlar |
| `goruntuler/05-outletler-03-duzenle-byblos.png` | Düzenleme: bilgiler ve bağlı departman kodları ("Yalnız seçilenler") |
| `goruntuler/05-outletler-04-yeni-baska-outletteki-kodlar.png` | Yeni outlet: başka outlet'e bağlı kodlar soluk ve seçilemez |
| `goruntuler/05-outletler-05-yeni-dogrulama-hatasi.png` | Aynı kodla ikinci outlet açılamaz |
| `videolar/05-outletler-klavye-ile-kullanim.mp4` | Giriş → Ayarlar menüsü → liste, hızlı arama, `Alt+E` düzenle, RVC no, `F10` kaydet, `Alt+U` yukarı, `Esc` kapat |

## Taslakları yerelde açmak

```bash
cd docs/ekranlar/kaynak
php -S 127.0.0.1:8000
```

Tarayıcıda `http://127.0.0.1:8000/01-rezervasyon-navigatoru.dc.html` adresini açın. Ekranlar arasındaki bağlantılar çalışır.

Yeni ekran eklendiğinde: kaynak dosya `kaynak/` altına sıradaki numarayla, görüntü ve videolar aynı numarayla eklenir ve bu tablo güncellenir.
