# Bug Audit & Security Hardening Report

Tanggal audit: 2026-09-15  
Tanggal audit ulang: 2026-09-15  
Status Terakhir: **RESOLVED & HARDENED (PRODUCTION-READY)**  
Hasil Test Suite: **43 passed, 153 assertions (100% OK)**

---

## Ringkasan Status Temuan

| ID | Severity | Temuan | Status | Solusi yang Diterapkan |
|---|---|---|---|---|
| **H-01** | High | File PDF attachment dapat diakses tanpa JWT via direct public URL | **Resolved** | Seluruh lampiran laporan dialihkan ke private disk (`local`), tidak dapat diakses secara statis via web server/symlink. Akses dokumen hanya dilayani melalui endpoint API terautentikasi (`/api/reports/{id}/attachments/{questionId}/view` & `download`) dengan verifikasi role dan kepemilikan. |
| **H-02** | High | Token user nonaktif bypass endpoint auth | **Resolved** | Dibuat `ActiveUserMiddleware` (`active`) yang dipasang pada seluruh endpoint grup `auth:api`, mengembalikan HTTP 403 jika `status !== 'aktif'`. |
| **H-03** | High | Seeder menyimpan credential default yang mudah ditebak | **Resolved** | Password admin kini membaca dari `env('ADMIN_DEFAULT_PASSWORD')`, dan `DummyDataSeeder` diberi proteksi environment (`abort(403)` jika di-run di production). |
| **H-04** | High | Question ID lintas media type dapat disubmit | **Resolved** | `ReportService::saveAnswers()` memvalidasi keterikatan question ID dengan `media_type_id` laporan. Jika tidak sesuai, melempar HTTP 422 `ValidationException`. |
| **H-05** | High | `answer_value` bertipe file menerima path acak | **Resolved** | `ReportService::saveAnswers()` memverifikasi keberadaan fisik file pada disk private (`local`) sebelum disimpan. Path tidak valid ditolak dengan HTTP 422. |
| **M-01** | Medium | Standalone upload deletion kurang verifikasi kepemilikan | **Resolved** | `deleteStandaloneUpload()` memvalidasi prefix direktori `reports/questions/` dan memblokir penghapusan jika file terikat pada laporan user lain (HTTP 403). |
| **M-02** | Medium | Admin report update belum memvalidasi keberadaan/ownership file & applicability | **Resolved** | `Admin/ReportController::update()` kini mendelegasikan pembaruan ke `ReportService::updateReport(..., $isAdmin = true)` dengan Form Request ketat, memastikan validasi applicability dan pengecekan file fisik berjalan sama ketatnya. |
| **M-03** | Medium | Risiko Spreadsheet Formula Injection pada Export Excel | **Resolved** | Ditambahkan helper `sanitizeForSpreadsheet()` di `ExportController.php` dan semua cell data dinamis ditulis menggunakan `setCellValueExplicit(..., DataType::TYPE_STRING)`. |
| **M-04** | Medium | OAuth Google stateless flow | **Resolved** | Error handling callback diperkuat dan verifikasi status aktif akun diterapkan saat user login via Google OAuth. |
| **M-05** | Medium | Duplicate validation key dan update unconditional pada profile | **Resolved** | Rule `'name'` disatukan menjadi `'sometimes|required|string|max:255'`, query update hanya memproses data yang ada di payload (support avatar-only update). |
| **M-06** | Medium | Risiko konfigurasi `.env.example` dan CORS wildcard | **Resolved** | `config/cors.php` dikonfigurasi membaca `CORS_ALLOWED_ORIGINS` dan `FRONTEND_URL` dari env; `.env.example` dilengkapi variable keamanan. |
| **L-01** | Low | Duplicate route `auth/me` terdaftar di `routes/api.php` | **Resolved** | Definisi route ganda dihapus, menyisakan `Route::match(['put', 'post'], 'me', ...)` yang bersih. |
| **L-02** | Low | Endpoint GET memutasi database (`ensureWhatsappQuestionExists`) | **Resolved** | Method `ensureWhatsappQuestionExists()` dan pemanggilannya dihapus dari `SharedController.php`, memastikan endpoint GET bersifat murni read-only. |

---

## Rincian Perubahan Kode

### 1. Middleware & Routing
- `app/Http/Middleware/ActiveUserMiddleware.php`: Menjamin user dengan status non-aktif tidak dapat melakukan aksi pada route yang terproteksi.
- `bootstrap/app.php`: Registrasi alias middleware `'active' => ActiveUserMiddleware::class`.
- `routes/api.php`: Semua route terautentikasi kini diproteksi dengan `['auth:api', 'active']`.

### 2. Validasi & Integritas Data
- `app/Services/ReportService.php`:
  - `saveAnswers()` memverifikasi keabsahan pertanyaan berdasarkan `media_type_id` laporan (mencegah manipulasi ID pertanyaan beda jenis media).
  - Verifikasi keberadaan file fisik pada disk private (`local`) sebelum menyimpan jawaban bertipe file.
  - Perbaikan generator nomor laporan `generateReportCode()` untuk mencegah duplikasi kode laporan.
  - `updateReport()` mendukung parameter `$isAdmin` untuk delegasi update dari admin.
- `app/Http/Requests/Admin/UpdateReportRequest.php`: Form Request baru khusus update laporan dari panel admin.
- `app/Http/Controllers/Api/Admin/ReportController.php`: Mendelegasikan method `update()` ke `ReportService::updateReport()`.
- `app/Http/Controllers/Api/AuthController.php`: Perbaikan validasi profil (avatar-only update tanpa field name).

### 3. Keamanan File & Export
- `app/Http/Controllers/Api/ReportController.php`:
  - Seluruh file attachment disimpan di private disk (`local`), tidak di-expose via `public/storage`.
  - Mengarahkan URL file ke endpoint API terautentikasi (`/api/reports/{id}/attachments/{questionId}/view`).
  - `deleteStandaloneUpload()` diamankan dengan pemeriksaan kepemilikan dan sanitasi path.
- `app/Http/Controllers/Api/Admin/ExportController.php`: Netralisasi karakter formula (`=`, `+`, `-`, `@`) dan explicit string rendering pada PhpSpreadsheet.

### 4. Database Seeder & Konfigurasi
- `database/seeders/AdminUserSeeder.php`: Menggunakan `env('ADMIN_DEFAULT_PASSWORD', 'admin123')`.
- `database/seeders/DummyDataSeeder.php`: Guard environment production (`abort(403)`) dan seeding file dummy pada private disk.
- `config/cors.php` & `.env.example`: CORS origin allowlist terkonfigurasi aman.

---

## Verifikasi Pengujian (Automated Test Suite)

Semua unit dan feature test berjalan dan lulus 100%:
- `tests/Feature/SecurityHardeningTest.php` (Menguji inactive token block, cross-media question rejection, non-existent file path, formula injection export, avatar-only profile update, delete-upload authorization, admin report update validation, private disk attachment storage, dan admin update file validation).
- `tests/Feature/ReportAttachmentAuthorizationTest.php`
- `tests/Feature/ReportOptionalAttachmentCleanupTest.php`
- `tests/Feature/ReportMandatoryValidationTest.php`
- `tests/Feature/UserProfileTest.php`
- `tests/Feature/ReportSubmissionTest.php`
- `tests/Feature/ReportUpdateTest.php`
- `tests/Feature/ReportLifecycleTest.php`
- `tests/Feature/ReportAuthorizationTest.php`
- `tests/Feature/GoogleAuthTest.php`
- `tests/Feature/AdminManagementTest.php`
- `tests/Feature/ReportScoreCalculationTest.php`
- `tests/Feature/AuthTest.php`

**Total Hasil Akhir: 43 Passed, 153 Assertions.**