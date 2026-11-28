#!/usr/bin/env bash
# Canlı sunucuda güncelleme (docs/15). pms kullanıcısıyla, proje klasöründen çalıştırılır:
#   su -s /bin/bash - pms
#   cd /home/pms/rs-pms && bash scripts/guncelle.sh
# Sırası: yedek → bakım modu → git pull → paketler → veritabanı → tanımlar → ekranlar → önbellek → arka plan işlerini yeniden başlat → aç.
# Bir adım hata verirse durur; site bakım modunda kalır (düzeltip betiği yeniden çalıştırın ya da "php artisan up").
set -euo pipefail
cd "$(dirname "$0")/.."

if [ "$(id -u)" -eq 0 ]; then
    echo "Root olarak çalıştırmayın. Önce: su -s /bin/bash - pms" >&2
    exit 1
fi

echo "== Yedek"
php artisan pms:backup || echo "UYARI: yedek alınamadı (BACKUP_PATH ve mysqldump'ı kontrol edin); devam ediliyor."

echo "== Bakım modu"
php artisan down --retry=30 || true
# Bir adım hata verirse site bakımda kalmasın: önceki derlenmiş ekranlarla açılır, hata ekranda kalır
trap 'echo "HATA: güncelleme yarıda kaldı; site yeniden açılıyor (çıktıyı kontrol edin)." >&2; php artisan up || true' ERR

echo "== Kod (git pull)"
git pull --ff-only

echo "== PHP paketleri"
composer install --no-dev --optimize-autoloader --no-interaction

echo "== Veritabanı ve tanımlar"
php artisan migrate --force
php artisan db:seed --class=RocksHotelReferenceSeeder --force

echo "== Ekranlar"
npm ci --no-audit --no-fund
# CWP hesaplarında işlem / iş parçacığı sınırı var; Vite (rolldown) varsayılan olarak çekirdek sayısı kadar iş parçacığı açar
# ve "Resource temporarily unavailable" ile düşer. Az iş parçacığıyla derlenir, olmazsa tek iş parçacığıyla yeniden denenir.
RAYON_NUM_THREADS=2 UV_THREADPOOL_SIZE=2 npm run build || RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=1 npm run build

echo "== Önbellek"
php artisan optimize:clear
php artisan optimize

echo "== Arka plan işleri (cron bir dakika içinde yeni kodla başlatır)"
pkill -u "$(id -u)" -f "pms:simphony-listen" || true
pkill -u "$(id -u)" -f "queue:work" || true

php artisan up
echo "== Tamam: $(git log --oneline -1)"
