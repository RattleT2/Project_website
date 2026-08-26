# Setup Manual — Laporan Media Kominfo (Backend)

Panduan lengkap dari nol sampai sistem siap digunakan.

---

## Prasyarat

| Komponen | Versi |
|---|---|
| PHP | >= 8.3 |
| Composer | >= 2.x |
| MySQL | >= 8.0 (atau MariaDB >= 10.5) |
| Git | opsional |

Ekstensi PHP yang wajib: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `gd` (untuk captcha)

---

## 1. Clone & Install Dependencies

```bash
git clone <repo-url> laporan-media
cd laporan-media
composer install
```

---

## 2. Konfigurasi Environment (.env)

### 2.1 Copy .env.example
```bash
cp .env.example .env
```

### 2.2 Generate Application Key
```bash
php artisan key:generate
```

### 2.3 Generate JWT Secret
```bash
php artisan jwt:secret
```

### 2.4 Konfigurasi Database
Buka `.env`, isi kredensial database:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laporan_media
DB_USERNAME=root
DB_PASSWORD=password_anda
```

> **Pastikan database sudah dibuat** di MySQL:
> ```sql
> CREATE DATABASE laporan_media CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> ```

### 2.5 Jalankan Migrasi + Seeder
```bash
php artisan migrate --seed
```

Ini akan membuat semua tabel dan mengisi data:
- **5 jenis media**: Online, Cetak, Elektronik, Televisi, Radio
- **~21 pertanyaan evaluasi** (universal + per jenis media)
- **Aturan skor** untuk setiap pertanyaan
- **1 akun admin** (lihat bagian 3)

---

## 3. Akun Default

| Role | Email | Password |
|---|---|---|
| Admin | `admin@kominfo.go.id` | `admin123` |

> **Segera ganti password admin setelah login pertama!**

---

## 4. Storage Link (Upload File)

```bash
php artisan storage:link
```

File PDF yang diupload akan disimpan di `storage/app/public/reports/questions/{id}/`.

---

## 5. Google Login (Opsional)

### 5.1 Buat OAuth Credentials
1. Buka [Google Cloud Console](https://console.cloud.google.com)
2. Pilih project Anda → **APIs & Services > Credentials**
3. **Create Credentials > OAuth client ID**
4. Pilih **Web application**
5. Isi:
   - **Name**: `Laporan Media Kominfo`
   - **Authorized redirect URIs**: `http://localhost:8000/api/auth/google/callback`
6. Copy **Client ID** dan **Client Secret**

### 5.2 Isi di .env
```
GOOGLE_CLIENT_ID=123456789-xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxx
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

> **Production**: ganti URI redirect dengan domain production.

---

## 6. Email (Forgot Password + Notifikasi Status)

### 6.1 Pilihan 1: Development (Mailtrap, gratis)
1. Daftar di [mailtrap.io](https://mailtrap.io)
2. Masuk ke **Email Testing > Inboxes > My Inbox**
3. Pilih **Integrations > Laravel 9+**
4. Copy konfigurasi ke `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=xxxxxxxxxxxxxx
MAIL_PASSWORD=xxxxxxxxxxxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@laporan-media.go.id"
MAIL_FROM_NAME="Laporan Media Kominfo"
```

### 6.2 Pilihan 2: Tanpa Email (Development)
```
MAIL_MAILER=log
```
Email akan ditulis ke `storage/logs/laravel.log` tanpa dikirim.

### 6.3 Pilihan 3: Production (Gmail SMTP / SMTP Server)
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=email_anda@gmail.com
MAIL_PASSWORD=app_password_anda
MAIL_ENCRYPTION=tls
```

> Untuk Gmail: aktifkan 2FA dan gunakan **App Password** di [Google Account Security](https://myaccount.google.com/security) > **App passwords**.

---

## 7. CAPTCHA

CAPTCHA sudah menggunakan `mews/captcha` (gambar self-hosted, tanpa external service).

### Konfigurasi (opsional)
Buka `config/captcha.php` untuk menyesuaikan tampilan captcha.

### Frontend Usage

**1. Request gambar captcha:**
```
GET /api/captcha
```
Response:
```json
{
  "captcha_img": "data:image/png;base64,...",
  "captcha_key": "abc123..."
}
```

**2. Kirim captcha saat register/login/forgot-password:**
```
POST /api/auth/login
{
  "email": "user@example.com",
  "password": "password123",
  "captcha": "jawaban_captcha"    // teks yang user masukkan
}
```

### Menonaktifkan CAPTCHA (Development)
Di `.env`:
```
CAPTCHA_DISABLE=true
```

---

## 8. Menjalankan Server

### Development
```bash
php artisan serve
```
API tersedia di: `http://localhost:8000/api/`

### Production
Gunakan Nginx/Apache. Root document: `public/`

Contoh Nginx:
```nginx
server {
    listen 80;
    server_name laporan-media.kominfo.go.id;
    root /var/www/laporan-media/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 9. Struktur Folder Penting

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── AuthController.php       # Register, login, reset password, Google
│   │   │   ├── CaptchaController.php    # Generate & reload captcha
│   │   │   ├── ReportController.php     # CRUD laporan pelapor
│   │   │   ├── SharedController.php     # Media types, pertanyaan
│   │   │   └── Admin/
│   │   │       ├── ExportController.php # PDF export
│   │   │       ├── ReportController.php # Manajemen laporan admin
│   │   │       └── UserController.php   # Manajemen user admin
│   ├── Middleware/
│   │   └── RoleMiddleware.php           # Cek role + status user
│   └── Requests/                        # Validasi form request
├── Models/                               # Eloquent models
├── Services/
│   ├── ScoringService.php               # Kalkulasi skor & kategori
│   └── ReportService.php                # Bisnis logika laporan
config/
├── auth.php                              # Guard JWT
├── captcha.php                           # Konfigurasi captcha
├── jwt.php                               # Konfigurasi JWT
└── services.php                          # Google OAuth config
database/
├── migrations/                           # Skema database
└── seeders/
    ├── AdminUserSeeder.php
    ├── EvaluationQuestionSeeder.php
    ├── MediaTypeSeeder.php
    └── ScoringRuleSeeder.php
routes/
├── api.php                               # 29+ endpoint API
└── web.php
storage/
└── app/public/reports/                   # File upload disimpan di sini
```

---

## 10. Daftar Lengkap API Endpoint

### Auth
| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/api/auth/register` | No | Registrasi + captcha |
| POST | `/api/auth/login` | No | Login + captcha |
| POST | `/api/auth/logout` | JWT | Logout |
| POST | `/api/auth/refresh` | JWT | Refresh token |
| GET | `/api/auth/me` | JWT | Profile |
| POST | `/api/auth/forgot-password` | No | Reset password link |
| POST | `/api/auth/reset-password` | No | Submit reset password |
| GET | `/api/auth/google` | No | OAuth redirect URL |
| GET | `/api/auth/google/callback` | No | OAuth callback |

### Shared
| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/api/captcha` | Generate captcha image |
| GET | `/api/captcha/reload` | Reload captcha |
| GET | `/api/media-types` | List jenis media |
| GET | `/api/evaluation-questions` | Pertanyaan universal |
| GET | `/api/evaluation-questions/{mediaTypeId}` | Pertanyaan per jenis media |

### Pelapor
| Method | Endpoint | Auth |
|---|---|---|
| GET | `/api/reports` | JWT + pelapor |
| POST | `/api/reports` | JWT + pelapor |
| GET | `/api/reports/{id}` | JWT + pelapor |
| PUT | `/api/reports/{id}` | JWT + pelapor |
| DELETE | `/api/reports/{id}` | JWT + pelapor |
| POST | `/api/reports/{id}/submit` | JWT + pelapor |
| POST | `/api/reports/{reportId}/upload/{questionId}` | JWT + pelapor |

### Admin
| Method | Endpoint | Auth |
|---|---|---|
| GET | `/api/admin/dashboard` | JWT + admin |
| GET | `/api/admin/users` | JWT + admin |
| GET | `/api/admin/users/{id}` | JWT + admin |
| PUT | `/api/admin/users/{id}/status` | JWT + admin |
| DELETE | `/api/admin/users/{id}` | JWT + admin |
| GET | `/api/admin/reports` | JWT + admin |
| GET | `/api/admin/reports/{id}` | JWT + admin |
| PUT | `/api/admin/reports/{id}` | JWT + admin |
| PUT | `/api/admin/reports/{id}/status` | JWT + admin |
| GET | `/api/admin/reports/{id}/pdf` | JWT + admin |
| GET | `/api/admin/export-pdf` | JWT + admin |
| GET | `/api/admin/export-excel` | JWT + admin |

### Filter Admin Reports
| Param | Tipe | Deskripsi |
|---|---|---|
| `status` | string | pending / proses / disetujui |
| `media_type_id` | int | Filter jenis media |
| `search` | string | Cari nama/email/ID |
| `date_from` | date | YYYY-MM-DD |
| `date_to` | date | YYYY-MM-DD |
| `score_min` | int | Skor minimum |
| `score_max` | int | Skor maksimum |
| `sort_by` | string | created_at / total_score / status |
| `sort_dir` | string | asc / desc |
| `per_page` | int | Jumlah per halaman (max 100) |

---

## 11. Kategori Penilaian

| Total Skor | Kategori |
|---|---|
| 68 - 82 | Kategori 1 |
| 40 - 67 | Kategori 2 |
| 20 - 39 | Kategori 3 |
| 0 - 19 | Tidak memenuhi kategori |

---

## 12. Troubleshooting

### Error: Class "Tymon\JWTAuth\Providers\JWT\Provider" not found
```bash
composer dump-autoload -o
php artisan config:clear
php artisan cache:clear
```

### Error: SQLSTATE[HY000] [2002] Connection refused
- Pastikan MySQL/MariaDB berjalan
- Periksa kredensial di `.env`

### CAPTCHA tidak muncul
- Pastikan ekstensi PHP `gd` terinstall
- Atau set `CAPTCHA_DISABLE=true` di `.env`

### Storage link 404
```bash
php artisan storage:link --force
```

### Reset semuanya (fresh start)
```bash
php artisan migrate:fresh --seed
php artisan storage:link
php artisan optimize:clear
```

---

## 13. Untuk Tim Frontend

Dokumentasi lengkap setiap endpoint ada di `api_documentation.md`.

### Catatan Penting untuk Frontend:
1. **Token JWT**: Simpan di localStorage, kirim via header `Authorization: Bearer <token>`
2. **Refresh token**: Sebelum expired (default 1 jam), panggil `POST /api/auth/refresh`
3. **CAPTCHA**: Panggil `GET /api/captcha` untuk mendapatkan gambar, user input teks captcha
4. **Upload file**: Gunakan endpoint terpisah, format PDF max 5MB
5. **Pertanyaan per media**: Panggil `GET /api/evaluation-questions/{mediaTypeId}` setelah user memilih jenis media
6. **Draft**: Buat laporan tanpa `submit: true`, panggil submit endpoint saat siap
7. **Edit**: Laporan hanya bisa diedit saat status `pending`
