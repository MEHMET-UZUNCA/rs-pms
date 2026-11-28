# 15. Sunucu kurulumu, yedekleme ve işletim

Otel sunucusunda (iç ağ) RS PMS'in canlıda çalışması için gerekenler. Örnekler Ubuntu 24.04 LTS içindir; yol `/var/www/rs-pms`.

## 15.1 Gerekenler

| Bileşen | Sürüm / not |
|---|---|
| PHP | 8.3; eklentiler: `bcmath ctype curl dom fileinfo gd intl mbstring mysql opcache pdo_mysql redis xml zip` |
| Veritabanı | MySQL 8 ya da MariaDB 10.11+ (utf8mb4) |
| Redis | 7 (oturum, önbellek, kuyruk) |
| Web | Nginx + PHP-FPM |
| Node | 20 LTS (yalnız `npm run build` için) |
| Composer | 2 |
| Diğer | `mysqldump` / `mariadb-dump`, `gzip`, `supervisor` |

```bash
sudo apt install nginx mariadb-server redis-server supervisor php8.3-fpm php8.3-{bcmath,curl,gd,intl,mbstring,mysql,opcache,redis,xml,zip} composer nodejs npm
```

## 15.2 İlk kurulum

```bash
sudo mkdir -p /var/www/rs-pms && sudo chown $USER:www-data /var/www/rs-pms
```
```bash
git clone https://github.com/MEHMET-UZUNCA/rs-pms.git /var/www/rs-pms
```
```bash
cd /var/www/rs-pms && composer install --no-dev --optimize-autoloader
```
```bash
cp .env.example .env && php artisan key:generate
```

`.env` canlı değerleri (parolalar yalnız sunucuda; depoya, sohbete, e-postaya yazılmaz):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pms.rocks.local
APP_LOCALE=en
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=rs_pms
DB_USERNAME=rs_pms
DB_PASSWORD=...
SESSION_DRIVER=redis
SESSION_LIFETIME=30          # ortak ön büro bilgisayarları: 30 dk boşta kalan oturum kapanır (otel karar verir)
QUEUE_CONNECTION=redis
CACHE_STORE=redis
LOG_CHANNEL=daily            # günlük log dosyası, 14 gün saklanır (LOG_DAILY_DAYS)
MAIL_...                     # onay mektubu için SMTP
BACKUP_PATH=/var/backups/rs-pms
BACKUP_COPY_PATH=/mnt/yedek/rs-pms   # ikinci disk ya da ağ paylaşımı
BACKUP_KEEP_DAYS=30
```

```bash
php artisan migrate --force && php artisan db:seed --class=RocksHotelReferenceSeeder --force
```
```bash
npm ci && npm run build
```
```bash
php artisan pms:create-user MU "M.U" kullanici@ornek.com --role=admin
```
```bash
php artisan optimize && sudo chown -R www-data:www-data storage bootstrap/cache
```

## 15.3 Nginx (HTTPS)

İç ağda da HTTPS kullanılır (şifreler ve misafir bilgileri ağda açık gitmez). Otelin iç sertifikası ya da kendi imzalı sertifika.

```
server {
    listen 443 ssl http2;
    server_name pms.rocks.local;
    ssl_certificate     /etc/ssl/rocks/pms.crt;
    ssl_certificate_key /etc/ssl/rocks/pms.key;
    root /var/www/rs-pms/public;
    index index.php;
    client_max_body_size 20m;
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-Content-Type-Options nosniff;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
    location ~ /\.(?!well-known) { deny all; }
}
server { listen 80; server_name pms.rocks.local; return 301 https://$host$request_uri; }
```

## 15.4 Zamanlanmış işler (cron)

```bash
echo '* * * * * www-data cd /var/www/rs-pms && php artisan schedule:run >> /dev/null 2>&1' | sudo tee /etc/cron.d/rs-pms
```

| Ne | Ne zaman |
|---|---|
| Döviz kurları (`pms:fetch-exchange-rates`) | 09:05 ve 15:40 (İstanbul) |
| Veritabanı yedeği (`pms:backup`) | 04:15 ve 08 / 12 / 16 / 20:00 (Lefkoşa) |
| KVKK anonimleştirme (`pms:anonymize-profiles`) | Her ayın 1'i 03:30 (saklama süresi girilmişse) |

Gece kapanışı otomatik değildir; gece müdürü Miscellaneous → Night Audit'ten çalıştırır.

## 15.5 Sürekli çalışan işlemler (Supervisor)

Simphony dinleyicisi (TCP 5010) ve kuyruk işçisi sürekli çalışmalı; düşerse Supervisor yeniden başlatır.

`/etc/supervisor/conf.d/rs-pms.conf`:

```
[program:rs-pms-simphony]
command=php /var/www/rs-pms/artisan pms:simphony-listen
user=www-data
autostart=true
autorestart=true
startretries=100
stopwaitsecs=10
stdout_logfile=/var/www/rs-pms/storage/logs/simphony.log
stdout_logfile_maxbytes=20MB
redirect_stderr=true

[program:rs-pms-queue]
command=php /var/www/rs-pms/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/www/rs-pms/storage/logs/queue.log
stdout_logfile_maxbytes=20MB
redirect_stderr=true
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

Güvenlik duvarı: 5010 numaralı port yalnız Simphony sunucusuna (192.168.200.x) açılır; 443 yalnız otel ağına.

```bash
sudo ufw allow from 192.168.200.11 to any port 5010 proto tcp && sudo ufw allow from 192.168.200.0/24 to any port 443 proto tcp
```

## 15.6 Yedekleme ve geri yükleme

`pms:backup` tutarlı (`--single-transaction`, kilitsiz) bir döküm alır, gzip'ler, `BACKUP_PATH`'e yazar, `BACKUP_COPY_PATH`'e kopyalar,
`BACKUP_KEEP_DAYS`'ten eski yedekleri siler. Elle:

```bash
cd /var/www/rs-pms && sudo -u www-data php artisan pms:backup
```

Gün içi kayıp riskini dakikalara indirmek için MySQL / MariaDB ikili günlüğü (binlog) açılır — `/etc/mysql/mariadb.conf.d/60-rspms.cnf`:

```
[mysqld]
log_bin = /var/log/mysql/mysql-bin
binlog_format = ROW
expire_logs_days = 7
```

Yedeğin işe yaradığı **ayda bir** deneme veritabanına geri yüklenerek denetlenir:

```bash
gunzip -c /var/backups/rs-pms/rs-pms_mysql_2026-12-01_041500.sql.gz | mysql -u root rs_pms_deneme
```

Gerçek geri dönüş (felaket): uygulamayı bakım moduna al (`php artisan down`), son tam yedeği yükle, gerekirse binlog'dan yedek saatinden
arıza anına kadar olanları uygula (`mysqlbinlog --start-datetime=…`), `php artisan up`. Yedeklerin bir kopyası mutlaka sunucunun dışında olmalı.

## 15.7 Güncelleme

```bash
cd /var/www/rs-pms && php artisan pms:backup && php artisan down
```
```bash
git pull && composer install --no-dev --optimize-autoloader
```
```bash
php artisan migrate --force && php artisan db:seed --class=RocksHotelReferenceSeeder --force
```
```bash
npm ci && npm run build && php artisan optimize:clear && php artisan optimize
```
```bash
sudo supervisorctl restart rs-pms-simphony rs-pms-queue && php artisan up
```

Gece kapanışı sırasında ya da Simphony yoğunken (yemek saatleri) güncelleme yapılmaz.

## 15.8 Kontrol listesi (canlıya geçmeden)

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS çalışıyor
- [ ] Cron çalışıyor (`php artisan schedule:list`)
- [ ] Supervisor: Simphony dinleyicisi ve kuyruk `RUNNING`; Simphony'den oda sorgusu deneniyor
- [ ] İlk yedek alındı, ikinci yere kopyalandı, deneme veritabanına geri yüklendi
- [ ] Kullanıcılar açıldı (`pms:import-users`), herkes ilk girişte şifresini değiştirdi
- [ ] SMTP denendi (onay mektubu)
- [ ] Rezervasyon seçenekleri, web kodları, KVKK saklama süresi, overbooking sınırları otelle girildi
- [ ] Ekran ve klavye kabul testi (docs/13) otel bilgisayarlarında çalıştırıldı

## 15.9 CentOS Web Panel (CWP) üzerinde, `pms` hesabının altına kurulum

Otel sunucusunda CWP var ve `pms` adlı hesap açıldı. Kurulum bu hesabın evine (`/home/pms`) yapılır; root yalnız iki üç iş için gerekir
(aşağıda **root** diye işaretli). Redis ve Supervisor kullanılmaz: oturum, önbellek ve kuyruk veritabanında; sürekli çalışan işlemler cron + `flock` ile.

**Panelde (CWP yönetici):**
1. User Accounts → `pms` → **Shell access: bash** (jailed değil; git ve composer için).
2. PHP **8.3** (PHP-FPM Selector ya da PHP Switcher) ve eklentileri: `bcmath intl mbstring gd zip pdo_mysql opcache fileinfo`. Hesabın PHP sürümü 8.3 seçilir.
3. **root**: `git` ve `mysqldump` kurulu olmalı (`dnf install -y git`).

**Panelde (pms kullanıcısı) → MySQL Manager:** veritabanı `rspms` ve kullanıcı `rspms` oluşturulur. CWP önek ekler: ad **`pms_rspms`**, kullanıcı **`pms_rspms`**.

**SSH ile `pms` olarak:**

```bash
php -v
```
8.3 değilse CLI için tam yol kullanılır (ör. `/opt/alt/php-fpm83/usr/bin/php`); aşağıda `php` yazan yerlere o yol konur.

```bash
cd ~ && curl -sS https://getcomposer.org/installer | php && mkdir -p ~/bin && mv composer.phar ~/bin/composer
```
```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash && source ~/.bashrc && nvm install 20
```
```bash
ssh-keygen -t ed25519 -C "rs-pms sunucu" -f ~/.ssh/id_ed25519 -N "" && cat ~/.ssh/id_ed25519.pub
```
Çıkan satır GitHub → rs-pms → Settings → Deploy keys'e eklenir (yazma izni verilmez).

```bash
git clone git@github.com:MEHMET-UZUNCA/rs-pms.git ~/rs-pms && cd ~/rs-pms && ~/bin/composer install --no-dev --optimize-autoloader
```
```bash
cp .env.example .env && php artisan key:generate && nano .env
```
`.env`'de: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=...`, `DB_DATABASE=pms_rspms`, `DB_USERNAME=pms_rspms`, `DB_PASSWORD=...`,
`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `LOG_CHANNEL=daily`, `BACKUP_PATH=/home/pms/yedek`.

```bash
php artisan migrate --force && php artisan db:seed --class=RocksHotelReferenceSeeder --force && npm ci && npm run build
```
```bash
php artisan pms:create-user MU "M.U" kullanici@ornek.com --role=admin && php artisan optimize
```

**Site kökü:** Laravel'in açılan klasörü `public`'tir. `public_html` boşsa yerine bağlantı konur (doluysa önce içine bakılır):
```bash
ls -A ~/public_html && rm -rf ~/public_html && ln -s ~/rs-pms/public ~/public_html
```
Panelden alan adı / SSL (AutoSSL ya da otelin sertifikası) ayarlanır; `public/.htaccess` Apache yönlendirmesini yapar.

**Ekran derlemesi:** CWP hesaplarında iş parçacığı sınırı olduğu için `npm run build` "The global thread pool has not been initialized … Resource temporarily
unavailable" hatasıyla düşebilir; `RAYON_NUM_THREADS=2 npm run build` (olmazsa `=1`) ile derlenir. `scripts/guncelle.sh` bunu kendisi yapar.

**Zamanlanmış ve sürekli işler (`crontab -e`, pms kullanıcısı):**
```
* * * * * cd /home/pms/rs-pms && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/pms/rs-pms && flock -n /home/pms/.simphony.lock php artisan pms:simphony-listen >> storage/logs/simphony.log 2>&1
* * * * * cd /home/pms/rs-pms && flock -n /home/pms/.queue.lock php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 >> storage/logs/queue.log 2>&1
```
`flock -n`: dinleyici çalışıyorsa bir şey yapmaz; düşmüşse en geç bir dakikada yeniden başlatır (Supervisor yerine).

**root — güvenlik duvarı (CSF):** Simphony dinleyicisinin portu yalnız Simphony sunucusuna açılır; `/etc/csf/csf.allow`'a
`tcp|in|d=5010|s=192.168.200.11` eklenip `csf -r`.

**Güncelleme (tek komut):** `scripts/guncelle.sh` — yedek, bakım modu, pull, paketler, migration, seeder, derleme, önbellek, arka plan işleri.
```bash
su -s /bin/bash - pms
```
```bash
cd /home/pms/rs-pms && bash scripts/guncelle.sh
```
Elle yapmak isterseniz:
```bash
cd ~/rs-pms && php artisan pms:backup && php artisan down && git pull && ~/bin/composer install --no-dev --optimize-autoloader
```
```bash
php artisan migrate --force && php artisan db:seed --class=RocksHotelReferenceSeeder --force && npm ci && npm run build && php artisan optimize:clear && php artisan optimize && php artisan up
```
```bash
pkill -f "pms:simphony-listen"; pkill -f "queue:work"
```
(son satır: cron bir dakika içinde yeni kodla yeniden başlatır)
