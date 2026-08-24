# API Documentation — Laporan Media Kominfo

Base URL: `http://localhost/api`

---

## Autentikasi

Semua endpoint yang memerlukan autentikasi menggunakan **Bearer Token** (JWT).
Header: `Authorization: Bearer <token>`

---

## CAPTCHA Flow

1. Panggil `GET /api/captcha` → dapatkan `captcha_img` (base64) + `captcha_key`
2. Tampilkan gambar captcha ke user
3. Kirim `captcha` (jawaban user) + `captcha_key` saat register/login/forgot-password

---

### 1. Auth

#### Registrasi
```
POST /api/auth/register
```
| Field | Type | Required | Keterangan |
|---|---|---|---|
| `name` | string | Ya | Nama lengkap |
| `email` | string | Ya | Email (unik) |
| `password` | string | Ya | Minimal 8 karakter |
| `password_confirmation` | string | Ya | Konfirmasi password |
| `captcha` | string | Ya | Jawaban captcha |
| `captcha_key` | string | Ya | Key dari GET /api/captcha |

**Request Body:**
```json
{
  "name": "Budi Santoso",
  "email": "budi@mediabanjar.com",
  "password": "password123",
  "password_confirmation": "password123",
  "captcha": "aB3dEf",
  "captcha_key": "hash_dari_get_captcha"
}
```

**Response 201:**
```json
{
  "message": "Registrasi berhasil.",
  "user": { ... },
  "access_token": "eyJ...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

#### Login
```
POST /api/auth/login
```
| Field | Type | Required |
|---|---|---|
| `email` | string | Ya |
| `password` | string | Ya |
| `captcha` | string | Ya |
| `captcha_key` | string | Ya |
| `remember_me` | boolean | Tidak (default `false`) |

**Request Body:**
```json
{
  "email": "budi@mediabanjar.com",
  "password": "password123",
  "captcha": "aB3dEf",
  "captcha_key": "hash_dari_get_captcha",
  "remember_me": true
}
```

> **Catatan `remember_me`:** jika `true`, token berlaku 30 hari (`expires_in: 2592000`). Jika `false`, token berlaku 1 jam (`expires_in: 3600`).

**Response 200:**
```json
{
  "message": "Login berhasil.",
  "user": { ... },
  "access_token": "eyJ...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

#### Logout
```
POST /api/auth/logout
Authorization: Bearer <token>
```

#### Refresh Token
```
POST /api/auth/refresh
Authorization: Bearer <token>
```

#### Profile (Me)
```
GET /api/auth/me
Authorization: Bearer <token>
```

#### Update Profile
```
PUT /api/auth/me
Authorization: Bearer <token>
```
| Field | Type | Required |
|---|---|---|
| `name` | string | Ya |

**Request Body: **
```json
{
  "name": "Budi Santoso"
}
```

**Response 200:**
```json
{
  "message": "Profil berhasil diperbarui.",
  "user": { ... }
}
```

#### Change Password
```
PUT /api/auth/me/password
Authorization: Bearer <token>
```
| Field | Type | Required |
|---|---|---|
| `current_password` | string | Ya |
| `password` | string | Ya (minimal 8 karakter) |
| `password_confirmation` | string | Ya |

**Request Body:**
```json
{
  "current_password": "passwordlama123",
  "password": "passwordbaru123",
  "password_confirmation": "passwordbaru123"
}
```

**Response 200:**
```json
{
  "message": "Password berhasil diperbarui."
}
```

**Response 422 jika password saat ini salah:**
```json
{
  "message": "Password saat ini tidak sesuai."
}
```

#### Forgot Password
```
POST /api/auth/forgot-password
```
| Field | Type | Required |
|---|---|---|
| `email` | string | Ya |
| `captcha` | string | Ya |
| `captcha_key` | string | Ya |

**Request Body:**
```json
{
  "email": "budi@mediabanjar.com",
  "captcha": "aB3dEf",
  "captcha_key": "hash_dari_get_captcha"
}
```

**Response 200 (selalu sama, demi keamanan):**
```json
{
  "message": "Jika email terdaftar, tautan reset password akan dikirim."
}
```

> **Penting untuk frontend:**
> - Backend **selalu** membalas `200` dengan pesan umum (tidak membocorkan apakah email terdaftar atau tidak).
> - Setelah request sukses, arahkan user ke halaman "Cek email Anda".

#### Reset Password
```
POST /api/auth/reset-password
```
| Field | Type | Required |
|---|---|---|
| `token` | string | Ya |
| `email` | string | Ya |
| `password` | string | Ya |
| `password_confirmation` | string | Ya |

**Request Body:**
```json
{
  "token": "token_dari_email_reset",
  "email": "budi@mediabanjar.com",
  "password": "passwordbaru123",
  "password_confirmation": "passwordbaru123"
}
```

**Response 200:**
```json
{
  "message": "Password berhasil direset."
}
```

---

### Alur Lengkap Forgot Password

```
1. User klik "Lupa Password" di frontend
   ↓
2. Frontend panggil GET /api/captcha → tampilkan gambar captcha
   ↓
3. User isi email + jawaban captcha
   ↓
4. Frontend panggil POST /api/auth/forgot-password
   { email, captcha, captcha_key }
   ↓
5. Backend kirim email berisi link reset ke email user
   Link format: {APP_URL}/password/reset/{token}?email={email}
   ↓
6. User klik link tersebut
   ↓
7. Frontend tangkap `token` dan `email` dari URL,
   tampilkan form "password baru"
   ↓
8. Frontend panggil POST /api/auth/reset-password
   { token, email, password, password_confirmation }
   ↓
9. Backend verifikasi token & simpan password baru
```

**Catatan penting:**
- **Token berlaku 60 menit** (dari `config/auth.php` → `expire: 60`).
- **Token hanya bisa dipakai sekali.** Setelah reset sukses, token tidak valid lagi.
- Saat ini link reset mengarah ke **backend** (`APP_URL/password/reset/...`). Saat integrasi frontend, kami akan ubah agar mengarah ke halaman frontend (misal `FRONTEND_URL/reset-password`). Untuk sementara, frontend tinggal baca `token` & `email` dari query string link tersebut.

#### Google Login (Redirect URL)
```
GET /api/auth/google
```
Mengembalikan URL redirect untuk Google OAuth.

#### Google Callback
```
GET /api/auth/google/callback?code=...
```
Handle callback dari Google OAuth.

---

### 2. Shared / Public

#### List Jenis Media
```
GET /api/media-types
```
**Response:**
```json
[
  { "id": 1, "name": "Online", "code": "ON", "created_at": "...", "updated_at": "..." },
  { "id": 2, "name": "Cetak", "code": "CT", "created_at": "...", "updated_at": "..." },
  { "id": 3, "name": "Elektronik", "code": "EL", "created_at": "...", "updated_at": "..." },
  { "id": 4, "name": "Televisi", "code": "TV", "created_at": "...", "updated_at": "..." },
  { "id": 5, "name": "Radio", "code": "RD", "created_at": "...", "updated_at": "..." }
]
```

#### List Pertanyaan Evaluasi (dengan Scoring Rules)
```
GET /api/evaluation-questions
```
**Response:**
```json
[
  {
    "id": 1,
    "category": "identitas",
    "question_text": "Nama Media",
    "weight": 0,
    "is_mandatory": true,
    "scoring_rules": []
  },
  {
    "id": 2,
    "category": "verifikasi",
    "question_text": "Media terverifikasi...",
    "weight": 25,
    "is_mandatory": true,
    "scoring_rules": [
      { "id": 1, "answer_option": "Ya", "score": 25 },
      { "id": 2, "answer_option": "Tidak", "score": 0 }
    ]
  },
  ...
]
```

#### List Pertanyaan Per Jenis Media
```
GET /api/evaluation-questions/{mediaTypeId}
```
**Parameter:** `mediaTypeId` = ID jenis media (1=Online, 2=Cetak, 3=Elektronik, 4=Televisi, 5=Radio)

Mengembalikan pertanyaan universal + pertanyaan khusus jenis media tersebut. Gunakan endpoint ini setelah user memilih jenis media.

**Response** sama seperti `GET /api/evaluation-questions`.

---

### 3. Pelapor (Reports)

Bagian ini dibagi supaya frontend tidak tertukar antara endpoint shared, pelapor, dan admin.

#### Peta Akses Cepat

| Endpoint | Admin | Pelapor | Catatan |
|---|---|---|---|
| `GET /api/reports` | Ya | Ya | Admin melihat semua laporan, pelapor hanya laporannya sendiri |
| `GET /api/reports/{id}` | Ya | Ya | Admin bisa buka laporan apa pun, pelapor hanya laporan miliknya |
| `POST /api/reports` | Tidak | Ya | Buat draft / laporan baru |
| `PUT /api/reports/{id}` | Tidak | Ya | Edit laporan milik sendiri saat masih `pending` |
| `DELETE /api/reports/{id}` | Tidak | Ya | Hapus laporan milik sendiri saat masih `pending` |
| `POST /api/reports/{id}/submit` | Tidak | Ya | Finalisasi laporan |
| `POST /api/reports/{reportId}/upload/{questionId}` | Tidak | Ya | Upload file lampiran per pertanyaan |
| `GET /api/reports/{reportId}/attachments/{questionId}/view` | Ya | Ya | Preview PDF lampiran |
| `GET /api/reports/{reportId}/attachments/{questionId}/download` | Ya | Ya | Download PDF lampiran |

> Gunakan endpoint admin hanya di panel admin. Gunakan endpoint pelapor hanya di alur pelapor. Endpoint shared boleh dipakai keduanya hanya bila tabel di atas memang mengizinkan.

#### List Laporan Saya
```
GET /api/reports
Authorization: Bearer <token>
```
Bisa diakses oleh **pelapor** dan **admin**. Admin melihat semua laporan, pelapor hanya melihat laporan miliknya.

#### Detail Laporan
```
GET /api/reports/{id}
Authorization: Bearer <token>
```
Bisa diakses oleh **pelapor** dan **admin**.

> **Jawaban bertipe `file`** akan menyertakan field `file_url` untuk mengakses/menampilkan file yang di-upload:
> ```json
> {
>   "id": 10,
>   "question_id": 3,
>   "answer_value": "reports/questions/3/abc123.pdf",
>   "answer_type": "file",
>   "file_url": "/storage/reports/questions/3/abc123.pdf"
> }
> ```
> Gabungkan `file_url` dengan base URL untuk membuka file (contoh: `http://localhost:8000` + `/storage/...`).

#### View Lampiran File
```
GET /api/reports/{reportId}/attachments/{questionId}/view
Authorization: Bearer <token>
```
Bisa diakses oleh **admin** dan **pelapor pemilik laporan**.

Endpoint ini menampilkan file PDF langsung di browser dengan `Content-Disposition: inline`.

#### Download Lampiran File
```
GET /api/reports/{reportId}/attachments/{questionId}/download
Authorization: Bearer <token>
```
Bisa diakses oleh **admin** dan **pelapor pemilik laporan**.

Endpoint ini memaksa unduhan file PDF lampiran.

> **Catatan frontend:**
> - Gunakan endpoint `view` untuk preview di halaman detail.
> - Gunakan endpoint `download` untuk tombol unduh.
> - Jangan langsung membuka `answer_value` sebagai URL final.
> - Format file yang diizinkan backend saat upload tetap PDF.

#### Buat Laporan Baru
```
POST /api/reports
Authorization: Bearer <token>
```
| Field | Type | Required | Keterangan |
|---|---|---|---|
| `media_type_id` | int | Ya | ID jenis media |
| `answers` | array | Ya | Array jawaban |
| `answers[].question_id` | int | Ya | ID pertanyaan |
| `answers[].answer_value` | string | Ya | Isi jawaban |
| `answers[].answer_type` | string | Ya | `text`, `file`, atau `url` |
| `link_url` | string | Tidak | Link URL laporan |
| `submit` | boolean | Tidak | Jika `true`, langsung submit |

**Request Body (contoh — membuat DRAFT):**
```json
{
  "media_type_id": 1,
  "link_url": "https://mediabanjar.com",
  "submit": false,
  "answers": [
    { "question_id": 1, "answer_value": "Media Banjar News", "answer_type": "text" },
    { "question_id": 2, "answer_value": "Ya", "answer_type": "text" },
    { "question_id": 4, "answer_value": "Ada UKW Utama", "answer_type": "text" },
    { "question_id": 6, "answer_value": "Ada + UKW", "answer_type": "text" },
    { "question_id": 8, "answer_value": ">4 tahun", "answer_type": "text" },
    { "question_id": 10, "answer_value": "Aktif", "answer_type": "text" },
    { "question_id": 11, "answer_value": "https://mediabanjar.com/berita/umum", "answer_type": "url" },
    { "question_id": 12, "answer_value": "Aktif", "answer_type": "text" },
    { "question_id": 13, "answer_value": "https://mediabanjar.com/kab-banjar", "answer_type": "url" },
    { "question_id": 14, "answer_value": ">20.000", "answer_type": "text" },
    { "question_id": 15, "answer_value": "https://instagram.com/mediabanjar", "answer_type": "url" },
    { "question_id": 16, "answer_value": "Ada", "answer_type": "text" },
    { "question_id": 17, "answer_value": "https://mediabanjar.com/martapura", "answer_type": "url" }
  ]
}
```

> **⚠️ PENTING — Pertanyaan bertipe `file` TIDAK dikirim di request ini.**
> Pertanyaan yang butuh upload (mis. Q3, Q5, Q7, Q9) di-**OMIT** (tidak dimasukkan) dulu.
> File di-upload **terpisah setelah laporan dibuat** (lihat bagian **Alur Upload File**).
>
> - `submit: false` (atau tidak dikirim) → laporan menjadi **DRAFT** (`submitted_at` masih `null`, skor `0`).
> - `submit: true` → langsung submit (hanya jika semua jawaban sudah lengkap, termasuk file yang sudah di-upload).
> - `answer_type` **wajib** dikirim untuk setiap jawaban (`text` / `file` / `url`) — backend **tidak** mendeteksi otomatis.

> **Penjelasan `answer_type`:**
> - `text` → jawaban teks biasa (nama media, pilihan Ya/Tidak, kategori, dll)
> - `file` → **path** file hasil upload (bukan binary). Didapat dari response endpoint `POST /api/reports/{reportId}/upload/{questionId}`
> - `url` → alamat URL lengkap (harus valid URL)
>
> **Nilai `answer_value` yang valid untuk pertanyaan bernilai (scoring):**
> | Pertanyaan | Nilai Valid |
> |---|---|
> | Terverifikasi Dewan Pers | `Ya` / `Tidak` |
> | Pimpinan redaksi (UKW) | `Ada UKW Utama` / `Tidak UKW Utama` |
> | Wartawan/Biro Banjar | `Ada + UKW` / `Ada tanpa UKW` / `Tidak ada` |
> | Usia media | `>4 tahun` / `2-4 tahun` / `<2 tahun` |
> | Berita umum / Banjar | `Aktif` / `Tidak` |
> | Jumlah pengikut | `>20.000` / `5.000 - 20.000` / `<5.000` |
> | Halaman khusus/tayangan/siaran | `Ada` / `Tidak` |
>
> Nilai di atas harus **persis** (case-sensitive) agar skor terhitung benar.

**Response 201:**
```json
{
  "message": "Laporan berhasil dibuat.",
  "report": {
    "id": 1,
    "report_code": "ON-001",
    "user_id": 2,
    "media_type_id": 1,
    "status": "pending",
    "total_score": 0,
    "category": "Tidak memenuhi kategori",
    "answers": [
      {
        "id": 1,
        "question_id": 2,
        "answer_value": "Ya",
        "answer_type": "text",
        "score_earned": 25,
        "question": { ... }
      }
    ],
    "media_type": { ... }
  }
}
```

> **`report_code`** dibuat otomatis saat laporan dibuat, format `{KODE_MEDIA}-{nomor urut 3 digit}`.
> Contoh: `ON-001` (Online urutan 1), `CT-001` (Cetak urutan 1), `TV-005` (Televisi urutan 5).

#### Detail Laporan
```
GET /api/reports/{id}
Authorization: Bearer <token>
```

#### Edit Laporan (hanya status `pending`)
```
PUT /api/reports/{id}
Authorization: Bearer <token>
```
Body sama seperti `POST` (semua field opsional).

**Request Body (contoh):**
```json
{
  "media_type_id": 2,
  "link_url": "https://mediabanjar.com/baru",
  "answers": [
    { "question_id": 2, "answer_value": "Tidak", "answer_type": "text" },
    { "question_id": 14, "answer_value": "5.000 - 20.000", "answer_type": "text" }
  ]
}
```

> Hanya `answers` yang dikirim yang akan diperbarui. Jawaban lain tetap seperti sebelumnya.

#### Hapus Laporan (hanya status `pending`)
```
DELETE /api/reports/{id}
Authorization: Bearer <token>
```

#### Submit Laporan
```
POST /api/reports/{id}/submit
Authorization: Bearer <token>
```
Mengubah status laporan menjadi terkirim: set `submitted_at` + menghitung skor & kategori.

> **Catatan:** endpoint ini **POST**, bukan PUT. Backend **tidak otomatis** submit setelah upload file — frontend harus memanggil endpoint ini secara eksplisit setelah semua file ter-upload.

#### Upload File per Pertanyaan
```
POST /api/reports/{reportId}/upload/{questionId}
Authorization: Bearer <token>
Content-Type: multipart/form-data
```
| Field | Type | Required |
|---|---|---|
| `file` | file | Ya (PDF, max 5MB) |

**Response 200:**
```json
{
  "message": "File berhasil diupload.",
  "answer": {
    "id": 10,
    "report_id": 1,
    "question_id": 3,
    "answer_value": "reports/questions/3/abc123.pdf",
    "answer_type": "file",
    "score_earned": 0
  },
  "url": "/storage/reports/questions/3/abc123.pdf"
}
```

> Gunakan `answer_value` dari response ini jika ingin mengirim data laporan lengkap (misal saat `PUT /api/reports/{id}` atau `POST /api/reports` dengan `submit: true`).

#### View Lampiran File
```
GET /api/reports/{reportId}/attachments/{questionId}/view
Authorization: Bearer <token>
```
Bisa diakses oleh **admin** dan **pelapor pemilik laporan**.

Endpoint ini menampilkan file PDF langsung di browser dengan `Content-Disposition: inline`.

#### Download Lampiran File
```
GET /api/reports/{reportId}/attachments/{questionId}/download
Authorization: Bearer <token>
```
Bisa diakses oleh **admin** dan **pelapor pemilik laporan**.

Endpoint ini memaksa unduhan file PDF lampiran.

> **Catatan frontend:**
> - Gunakan endpoint `view` untuk preview di halaman detail.
> - Gunakan endpoint `download` untuk tombol unduh.
> - Jangan langsung membuka `answer_value` sebagai URL final.
> - Format file yang diizinkan backend saat upload tetap PDF.

---

### Alur Upload File (Langkah demi Langkah) ⭐

Backend memisahkan **jawaban teks/URL** dengan **upload file**. Alurnya **bertahap** (bukan sekali kirim):

```
1. BUAT DRAFT
   POST /api/reports
   Body: { media_type_id, answers (HANYA text & url), submit: false }
   → Response: { report: { id: 1, report_code: "ON-001", status: "pending" } }
   Catatan: pertanyaan bertipe file TIDAK dikirim di sini.

2. UPLOAD FILE (looping per pertanyaan yang butuh file)
   POST /api/reports/{reportId}/upload/{questionId}
   Content-Type: multipart/form-data
   Body: { file: <binary pdf> }
   → Response: { answer: { answer_value: "reports/questions/3/abc123.pdf", ... } }
   Lakukan ini untuk Q3, Q5, Q7, Q9 (atau pertanyaan file lainnya).
   Pertanyaan lain yang tidak perlu file → skip.

3. SUBMIT
   POST /api/reports/{reportId}/submit
   → Set submitted_at + hitung skor & kategori
```

**Ringkasan untuk frontend:**
| Langkah | Endpoint | Isi |
|---|---|---|
| 1 | `POST /api/reports` | `submit: false`, jawaban text & url saja |
| 2 | `POST /api/reports/{id}/upload/{questionId}` | File PDF per pertanyaan (multipart) |
| 3 | `POST /api/reports/{id}/submit` | Finalisasi + hitung skor |

> **Kenapa tidak kirim file langsung di `POST /api/reports`?**
> Karena upload membutuhkan `reportId`, dan `reportId` baru ada setelah laporan dibuat. Maka file harus di-upload setelah draft dibuat. Ini **bukan** bug — memang desainnya bertahap.

---

### 4. Admin

**Semua endpoint memerlukan role `admin`.**

#### Dashboard
```
GET /api/admin/dashboard
Authorization: Bearer <token>
```
**Response:**
```json
{
  "total_users": 15,
  "total_reports": 42,
  "pending_reports": 10,
  "processing_reports": 5,
  "approved_reports": 27
}
```

#### List Semua User Pelapor
```
GET /api/admin/users
Authorization: Bearer <token>
```

#### Detail User
```
GET /api/admin/users/{id}
Authorization: Bearer <token>
```

#### Update Status User
```
PUT /api/admin/users/{id}/status
Authorization: Bearer <token>
```
| Field | Type | Required |
|---|---|---|
| `status` | string | Ya (`aktif` / `non-aktif`) |

**Request Body:**
```json
{
  "status": "non-aktif"
}
```

#### Hapus Akun Pelapor
```
DELETE /api/admin/users/{id}
Authorization: Bearer <token>
```

#### List Semua Laporan
```
GET /api/admin/reports?status=pending&media_type_id=1&search=kata
Authorization: Bearer <token>
```
| Query Param | Type | Keterangan |
|---|---|---|
| `status` | string | Filter: `pending`, `proses`, `disetujui` |
| `media_type_id` | int | Filter jenis media |
| `search` | string | Cari nama/email/ID/kode laporan |
| `page` | int | Pagination |
| `per_page` | int | Jumlah per halaman (max 100) |
| `date_from` | date | Filter dari tanggal (YYYY-MM-DD) |
| `date_to` | date | Filter sampai tanggal (YYYY-MM-DD) |
| `score_min` | int | Skor minimum |
| `score_max` | int | Skor maksimum |
| `sort_by` | string | `created_at`, `total_score`, `status`, `report_code` |
| `sort_dir` | string | `asc` / `desc` |

**Response (format pagination, tiap item berisi):**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "report_code": "ON-001",
      "media_name": "Media Banjar News",
      "user_name": "Budi Santoso",
      "user_email": "budi@mediabanjar.com",
      "media_type": "Online",
      "submitted_at": "2026-08-11 10:30:00",
      "total_score": 52,
      "category": "Kategori 2",
      "status": "pending"
    }
  ],
  "total": 1,
  "per_page": 20,
  "last_page": 1
}
```

> **Penjelasan field:**
> - `report_code` → kode laporan (misal `ON-001`, `CT-001`)
> - `media_name` → nama media (diambil dari jawaban pertanyaan "Nama Media")
> - `user_name` / `user_email` → nama & email pelapor
> - `media_type` → jenis media (Online/Cetak/Elektronik/Televisi/Radio)
> - `submitted_at` → tanggal submit (null jika masih draft)
> - `total_score` → penilaian/skor total
> - `category` → kategori (dihitung otomatis: Kategori 1/2/3/Tidak memenuhi)
> - `status` → `pending` / `proses` / `disetujui`

#### Detail Laporan (Admin)
```
GET /api/admin/reports/{id}
Authorization: Bearer <token>
```
Endpoint ini khusus panel admin dan berbeda dari `GET /api/reports/{id}`. Gunakan endpoint ini jika frontend berada di konteks admin.
**Response:**
```json
{
  "report": {
    "id": 1,
    "report_code": "ON-001",
    "user": { ... },
    "media_type": { ... },
    "answers": [
      {
        "question": { "scoring_rules": [...] },
        "answer_value": "Ya",
        "answer_type": "text",
        "score_earned": 25
      },
      {
        "question": { ... },
        "answer_value": "reports/questions/3/abc123.pdf",
        "answer_type": "file",
        "file_url": "/storage/reports/questions/3/abc123.pdf",
        "score_earned": 0
      }
    ],
    "status": "pending",
    "total_score": 52,
    "category": "Kategori 2"
  },
  "category": "Kategori 2"
}
```

#### Edit Laporan (Admin)
```
PUT /api/admin/reports/{id}
Authorization: Bearer <token>
```
Body bisa berisi `answers` array dan/atau `media_type_id`.

**Request Body (contoh):**
```json
{
  "media_type_id": 1,
  "answers": [
    { "question_id": 2, "answer_value": "Ya", "answer_type": "text" },
    { "question_id": 8, "answer_value": ">4 tahun", "answer_type": "text" }
  ]
}
```

#### Update Status Laporan
```
PUT /api/admin/reports/{id}/status
Authorization: Bearer <token>
```
| Field | Type | Required |
|---|---|---|
| `status` | string | Ya (`pending` / `proses` / `disetujui`) |

**Request Body:**
```json
{
  "status": "disetujui"
}
```

#### Export PDF Single Laporan
```
GET /api/admin/reports/{id}/pdf
Authorization: Bearer <token>
```
Returns PDF file download.

#### Export PDF Rekapitulasi
```
GET /api/admin/export-pdf
Authorization: Bearer <token>
```
Returns PDF recap of all approved reports.

---

## Error Response Format

Semua endpoint mengembalikan format error yang konsisten:

```json
{
  "message": "Deskripsi error",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### HTTP Status Codes
| Code | Keterangan |
|---|---|
| 200 | Sukses |
| 201 | Data berhasil dibuat |
| 400 | Bad request / validasi gagal |
| 401 | Unauthenticated |
| 403 | Forbidden (role tidak sesuai / akun non-aktif) |
| 404 | Data tidak ditemukan |
| 422 | Validasi gagal |
| 500 | Server error |

---

## Catatan untuk Frontend

1. **Token JWT** harus disimpan di client (localStorage/cookie) dan dikirim via header `Authorization: Bearer <token>`.
2. **Refresh token** sebelum expired (default 1 jam) dengan memanggil `POST /api/auth/refresh`.
3. **Login Google**: Panggil `GET /api/auth/google` untuk mendapatkan URL redirect, lalu handle callback di frontend dengan mengambil token dari response.
4. **Upload file**: File di-upload **terpisah** (bukan di body `POST /api/reports`). Lihat bagian **Alur Upload File**. File hanya PDF maksimal 5MB.
5. **Draft**: Buat laporan dengan `submit: false` (atau tanpa field submit) untuk menyimpan sebagai draft. Panggil `POST /api/reports/{id}/submit` saat semua jawaban & file sudah lengkap.
6. **Edit**: Laporan hanya bisa diedit jika status masih `pending`. Begitu admin mengubah ke `proses` atau `disetujui`, laporan terkunci.
7. **Scoring**: Skor dihitung otomatis saat submit dan saat admin verifikasi. Total score menentukan kategori (1/2/3/tidak memenuhi).
8. **Tetap Masuk (`remember_me`)**: Kirim `remember_me: true` saat login agar token berlaku 30 hari, bukan 1 jam.
9. **Alur upload file yang benar**: (1) `POST /api/reports` dengan `submit: false` & jawaban text/url saja → dapat `report.id`, (2) upload tiap file ke `POST /api/reports/{id}/upload/{questionId}` (multipart), (3) `POST /api/reports/{id}/submit` untuk finalisasi. Jangan mengirim file binary di dalam array `answers`.
10. **Jangan tertukar konteks route**: jika frontend memakai halaman admin, gunakan endpoint admin. Jika frontend memakai halaman pelapor, gunakan endpoint pelapor. Kalau route yang dipanggil tidak sesuai role, backend akan membalas `403` dengan pesan seperti `Anda tidak memiliki akses.`
11. **Jangan membuka path mentah sebagai route API**: untuk preview lampiran gunakan `GET /api/reports/{reportId}/attachments/{questionId}/view`, untuk download gunakan `.../download`. Jangan menambahkan prefix `/api/storage/` atau menggandakan `/storage` di frontend.
