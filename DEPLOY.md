# Deploy — AURA (Aplikasi Untuk suRAt)

Panduan memasang folder `app/` ini di server kantor (CentOS 7, PHP 5.6, MariaDB, akses `192.168.100.7`, LAN-only).

## 0. Prasyarat yang perlu dicek dulu di server

- **Composer tersedia?** Belum dipastikan. Kalau belum ada, perlu dipasang dulu (atau minta bantuan pihak yang pegang server) sebelum langkah 2 di bawah bisa jalan.
- **Ekstensi PHP aktif:** `pdo_mysql`, `mbstring`, `zip`, `xml` — dipakai PDO dan PHPWord. Cek dengan `php -m | grep -E "pdo_mysql|mbstring|zip|xml"`.
- **`AllowOverride` diizinkan** untuk folder ini di konfigurasi Apache induk. Ini **wajib dicek**, bukan diasumsikan — kalau `AllowOverride None` yang berlaku, file `.htaccess` yang memproteksi `src/`, `config/`, `vendor/`, `templates/`, `db/`, `views/` tidak akan berfungsi, dan folder-folder itu bisa diakses langsung lewat browser. Kalau ternyata tidak bisa diubah, beri tahu saya — perlu cara proteksi lain.

## 1. Salin folder

Salin seluruh isi `app/` ke server, contoh ke `/var/www/html/aurat/` (folder terpisah dari aplikasi lain yang sudah ada, sesuai kesepakatan isolasi — nama folder ini bebas, tidak memengaruhi kode).

## 2. Pasang dependensi (PHPWord)

```
cd /var/www/html/aurat
composer install
```

## 3. Buat database

```
mysql -u root -p < db/schema.sql
mysql -u root -p aurat < db/002_generic_surat_engine.sql
mysql -u root -p aurat < db/003_blok_tabel_fungsi_pasca.sql
mysql -u root -p aurat < db/004_ikon_jenis_surat.sql
mysql -u root -p aurat < db/005_peran_admin_user_login.sql
```

`schema.sql` otomatis membuat database `aurat`, tabel `pegawai` & `user_login`, plus 5 data pegawai contoh dan 1 akun login contoh. Keempat file `00X_...sql` berikutnya menambah: mesin template/variabel generic (`jenis_surat`, `variabel_surat`, `template_surat`, dst — 002), kolom fungsi turunan pada blok tabel (003), ikon per jenis surat (004), dan peran administrator pada `user_login` (005 — lihat §5a). **Semuanya wajib dijalankan berurutan**, termasuk di deploy baru maupun upgrade dari versi sebelum mesin generic ini ada.

## 3a. Isi data 6 jenis surat bawaan

Tabel `jenis_surat` dkk masih kosong setelah langkah 3 — jenis surat tidak lagi datang dari file `config/jenis_surat/*.json` (sudah dipensiunkan, arsipnya di `_arsip_pra_migrasi/`), tapi dari data di database. Isi dengan skrip migrasi CLI (baca `.docx` asli di `templates/`, tidak mengubah berkasnya):

```
php migrasi/import_jenis_surat.php pelaksana_harian
php migrasi/import_jenis_surat.php pernyataan_melaksanakan_tugas
php migrasi/import_jenis_surat.php berita_acara_sumpah
php migrasi/import_jenis_surat.php undangan
php migrasi/import_jenis_surat.php surat_tugas
php migrasi/import_jenis_surat.php sk
```

Tiap perintah mengecek dulu bahwa semua variabel yang didefinisikan benar-benar ada sbg placeholder `${...}` di berkas `.docx`-nya sebelum menyimpan apa pun — kalau gagal, tidak ada data yang tertulis (aman diulang). Skrip ini idempoten per kode: dijalankan dua kali untuk kode yang sama akan ditolak (bukan duplikat) — hapus dulu manual dari tabel `jenis_surat` kalau memang perlu migrasi ulang.

Setelah ini, jenis surat baru **tidak perlu lagi lewat skrip CLI** — cukup lewat menu **Kelola Jenis Surat** di aplikasi (perlu login dulu).

## 4. Konfigurasi

```
cp config/config.example.php config/config.php
```

Buka `config/config.php`, isi `host`, `dbname`, `user`, `pass` sesuai kredensial MariaDB yang sudah dibuat.

## 5. Ganti password akun contoh

Password di `db/schema.sql` cuma placeholder, belum bisa dipakai. Generate hash asli:

```
php -r "echo password_hash('KATA_SANDI_BARU', PASSWORD_DEFAULT), PHP_EOL;"
```

Lalu jalankan di MariaDB:

```sql
UPDATE user_login SET password_hash = '<hasil_di_atas>' WHERE username = 'admin.kepegawaian';
```

Kalau ada 2-3 admin dengan akun terpisah, tambah baris baru di `user_login` untuk masing-masing (username, nama_tampilan, dan hash password sendiri-sendiri) — bukan berbagi satu akun. Cara paling gampang: login pakai `admin.kepegawaian` dulu, lalu tambah akun-akun berikutnya lewat menu **Kelola Pengguna** (lihat §5a) — tidak perlu lagi lewat SQL manual.

## 5a. Peran administrator (menu Kelola Pengguna)

Migrasi `005_peran_admin_user_login.sql` menambah kolom `is_admin` — hanya akun dengan peran ini yang melihat & bisa membuka menu **Kelola Pengguna** di sidebar (kelola akun login, reset kata sandi, kelola peran admin akun lain). Akun lain tetap bisa login & pakai semua menu surat seperti biasa, cuma menu ini yang disembunyikan dan diblok kalau URL-nya diakses langsung.

Di deploy baru (cuma ada akun `admin.kepegawaian` dari `schema.sql`), migrasi otomatis menjadikan akun itu administrator pertama. Setelah login, cek/atur peran admin akun lain lewat **Kelola Pengguna** — tidak perlu SQL manual, kecuali untuk situasi darurat (mis. semua admin lupa kata sandi sekaligus):

```sql
UPDATE user_login SET is_admin = 1 WHERE username = 'admin.kepegawaian';
```

## 6. Permission folder

Pastikan user yang menjalankan Apache/PHP-FPM bisa membaca semua file (biasanya `755` untuk folder, `644` untuk file sudah cukup; tidak perlu `777`). Folder `templates/uploaded/` (dibuat otomatis saat admin pertama kali unggah template lewat menu **Kelola Jenis Surat**) juga perlu bisa **ditulis** oleh Apache/PHP-FPM — pastikan filenya sudah ter-copy dgn permission yang mengizinkan itu, atau buat folder ini manual (`mkdir templates/uploaded && chmod 755 templates/uploaded`) sebelum dipakai. Folder ini sudah otomatis ber-`.htaccess` deny-all sama seperti `templates/` induknya, jadi tetap tidak bisa diakses langsung lewat browser.

**Jangan salin folder `_arsip_pra_migrasi/`** ke server — isinya kode & config lama sebelum mesin generic ini ada, disimpan cuma sebagai referensi historis di komputer developer, tidak dipakai aplikasi sama sekali.

## 6a. Sync pegawai dari RESTU (opsional, cuma kalau satu server dgn app RESTU)

Kalau server ini juga menjalankan app RESTU (cuti) di MySQL instance yang sama, `cron/sync_pegawai_dari_restu.php` bisa nyinkron `nama_lengkap`/`jabatan`/`golongan_ruang`/`tmt` pegawai dari RESTU ke AURA tiap hari, satu arah (RESTU -> AURA), read-only terhadap RESTU. Field lain (pangkat, gelar, unit_kerja, status_aktif) sengaja tidak disentuh — lihat komentar di kepala file skrip untuk alasannya.

Skrip yang sama (Tahap 2, ditambah 2026-09-05) SEKALIAN nyinkron **akun login** dari `restu.user` — akun per-pegawai (username + password bcrypt + is_admin dari role) otomatis dibuat/di-update di AURA, password DISINKRON (1 kredensial buat 2 app, dikonfirmasi user). Butuh `db/030_sync_akun_dari_restu.sql` sudah jalan (kolom `user_login.nip`).

1. Buat user MySQL read-only, cuma ke 4 tabel yang dibutuhkan (nambah `user` dari sebelumnya):
   ```sql
   CREATE USER 'aura_restu_reader'@'localhost' IDENTIFIED BY 'PASSWORD_BARU_DI_SINI';
   GRANT SELECT ON restu.pegawai TO 'aura_restu_reader'@'localhost';
   GRANT SELECT ON restu.jabatan TO 'aura_restu_reader'@'localhost';
   GRANT SELECT ON restu.golongan TO 'aura_restu_reader'@'localhost';
   GRANT SELECT ON restu.user TO 'aura_restu_reader'@'localhost';
   FLUSH PRIVILEGES;
   ```
2. Isi blok `db_restu_readonly` di `config/config.php` dengan kredensial di atas (lihat `config.example.php`).
3. Tes dulu tanpa nulis apa-apa: `php cron/sync_pegawai_dari_restu.php --dry-run` — cek daftar NIP yang bakal ke-update/dibuat masuk akal, baru lanjut.
4. Pasang cron (`/etc/cron.d/aura-sync-pegawai-restu`, root:root, 644):
   ```
   0 1 * * * www-data /usr/bin/php /var/www/aura/cron/sync_pegawai_dari_restu.php >> /var/log/aura-sync-pegawai-restu.log 2>&1
   ```
5. Kalau server ini BUKAN satu MySQL instance dengan RESTU (server terpisah), skip semua langkah di atas — hapus/kosongkan `db_restu_readonly` dari config dan jangan pasang cron-nya, skrip akan exit dengan pesan error yang jelas kalau config-nya belum ada, bukan crash diam-diam.

## 7. Akses

```
http://192.168.100.7/aurat/login.php
```

## Uji coba alur pertama (Surat Tugas)

1. Login pakai `admin.kepegawaian` + password baru dari langkah 5.
2. Buka menu **Surat Tugas**.
3. Cari salah satu dari 5 pegawai contoh (mis. "Sri Wahyuni"), pilih dari hasil pencarian — pilih 1 atau lebih pegawai (tabel bertambah tiap kali memilih).
4. Isi uraian tugas dkk, coba juga dengan dan tanpa mengisi "Sumber Anggaran" untuk memastikan kalimat pembiayaan muncul/hilang dengan benar, lalu klik **Unduh Dokumen**.
5. Buka file `.docx` yang terunduh — pastikan semua data terisi benar dan tidak ada teks `${...}` yang tersisa (tanda ada placeholder yang belum kena isi).

Untuk **Surat Keputusan**, coba kedua Jenis SK (Tim Kerja & Panitia) — perhatikan kolom terakhir tabel lampiran berubah label ("Kedudukan" vs "Peran dalam Panitia") mengikuti pilihan, dan coba seret-urutkan baris sebelum diunduh.

### Uji coba menu Kelola Jenis Surat (admin)

Buka menu **Kelola Jenis Surat** di sidebar (perlu login) untuk mencoba alur admin non-programmer menambah jenis surat baru:
1. **+ Jenis Surat Baru** — isi kode, nama, kategori, simpan.
2. Tambah minimal satu **Peran Pegawai** kalau jenis surat itu butuh pemilih pegawai.
3. Klik **Kelola Template**, unggah berkas `.docx` yang placeholder-nya sudah disiapkan (`${nama_variabel}`, dan `${kolom#1}` dst utk tabel — lihat placeholder yang sudah ada di `templates/*.docx` bawaan sbg contoh).
4. Di layar **Variabel**, petakan tiap placeholder yang terdeteksi ke sumber data (manual/pegawai/turunan/sistem).
5. Kalau ada tabel berulang (mis. daftar lampiran pegawai), kelola lewat **Kelola Blok Tabel**.
6. Jenis surat langsung muncul di dasbor & sidebar begitu ada template aktif — coba generate dokumennya.

## Status per jenis surat

Keenam jenis surat bawaan sudah dimigrasikan penuh ke mesin template/variabel generic (lihat §3a) — tidak lagi punya berkas PHP sendiri-sendiri, semuanya lewat `surat/index.php?kode={kode}`. Menambah jenis surat baru cukup lewat menu **Kelola Jenis Surat**, tanpa deploy kode baru.

**Surat Cuti sudah tidak ada di sini** — sudah diakomodir penuh oleh aplikasi terpisah **LUCU (Aplikasi Untuk Cuti)**. Instalasi lama yang masih punya jenis surat `cuti` perlu menjalankan `db/016_hapus_jenis_surat_cuti.sql` sekali (aman diulang) untuk membersihkannya.

| Jenis surat | Kode | Status |
|---|---|---|
| Surat Tugas | `surat_tugas` | Termigrasi — siap dicoba (tabel lampiran pegawai) |
| Surat Keputusan | `sk` | Termigrasi — siap dicoba (2 sub-jenis: Tim Kerja & Panitia) |
| Undangan | `undangan` | Termigrasi — siap dicoba |
| Berita Acara Pengambilan Sumpah | `berita_acara_sumpah` | Termigrasi — siap dicoba |
| Pelaksana Harian | `pelaksana_harian` | Termigrasi — siap dicoba |
| Pernyataan Melaksanakan Tugas | `pernyataan_melaksanakan_tugas` | Termigrasi — siap dicoba |

## Pengingat keamanan & operasional

- `config/config.php` **jangan** pernah ikut dikirim/dibagikan atau masuk kontrol versi (sudah masuk `.gitignore`).
- Backup rutin **seluruh database** `aurat` (bukan cuma `pegawai`/`user_login` — sejak mesin template/variabel generic, tabel `jenis_surat`, `variabel_surat`, `template_surat`, `blok_tabel_surat*` juga sumber data satu-satunya) **dan** folder `templates/uploaded/` (berkas `.docx` yang diunggah admin lewat menu Kelola Jenis Surat, dirujuk dari `template_surat.nama_berkas` — backup DB saja tidak cukup tanpa berkas fisiknya).
- Akses aplikasi ini harus **selamanya LAN-only** — jangan forward port ini ke internet.
- Dokumen hasil generate tidak disimpan di server (streaming langsung ke unduhan) — tidak perlu rutinitas bersih-bersih berkas.
