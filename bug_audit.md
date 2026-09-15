# Bug Audit & Security Hardening Report

Tanggal audit: 2026-09-15  
Tanggal audit ulang: 2026-09-15  
Status Terakhir: **RESOLVED & HARDENED (100% PRODUCTION-READY)**  
Hasil Test Suite: **46 passed, 160 assertions (100% OK)**

---

## Ringkasan Status Temuan

| ID | Severity | Temuan | Status | Solusi yang Diterapkan |
|---|---|---|---|---|
| **H-01** | High | File PDF attachment dapat diakses tanpa JWT via direct public URL | **Resolved** | Seluruh lampiran laporan disimpan di private disk (`local`), tidak dapat diakses secara statis via web server/symlink. Akses dokumen hanya dilayani melalui endpoint API terautentikasi (`/api/reports/{id}/attachments/{questionId}/view` & `download`) dengan verifikasi role dan kepemilikan. |
| **H-02** | High | Token user nonaktif bypass endpoint auth | **Resolved** | Dibuat `ActiveUserMiddleware` (`active`) yang dipasang pada seluruh endpoint grup `auth:api`, mengembalikan HTTP 403 jika `status !== 'aktif'`. |
| **H-03** | High | Seeder menyimpan credential default yang mudah ditebak | **Resolved** | Password admin membaca dari `env('ADMIN_DEFAULT_PASSWORD')`, dan `DummyDataSeeder` diberi proteksi environment (`abort(403)` jika di-run di production). |
| **H-04** | High | Question ID lintas media type dapat disubmit | **Resolved** | `ReportService::saveAnswers()` memvalidasi keterikatan question ID dengan `media_type_id` laporan. Jika tidak sesuai, melempar HTTP 422 `ValidationException`. |
| **H-05** | High | `answer_value` bertipe file menerima path acak tanpa kepemilikan | **Resolved** | Dibuat tabel `temporary_uploads`. `saveAnswers()` memverifikasi bahwa file fisik ada di private storage DAN benar-benar diupload oleh user pemilik laporan untuk pertanyaan tersebut. User tidak dapat mencuri path file user lain. |
| **M-01** | Medium | Standalone upload deletion kurang verifikasi kepemilikan | **Resolved** | `deleteStandaloneUpload()` memverifikasi kepemilikan record di `temporary_uploads` dan memblokir penghapusan jika file diupload oleh user lain (HTTP 403). |
| **M-02** | Medium | Admin report update belum memvalidasi keberadaan/ownership file & applicability | **Resolved** | `Admin/ReportController::update()` mendelegasikan pembaruan ke `ReportService::updateReport(..., $isAdmin = true)` dengan Form Request ketat, memastikan validasi applicability dan pengecekan file fisik berjalan sama ketatnya. |
| **M-03** | Medium | Risiko Spreadsheet Formula Injection pada Export Excel | **Resolved** | Ditambahkan helper `sanitizeForSpreadsheet()` di `ExportController.php` dan semua cell data dinamis ditulis menggunakan `setCellValueExplicit(..., DataType::TYPE_STRING)`. |
| **M-04** | Medium | OAuth Google stateless flow | **Resolved** | Penggunaan `stateless()` didokumentasikan sesuai arsitektur REST API SPA. Error handling callback diperkuat dan verifikasi status aktif akun diterapkan saat user login via Google OAuth. |
| **M-05** | Medium | Duplicate validation key dan update unconditional pada profile | **Resolved** | Rule `'name'` disatukan menjadi `'sometimes|required|string|max:255'`, query update hanya memproses data yang ada di payload (support avatar-only update). |
| **M-06** | Medium | Risiko konfigurasi `.env.example` bernilai development | **Resolved** | `.env.example` diperbarui dengan standar aman production (`APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=info`, `SESSION_ENCRYPT=true`, `CORS_ALLOWED_ORIGINS`) beserta panduan checklist deploy. |
| **M-07** | Medium | Reset & Forgot Password tidak memblokir akun non-aktif | **Resolved** | `forgotPassword()` tidak akan mengirimkan email tautan reset untuk akun non-aktif, dan `resetPassword()` memblokir eksekusi token reset untuk akun non-aktif dengan HTTP 403. |
| **L-01** | Low | Duplicate route `auth/me` terdaftar di `routes/api.php` | **Resolved** | Definisi route ganda dihapus, menyisakan `Route::match(['put', 'post'], 'me', ...)` yang bersih. |
| **L-02** | Low | Endpoint GET memutasi database (`ensureWhatsappQuestionExists`) | **Resolved** | Method `ensureWhatsappQuestionExists()` dan pemanggilannya dihapus dari `SharedController.php`, memastikan endpoint GET bersifat murni read-only. |

---

## Rincian Perubahan Kode

### 1. File Upload Ownership & Temporary Uploads
- `database/migrations/2026_09_15_000001_create_temporary_uploads_table.php`: Migrasi tabel pelacakan upload sementara (`user_id`, `question_id`, `file_path`, `expires_at`).
- `app/Models/TemporaryUpload.php`: Model Eloquent untuk `temporary_uploads`.
- `app/Services/ReportService.php`:
  - `uploadFile()` mencatat kepemilikan file sementara ke `temporary_uploads`.
  - `saveAnswers()` memverifikasi hak kepemilikan file sebelum menyimpannya ke `report_answers`.
  - Menghapus record temporary upload yang telah terpakai.
- `app/Http/Controllers/Api/ReportController.php`: `deleteStandaloneUpload()` mengecek hak kepemilikan di `temporary_uploads`.

### 2. Password Reset Hardening
- `app/Http/Controllers/Api/AuthController.php`:
  - `forgotPassword()` hanya memproses permintaan untuk akun dengan `status === 'aktif'`.
  - `resetPassword()` mengembalikan HTTP 403 jika akun berstatus `non-aktif`.

### 3. Production Configuration
- `.env.example`: Dikonfigurasi dengan `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=info`, `SESSION_ENCRYPT=true`, `CORS_ALLOWED_ORIGINS`, dan checklist deployment.

---

## Verifikasi Pengujian (Automated Test Suite)

Semua 46 feature test dan unit test berjalan dan lulus 100%:
- `tests/Feature/SecurityHardeningTest.php` (Menguji inactive token block, cross-media question rejection, non-existent file path, formula injection export, avatar-only profile update, delete-upload authorization, admin report update validation, private disk attachment storage, temporary upload ownership cross-user protection, dan inactive user password reset prevention).
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

**Total Hasil Akhir: 46 Passed, 160 Assertions (100% Green).**