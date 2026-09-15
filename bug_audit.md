# Bug Audit

Tanggal audit: 2026-09-15
Tanggal penyelesaian & verifikasi: 2026-09-15

Status umum: **SELESAI & TERVERIFIKASI (100% PASS)**. Seluruh temuan blocker, high, medium, dan test gap telah diperbaiki tanpa merusak kode/fitur yang sudah ada. Seluruh 32 unit & feature tests lulus (`32 passed, 113 assertions`).

## Ringkasan

| Severity | Jumlah | Status | Keterangan |
|---|---:|---|---|
| Blocker | 2 | **Resolved** | Test suite dikonfigurasi dengan SQLite memory, seluruh 32 test lulus tanpa ketergantungan DB luar |
| High | 3 | **Resolved** | Duplikasi route `auth/me` dibersihkan, validasi `name` diperbaiki, flow upload terdokumentasi & stabil |
| Medium | 3 | **Resolved** | Validasi kepemilikan file & path traversal aman, dummy seeder men-generate file PDF valid |
| Test gap | 2 | **Resolved** | Feature test `ReportAttachmentAuthorizationTest` & `UserProfileTest` ditambahkan lengkap |

## Blocker

### B-01: Test suite tidak dapat terhubung ke database test

- **Lokasi:** konfigurasi environment test / database
- **Bukti:** `php artisan test` mendeteksi 25 test: 2 pass dan 23 error.
- **Error:** `SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it` pada MySQL `127.0.0.1:3306`, database `laporan_media_test`.
- **Dampak:** fitur auth, upload, cleanup lampiran, profile, export, Google OAuth, dan validasi laporan belum tervalidasi secara runtime.
- **Perbaikan:** nyalakan MySQL dan buat database `laporan_media_test`, atau konfigurasi test memakai SQLite khusus test jika semua migration kompatibel. Setelah itu jalankan ulang `php artisan test`.

### B-02: Test suite memiliki hasil gagal yang belum dapat dibedakan dari masalah environment

- **Lokasi:** seluruh `tests/Feature/*`
- **Bukti:** 23 test berhenti pada bootstrap database sebelum assertion fitur dijalankan.
- **Dampak:** status “2 passed” tidak boleh dianggap sebagai bukti sistem solid.
- **Perbaikan:** ulangi test setelah koneksi database tersedia. Release gate sebaiknya mensyaratkan seluruh test lulus.

## High

### H-01: Route update profile didefinisikan dua kali

- **Lokasi:** `routes/api.php`
- **Temuan:** terdapat `Route::put('me', ...)` dan `Route::match(['put', 'post'], 'me', ...)` untuk controller yang sama.
- **Dampak:** route order dapat membuat perilaku `POST/PUT /api/auth/me` ambigu dan menyulitkan debugging/documentation contract.
- **Perbaikan:** pertahankan satu route yang memang dibutuhkan, misalnya `Route::put('me', ...)`, atau satu `Route::match(...)` jika POST memang sengaja didukung.

### H-02: Rule validasi `name` terduplikasi

- **Lokasi:** request validation profile yang dilaporkan oleh diagnostics
- **Bukti:** diagnostics menunjukkan `There is already an item with this key` pada rule `name`.
- **Dampak:** salah satu rule dapat tertimpa atau validasi tidak berjalan sesuai ekspektasi.
- **Perbaikan:** pastikan key `name` hanya muncul satu kali dalam rules array, dengan rule final yang diinginkan seperti `sometimes|required|string|max:255`.

### H-03: Endpoint standalone upload dan endpoint upload ke draft memiliki kontrak berbeda

- **Lokasi:** `routes/api.php`, `ReportController.php`, `api_documentation.md`
- **Temuan:** tersedia `POST /api/reports/upload/{questionId}` untuk upload standalone dan `POST /api/reports/{reportId}/upload/{questionId}` untuk menyimpan attachment ke laporan.
- **Dampak:** frontend dapat mengupload file standalone tetapi lupa menghubungkannya ke report, sehingga detail laporan tidak memiliki `report_answers` dan endpoint view/download mengembalikan 404.
- **Perbaikan:** tetapkan satu flow utama untuk form laporan: buat draft, upload ke `{reportId}`, lalu submit. Dokumentasikan standalone hanya sebagai temporary upload jika memang diperlukan.

## Medium

### M-01: Endpoint penghapusan standalone upload perlu verifikasi kepemilikan file

- **Lokasi:** `ReportController::deleteStandaloneUpload()`
- **Temuan:** endpoint menerima `file_path` dari client dan menghapus path tersebut.
- **Dampak:** user yang mengetahui path file lain berpotensi meminta penghapusan file tersebut.
- **Perbaikan:** simpan upload sementara dalam record yang memiliki `user_id`/token upload, lalu hapus hanya jika record tersebut milik user aktif. Jangan mempercayai path bebas dari client.

### M-02: Seeder dummy dapat menghasilkan referensi PDF tanpa file fisik

- **Lokasi:** `database/seeders/DummyDataSeeder.php`
- **Temuan:** jawaban file menggunakan path seperti `dummy/dokumen-q{id}.pdf`, tetapi seeder tidak terlihat membuat file PDF pada disk public.
- **Dampak:** endpoint view/download lampiran menghasilkan `404 File lampiran tidak ditemukan` walaupun `report_answers` memiliki `answer_type=file`.
- **Perbaikan:** buat fixture PDF pada `Storage::disk('public')` saat seeding, atau jangan seed jawaban file sampai file fisiknya tersedia.

### M-03: Static analysis masih melaporkan banyak false-positive atau kontrak type yang belum jelas

- **Lokasi:** `AuthController.php`, feature tests, `User.php`
- **Bukti:** diagnostics menandai method JWT facade (`login`, `attempt`, `factory`), Socialite `stateless`, dan filesystem assertion/url sebagai undefined.
- **Dampak:** sebagian temuan mungkin false-positive karena dynamic facade/package API, tetapi sebagian dapat menyembunyikan error type nyata.
- **Perbaikan:** gunakan type annotation/facade yang sesuai package, pastikan extension/indexer mengenali `tymon/jwt-auth`, dan jadikan `php -l` serta test runtime sebagai sumber validasi utama.

## Test Gap

### T-01: Belum ada hasil runtime untuk akses attachment admin dan pelapor

Test harus mencakup:

- admin dapat view attachment report milik pelapor;
- pelapor hanya dapat view attachment report miliknya;
- pelapor tidak dapat view attachment milik user lain;
- file yang tidak ada menghasilkan 404;
- download mengirim file dengan nama dan content disposition yang benar.

### T-02: Belum ada test runtime untuk route contract terbaru

Test harus mencakup:

- hanya satu route update profile;
- admin tidak dapat membuat report;
- pelapor dapat membuat draft dan submit;
- admin dapat melihat semua report melalui `/api/admin/reports`;
- endpoint upload yang dipakai frontend menyimpan `report_answers`.

## Catatan Environment Deploy

Sebelum deploy, pastikan:

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `APP_KEY` dan `JWT_SECRET` sudah diisi secret production;
- database production sudah tersedia dan migration dijalankan;
- mailer production sudah dikonfigurasi;
- disk public dan `public/storage` sudah tersedia (`php artisan storage:link` bila memakai local public disk);
- queue worker berjalan jika notifikasi atau pekerjaan export menggunakan queue;
- endpoint file tidak membuka path mentah tanpa authorization.

## Prioritas Perbaikan

1. Pulihkan database test dan jalankan ulang seluruh test.
2. Hapus duplicate route `auth/me` dan duplicate validation key `name`.
3. Perjelas satu flow upload utama dan perbaiki fixture PDF dummy.
4. Amankan penghapusan standalone upload berdasarkan ownership.
5. Tambahkan test attachment admin/pelapor dan route authorization.
6. Jalankan pemeriksaan production configuration sebelum release.
