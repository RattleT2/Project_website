# Panduan Deployment & Konfigurasi Google OAuth di Server Production

Dokumen ini berisi panduan langkah demi langkah untuk mengonfigurasi **Google Cloud Console** dan backend **Laravel** ketika aplikasi siap dideploy ke server produksi (Live / Production Server).

---

## DAFTAR ISI
1. [Prasyarat Production](#1-prasyarat-production)
2. [Langkah Konfigurasi di Google Cloud Console](#2-langkah-konfigurasi-di-google-cloud-console)
3. [Konfigurasi Environment Backend (`.env`)](#3-konfigurasi-environment-backend-env)
4. [Pemeriksaan Akhir & Perintah Optimization](#4-pemeriksaan-akhir--perintah-optimization)
5. [Troubleshooting Umum](#5-troubleshooting-umum)

---

## 1. Prasyarat Production

Sebelum melakukan konfigurasi Google OAuth untuk production:
- [x] Domain publik sudah aktif dan terpasang SSL (**HTTPS**). *Google OAuth mewajibkan protokol `https://` untuk domain produksi (kecuali `http://localhost` untuk pengujian).*
- [x] Server backend dan frontend sudah dapat diakses via domain publik (contoh: `https://laporan-media.kominfo.go.id` atau `https://api-laporan.kominfo.go.id`).

---

## 2. Langkah Konfigurasi di Google Cloud Console

### Langkah 2.1 — Buka Konsol & Pilih Proyek
1. Akses **[Google Cloud Console](https://console.cloud.google.com/)**.
2. Pastikan Anda telah memilih Proyek yang sesuai di bagian baris atas konsol.

---

### Langkah 2.2 — Konfigurasi OAuth Consent Screen (Layar Persetujuan OAuth)
1. Buka menu navigasi kiri → **APIs & Services** → **OAuth consent screen**.
2. **User Type**:
   * Pilih **Internal** jika aplikasi hanya diperuntukkan untuk email berdomain instansi (misal: `@kominfo.go.id`).
   * Pilih **External** jika dapat diakses oleh publik/masyarakat umum.
3. Klik **CREATE**.
4. Isi informasi dasar aplikasi:
   * **App name**: `Laporan Media Kominfo`
   * **User support email**: Email admin/dukungan instansi Anda.
   * **App logo**: (Opsional) Upload logo instansi.
   * **App domain**:
     * Application home page: `https://laporan-media.kominfo.go.id`
     * Authorized domains: `kominfo.go.id` (masukkan nama domain tanpa `https://`).
   * **Developer contact information**: Masukkan email pengembang/tim IT.
5. Klik **SAVE AND CONTINUE**.
6. **Scopes**: Klik **ADD OR REMOVE SCOPES**, centang 3 scope dasar:
   * `.../auth/userinfo.email`
   * `.../auth/userinfo.profile`
   * `openid`
   * Klik **UPDATE** → **SAVE AND CONTINUE**.
7. **Publishing Status**:
   * Jika menggunakan tipe **External**, status aplikasi awalnya dalam mode **Testing**.
   * Klik tombol **PUBLISH APP** agar semua pemilik akun Google umum bisa login tanpa harus didaftarkan manual sebagai *Test User*.

---

### Langkah 2.3 — Menambahkan Authorized Redirect URIs di Credentials
1. Buka menu navigasi kiri → **APIs & Services** → **Credentials**.
2. Klik nama Client ID OAuth 2.0 yang sudah Anda buat (atau klik **+ CREATE CREDENTIALS** → **OAuth client ID** jika belum ada).
3. **Application type**: Pilih `Web application`.
4. **Name**: `Web Client Laporan Media Production`.
5. **Authorized JavaScript origins**:
   Tambahkan domain frontend Anda:
   * `https://laporan-media.kominfo.go.id`
6. **Authorized redirect URIs** *(SANGAT PENTING)*:
   Tambahkan URL callback resmi produksi:
   * `https://laporan-media.kominfo.go.id/api/auth/google/callback` *(jika callback diarahkan via frontend)*
   * `https://api-laporan.kominfo.go.id/api/auth/google/callback` *(jika backend memiliki subdomain terpisah)*
7. Klik **SAVE**.

---

## 3. Konfigurasi Environment Backend (`.env`)

Pada server produksi, buka file `.env` di direktori proyek Laravel Anda dan perbarui variabel berikut:

```env
# 1. Konfigurasi Aplikasi Produksi
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-laporan.kominfo.go.id
FRONTEND_URL=https://laporan-media.kominfo.go.id

# 2. Google OAuth Credentials Production
GOOGLE_CLIENT_ID=xxxxxxxxxxxx-xxxxxxxxxxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx
GOOGLE_REDIRECT_URI=https://laporan-media.kominfo.go.id/api/auth/google/callback
```

> **Perhatian**: Pastikan nilai `GOOGLE_REDIRECT_URI` di `.env` **persis sama** karakter demi karakter (termasuk `https://`) dengan yang Anda daftarkan di Google Cloud Console pada Langkah 2.3.

---

## 4. Pemeriksaan Akhir & Perintah Optimization

Setelah file `.env` diubah di server produksi, jalankan serangkaian perintah ini di terminal server untuk mengoptimalkan kinerja Laravel dan mengaplikasikan konfigurasi baru:

```bash
# 1. Hapus cache lama
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 2. Buat cache baru untuk ketersediaan cepat (Production Mode)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 5. Troubleshooting Umum

### 1. Error `400: redirect_uri_mismatch`
- **Penyebab**: `GOOGLE_REDIRECT_URI` di file `.env` tidak sama dengan yang terdaftar di Google Cloud Console.
- **Solusi**: Periksa protokol (`https://`), port, serta akhiran slash (`/`). Salin nilai dari `.env` dan tempel langsung ke **Authorized redirect URIs** di Google Console.

### 2. Error `403: access_denied` / Aplikasi Belum Diterbitkan
- **Penyebab**: OAuth Consent Screen masih dalam status **Testing**, sehingga hanya *Test Users* yang bisa login.
- **Solusi**: Masuk ke **OAuth consent screen** di Google Console dan klik **PUBLISH APP**.

### 3. Akun Admin Berubah Jadi Pelapor Saat Login Google Pertama Kali
- **Penyebab**: Email Google akun admin tersebut belum pernah diinput di database sebelum login.
- **Solusi**: Daftarkan email Google admin di seeder `AdminUserSeeder.php` atau ubah role user terkait via database:
  ```bash
  php artisan tinker --execute="App\Models\User::where('email', 'email_admin@kominfo.go.id')->update(['role' => 'admin']);"
  ```

