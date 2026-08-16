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
  { "id": 1, "name": "Online", "created_at": "...", "updated_at": "..." },
  ...
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

**Semua endpoint memerlukan role `pelapor`.**

#### List Laporan Saya
```
GET /api/reports
Authorization: Bearer <token>
```

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

**Request Body (contoh lengkap):**
```json
{
  "media_type_id": 1,
  "link_url": "https://mediabanjar.com",
  "submit": true,
  "answers": [
    { "question_id": 1, "answer_value": "Media Banjar News", "answer_type": "text" },
    { "question_id": 2, "answer_value": "Ya", "answer_type": "text" },
    { "question_id": 3, "answer_value": "reports/questions/3/abc123.pdf", "answer_type": "file" },
    { "question_id": 4, "answer_value": "Ada UKW Utama", "answer_type": "text" },
    { "question_id": 5, "answer_value": "reports/questions/5/def456.pdf", "answer_type": "file" },
    { "question_id": 6, "answer_value": "Ada + UKW", "answer_type": "text" },
    { "question_id": 7, "answer_value": "reports/questions/7/ghi789.pdf", "answer_type": "file" },
    { "question_id": 8, "answer_value": ">4 tahun", "answer_type": "text" },
    { "question_id": 9, "answer_value": "reports/questions/9/jkl012.pdf", "answer_type": "file" },
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

> **Penjelasan `answer_type`:**
> - `text` → jawaban teks biasa (nama media, pilihan Ya/Tidak, kategori, dll)
> - `file` → path file hasil upload (dari endpoint `POST /api/reports/{reportId}/upload/{questionId}`)
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
    "user_id": 2,
    "media_type_id": 1,
    "status": "pending",
    "total_score": 0,
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
Submits the report and calculates scores.

#### Upload File per Pertanyaan
```
POST /api/reports/{reportId}/upload/{questionId}
Authorization: Bearer <token>
Content-Type: multipart/form-data
```
| Field | Type | Required |
|---|---|---|
| `file` | file | Ya (PDF, max 5MB) |

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
| `search` | string | Cari nama/email pelapor |
| `page` | int | Pagination |

#### Detail Laporan (Admin)
```
GET /api/admin/reports/{id}
Authorization: Bearer <token>
```
**Response:**
```json
{
  "report": {
    "id": 1,
    "user": { ... },
    "media_type": { ... },
    "answers": [
      {
        "question": { "scoring_rules": [...] },
        "answer_value": "Ya",
        "answer_type": "text",
        "score_earned": 25
      }
    ],
    "status": "pending",
    "total_score": 52
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
4. **Upload file**: Gunakan endpoint terpisah `POST /api/reports/{reportId}/upload/{questionId}` dengan `multipart/form-data`. File hanya PDF maksimal 5MB.
5. **Draft**: Buat laporan tanpa `submit: true` untuk menyimpan sebagai draft. Panggil `POST /api/reports/{id}/submit` saat siap.
6. **Edit**: Laporan hanya bisa diedit jika status masih `pending`. Begitu admin mengubah ke `proses` atau `disetujui`, laporan terkunci.
7. **Scoring**: Skor dihitung otomatis saat submit dan saat admin verifikasi. Total score menentukan kategori (1/2/3/tidak memenuhi).
8. **Tetap Masuk (`remember_me`)**: Kirim `remember_me: true` saat login agar token berlaku 30 hari, bukan 1 jam.
9. **Alur upload file**: (1) Buat laporan dulu (draft), (2) upload file per pertanyaan ke `POST /api/reports/{id}/upload/{questionId}`, (3) update laporan dengan `answer_value` = path file yang dikembalikan, (4) submit laporan.
