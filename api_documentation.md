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

#### Update Status Laporan
```
PUT /api/admin/reports/{id}/status
Authorization: Bearer <token>
```
| Field | Type | Required |
|---|---|---|
| `status` | string | Ya (`pending` / `proses` / `disetujui`) |

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
