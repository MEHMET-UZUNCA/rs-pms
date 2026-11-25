# 7. Arayüz

Arayüz Türkçe; görünüm ve düzen **QloApps yönetim paneli** örnek alınarak (kod alınmadan) yeniden çizilir.
RS ONE ile aynı bileşen dili (Blade + Tailwind) kullanılır; birleşmede iki uygulama aynı görünür.

## 7.1 Genel düzen (QloApps'ten)

- **Sol menü** (daraltılabilir): Ön Büro, Rezervasyonlar, Oda Planı, Kasa, Cari, Kat Hizmetleri, Raporlar, Gece Kapanışı, Tanımlar.
- **Üst çubuk:** tesis seçici, **iş günü göstergesi** (takvim gününden farklıysa renkli), hızlı arama (oda no / misafir / rezervasyon no), kullanıcı.
- **Liste ekranları:** başlıkta filtre satırı, sıralanabilir kolonlar, toplu işlem, Excel/PDF dışa aktarma.
- **Kart ekranları:** sekmeli (Genel, Misafirler, Fiyat, Folyo, İzler, Geçmiş).
- Klavye kısayolları (resepsiyonda fare kullanımını azaltmak için): F2 arama, F3 yeni rezervasyon, F8 kayıt at.

## 7.2 Ekranlar

| Ekran | Örnek | İçerik |
|---|---|---|
| **Bugün** | Kamra "Today" | Gelecekler, gidecekler, içeridekiler, ödenmemiş bakiyeler, oda durumu özeti, izler |
| **Müsaitlik / Hızlı rezervasyon** | QloApps "Book Now" | Tarih + kişi → oda tipi bazında müsait sayı ve fiyat; odayı seç, rezervasyona ekle |
| **Oda planı (tape chart)** | QloApps Front Desk, Fidelio | Odalar × günler; sürükle-bırak oda değiştirme, süre uzatma; renkler durum ve segmente göre |
| **Rezervasyon kartı** | Fidelio | Misafir, tarih, oda, fiyat kodu, gecelik fiyatlar, garanti, paylaşanlar, izler, uyarılar |
| **Profil kartı** | Fidelio | Bilgiler, kimlik, iletişim, tercihler, geçmiş konaklamalar (arşiv dahil), istatistik |
| **Giriş / Çıkış** | Kamra | Kimlik okuma, oda atama, anahtar, kimlik bildirimi; çıkışta folyo kontrolü ve fatura |
| **Folyo** | Fidelio | 8 pencere sekmesi, kayıt at, ters kayıt, transfer, yönlendirme, ödeme, fatura |
| **Kasa** | Fidelio | Vardiya aç/kapat, sayım, fark |
| **Cari** | Kamra | Hesap listesi, açık kalemler, yaşlandırma, tahsilat eşleme, ekstre |
| **Kat hizmetleri** | Kamra | Oda durum panosu, görev dağıtımı, telefon ekranı (kat görevlisi) |
| **Gece kapanışı** | Kamra | Kontrol listesi (engelleyen/uyaran), tek düğme, adım ilerlemesi, rapor paketi |
| **Raporlar** | Fidelio | Gelecekler/gidecekler, doluluk, gelir (işlem kodu), segment, yönetici raporu, tahmin |
| **Tanımlar** | QloApps | Tesis, oda tipi, oda, fiyat kodu, fiyat tablosu, paket, işlem kodu, outlet, Simphony ve Logo eşlemeleri |

## 7.3 Onay sonrası ilk iş

Kodlamadan önce **Bugün**, **Oda planı** ve **Rezervasyon kartı** ekranlarının tıklanabilir görsel taslakları hazırlanıp onaya sunulacak.
