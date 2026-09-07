# Panduan Lengkap Manual & Deployment Produksi — Laporan Media Kominfo (Backend)

Dokumen ini berisi panduan lengkap dari tahap pengembangan hingga alur pemindahan dan deployment aplikasi ke **Server Produksi (Live VPS / Cloud Server Kominfo)**.

---

## DAFTAR ISI
1. [Struktur & Prasyarat Sistem](#1-prasyarat-sistem)
2. [Alur Deployment Produksi Opsi A: Menggunakan Docker (Rekomendasi Utama)](#2-opsi-a-deployment-menggunakan-docker-rekomendasi)
3. [Alur Deployment Produksi Opsi B: Manual di Ubuntu Linux VPS](#3-opsi-b-deployment-manual-di-ubuntu-linux-vps)
4. [Konfigurasi Domain, HTTPS (SSL), dan Google OAuth](#4-konfigurasi-domain-https-ssl-dan-google-oauth)
5. [Daftar Akun Default & Pengelolaan Seeder](#5-daftar-akun-default--seeder)
6. [Struktur Folder & Referensi API Endpoint](#6-referensi-api-endpoint--kategori)
7. [Panduan Pemeliharaan, Backup & Troubleshooting](#7-pemeliharaan-backup--troubleshooting)

---

## 1. Prasyarat Sistem

### Spesifikasi Minimal Server Produksi
- **CPU**: 2 vCPU
- **RAM**: Minimal 2 GB (Disarankan 4 GB)
- **OS**: Ubuntu 22.04 LTS / 24.04 LTS (atau CentOS / AlmaLinux)
- **Storage**: SSD minimal 25 GB
- **Akses**: Root / SSH User dengan akses `sudo`

### Kebutuhan Perangkat Lunak (Native Install)
- **PHP**: Versi `>= 8.2` (Modul: `pdo_mysql`, `mbstring`, `exif`, `pcntl`, `bcmath`, `gd`, `zip`, `opcache`, `xml`)
- **Web Server**: Nginx
- **Database**: MySQL 8.0+ atau MariaDB 10.5+
- **Composer**: Versi `>= 2.x`

---

## 2. OPSI A: Deployment Menggunakan Docker (Rekomendasi)

Pendekatan ini paling disukai oleh tim infrastruktur/IT Kominfo karena aplikasi dan database terisolasi rapi, terhindar dari konflik versi PHP/MySQL di server, dan siap dijalankan dengan **1 baris perintah**.

### Langkah 2.1 — Install Docker & Docker Compose di Server Ubuntu
Jalankan perintah ini di terminal server VPS produksi:

```bash
# 1. Update package manager
sudo apt update && sudo apt upgrade -y

# 2. Install Docker & Docker Compose Plugin
sudo apt install -y docker.io docker-compose-v2 git

# 3. Jalankan & aktifkan Docker service
sudo systemctl enable --now docker
```

---

### Langkah 2.2 — Clone Repository Proyek ke Server
```bash
# Pindah ke direktori web server
cd /var/www

# Clone proyek dari Git repository
sudo git clone <URL_REPOSITORY_ANDA> laporan-media
cd laporan-media
```

---

### Langkah 2.3 — Buat & Konfigurasi File `.env` Produksi
```bash
# Copy dari contoh template
cp .env.example .env

# Edit file .env menggunakan nano
nano .env
```

Sesuaikan nilai-nilai berikut di `.env` produksi:
```env
APP_NAME="Laporan Media Kominfo"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-laporanmedia.banjarkab.go.id
FRONTEND_URL=https://laporanmedia.banjarkab.go.id
APP_TIMEZONE=Asia/Makassar

# Kredensial Database Docker Internal
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=laporan_media
DB_USERNAME=laporan_user
DB_PASSWORD=Password_Sangat_Aman_123!

# JWT Auth
JWT_SECRET=IsiDenganStringJWT_Secret_32_Char_Lebih

# Google OAuth Production
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxx
GOOGLE_REDIRECT_URI=https://laporanmedia.banjarkab.go.id/api/auth/google/callback

# SMTP Email Real (Gmail / Server Mail Kominfo)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=kominfomtpadmin@gmail.com
MAIL_PASSWORD=bzwabhcsdmrucuqm
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="kominfomtpadmin@gmail.com"
MAIL_FROM_NAME="Laporan Media Kominfo"
```

---

### Langkah 2.4 — Jalankan Docker Containers
```bash
# Build dan jalankan container di background
sudo docker compose up -d --build
```

### Langkah 2.5 — Generate Key & Run Database Seeder
```bash
# Generate APP_KEY & JWT Secret di dalam container
sudo docker compose exec app php artisan key:generate
sudo docker compose exec app php artisan jwt:secret --force

# Seed database awal (Jenis Media, Pertanyaan, Skor, Admin)
sudo docker compose exec app php artisan db:seed --force
```

Selesai! Aplikasi backend Anda kini berjalan secara terisolasi di port `8000` (atau port yang diset di `docker-compose.yml`).

---

## 3. OPSI B: Deployment Manual di Ubuntu Linux VPS

Jika Anda memilih untuk tidak menggunakan Docker dan menginstall Nginx + PHP secara native di VPS Ubuntu:

### Langkah 3.1 — Install PHP 8.2, Nginx, dan MySQL
```bash
sudo apt update && sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y nginx mysql-server composer git \
  php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml \
  php8.4-bcmath php8.4-gd php8.4-zip php8.4-curl php8.4-intl
```

---

### Langkah 3.2 — Buat Database MySQL
```bash
sudo mysql -u root
```
Di dalam prompt MySQL, jalankan:
```sql
CREATE DATABASE laporan_media CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'laporan_user'@'localhost' IDENTIFIED BY 'Password_Sangat_Aman_123!';
GRANT ALL PRIVILEGES ON laporan_media.* TO 'laporan_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

### Langkah 3.3 — Setup Proyek & Permission
```bash
cd /var/www
sudo git clone <URL_REPOSITORY_ANDA> laporan-media
cd laporan-media

# Install dependencies composer
sudo composer install --no-dev --optimize-autoloader

# Buat file .env dan isi kredensial
cp .env.example .env
nano .env

# Generate Key & Migrate
php artisan key:generate
php artisan jwt:secret --force
php artisan migrate --seed --force
php artisan storage:link --force

# Atur Izin Akses Folder (Sangat Penting!)
sudo chown -R www-data:www-data /var/www/laporan-media
sudo chmod -R 775 /var/www/laporan-media/storage
sudo chmod -R 775 /var/www/laporan-media/bootstrap/cache
```

---

### Langkah 3.4 — Konfigurasi Nginx Web Server
Buat file konfigurasi Nginx baru:
```bash
sudo nano /etc/nginx/sites-available/laporan-media
```
Tempelkan konfigurasi berikut:
```nginx
server {
    listen 80;
    server_name api-laporanmedia.banjarkab.go.id;
    root /var/www/laporan-media/public;

    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan konfigurasi Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/laporan-media /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## 4. Konfigurasi Domain, HTTPS (SSL), dan Google OAuth

### 4.1 Pasang SSL Gratis (Certbot Let's Encrypt)
Google OAuth dan API JWT **mewajibkan** protokol HTTPS. Jalankan perintah ini di VPS:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api-laporanmedia.banjarkab.go.id
```
Certbot akan otomatis memperbarui file Nginx Anda sehingga mendukung HTTPS secara aman.

### 4.2 Konfigurasi Google Cloud Console Produksi
1. Buka [Google Cloud Console Credentials](https://console.cloud.google.com/apis/credentials).
2. Pilih OAuth 2.0 Client ID milik Anda.
3. Tambahkan ke **Authorized JavaScript origins**:
   `https://laporanmedia.banjarkab.go.id`
4. Tambahkan ke **Authorized redirect URIs**:
   `https://laporanmedia.banjarkab.go.id/api/auth/google/callback`
5. Masuk ke **OAuth consent screen** dan klik **PUBLISH APP**.

---

## 5. Daftar Akun Default & Seeder

### Akun Bawaan Seeder Awal
| Role | Nama | Email | Password Default | NIP |
|---|---|---|---|---|
| Admin Utama | Admin Kominfo | `admin@kominfo.go.id` | `admin123` | - |
| Admin Media 1 | Admin Media 1 | `admin2@kominfo.go.id` | `admin123` | `198503152010011002` |
| Admin Media 2 | Admin Media 2 | `admin3@kominfo.go.id` | `admin3@kominfo.go.id` | `198807202014022003` |
| Admin Google | Admin Kominfo | `kominfomtpadmin@gmail.com` | `admin123` | - |

> **⚠️ Keamanan**: Setelah sistem dipasang di produksi, segera ganti password akun admin atau ubah email admin ke email instansi resmi!

---

## 6. Referensi API Endpoint & Kategori

### Kategori Penilaian Otomatis
Penilaian total skor dikelompokkan menjadi 4 tingkat kategori:

| Range Total Skor | Kategori |
|---|---|
| **68 – 82** | Kategori 1 |
| **40 – 67** | Kategori 2 |
| **20 – 39** | Kategori 3 |
| **0 – 19** | Tidak Memenuhi Kategori |

### Ringkasan Endpoint Utama

#### Autentikasi
* `POST /api/auth/register` — Registrasi pelapor (+ CAPTCHA)
* `POST /api/auth/login` — Login (+ CAPTCHA)
* `GET /api/auth/google` — Dapatkan URL Redirect OAuth Google
* `GET /api/auth/google/callback` — Handle Callback token Google
* `POST /api/auth/forgot-password` — Request email reset password
* `POST /api/auth/reset-password` — Submit password baru

#### Pelapor
* `GET /api/reports` — Daftar laporan milik pelapor login
* `POST /api/reports` — Buat draft laporan baru
* `POST /api/reports/{id}/submit` — Submit laporan final
* `POST /api/reports/{reportId}/upload/{questionId}` — Upload bukti lampiran PDF

#### Admin
* `GET /api/admin/dashboard` — Statistik & ringkasan dashboard admin
* `GET /api/admin/reports` — Filter & pencarian seluruh laporan
* `PUT /api/admin/reports/{id}/status` — Update status laporan (`proses`, `disetujui`)
* `GET /api/admin/export-excel` — Export laporan ke format `.xlsx`
* `GET /api/admin/export-pdf` — Export rekap ke PDF

---

## 7. Pemeliharaan, Backup & Troubleshooting

### Perintah Optimasi Produksi (Jalankan setelah perbaikan kode/`.env`)
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Memantau Log Error
* **Jika Menggunakan Docker**:
  ```bash
  sudo docker compose logs -f app
  ```
* **Jika Native Ubuntu**:
  ```bash
  tail -f storage/logs/laravel.log
  ```

### Backup Database Rutin
```bash
# Manual MySQL dump
mysqldump -u laporan_user -p laporan_media > backup_laporan_$(date +%Y%m%d).sql
```
