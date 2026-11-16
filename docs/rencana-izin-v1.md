# Rencana implementasi modul pengajuan izin (v1)

Tanggal: 16 November 2026. Sumber rancangan: `~/repo/web-parantau/docs/superpowers/specs/2026-11-16-aura-pengajuan-izin-design.md` (bagian 0 dan 2 memuat keputusan pengguna terbaru).

Rencana ini tidak memberi izin deployment. Semua pekerjaan dilakukan di lokal dengan data sintetis.

## Keputusan yang dipakai

- Satu persetujuan pemberi izin cukup. Mengetahui (Wakil) hanya menerima pemberitahuan, tanpa aksi.
- Pegawai boleh keluar setelah status `disetujui`.
- Izin Ketua: surat ke KPTA, status `diajukan_ke_pta`, Ketua mencatat hasil PTA, Wakil diberi tahu.
- Izin darurat/lisan: kolom dibuat, saklar `izin_lisan_aktif` default 0.
- Surat memakai template `izin_keluar_kantor` yang ada; TTD basah tetap, ditambah keterangan “Disetujui melalui AURA pada …”.
- Pengelola data: admin AURA.
- Zona kantor: `Asia/Makassar` (WITA) disimpan di pengaturan modul, bukan dari browser.

## Kondisi kode yang ditemukan

| Hal | Temuan | Dampak |
|---|---|---|
| Lingkungan lokal | `config/config.php` tidak ada; DB `aura` lokal tidak bisa diakses user `aura_app` | Perlu disiapkan dulu (Fase 0) |
| Login | `Auth::login()` menyimpan `user_id`, `peran`; tidak menyimpan NIP; `login.php` selalu redirect ke `index.php` | Tambah parameter `lanjut` tervalidasi untuk tautan WA |
| Identitas | `user_login.nip` unik (migrasi 030), dicocokkan ke `pegawai.nip` | Pemohon = `user_login.nip` → `pegawai` aktif |
| Peran `pengguna` | Hanya boleh `izin_keluar_kantor` di jalur lama | Modul baru jadi pintu utama; jalur lama ditutup saat modul aktif |
| Generator | `DocxGenerator::generate()` mengembalikan path DOCX sementara | Dipakai langsung, lalu `soffice --headless --convert-to pdf` |
| Template di repo | Pegawai: belum punya `${tanggal_izin}`/jam; Hakim: punya `${hari}`, `${tanggal_izin_keluar}`, `${jam_mulai_izin}`, `${jam_selesai_izin}` | Template aktif di VPS (`templates/uploaded`) diambil baca-saja, lalu dibuat versi baru dengan variabel tambahan |
| WhatsApp | RESTU memakai Fonnte (paket gratis 1.000 pesan/bulan), token di DB RESTU; nomor di `restu.pegawai.no_telp` | AURA pakai token Fonnte sendiri di pengaturan modul (akun boleh sama, kuota berbagi); `no_telp` ikut disinkron oleh cron RESTU→AURA yang sudah ada |
| Tes | Skrip PHP polos (`php tests/*.php`), tanpa framework | Ikuti pola ini |
| Migrasi terakhir | `036_izin_keluar_format_lokal.sql` | Migrasi baru: `037_izin_modul.sql` |
| Nginx | Konfigurasi di VPS, tidak di repo; ada whitelist PHP | Endpoint baru perlu ditambah saat deploy |

## Penyederhanaan terhadap rancangan

Dipotong dari v1 karena belum ada kebutuhan nyata. Ditambahkan nanti bila diminta.

- Tabel `izin_petugas`: pengelola = admin AURA. Tambah bila ada petugas non-admin.
- Periode berlaku pada `izin_pejabat`: cukup kode jabatan → pegawai, perubahan diaudit. Snapshot di permohonan menjaga riwayat.
- Pengingat WA lima menit, status `tidak_pasti`, lease worker, webhook: tidak ada. Outbox cukup `antre/terkirim/gagal/dibatalkan`, maksimal 3 percobaan, cron dengan `flock`.
- Antrean PDF: PDF dibuat sinkron sesudah commit keputusan. Gagal → status berkas `gagal`, tombol “Buat ulang” untuk admin.
- Rekap/grafik: register dengan filter + CSV saja.

## Fase 0 — Lingkungan lokal (perlu bantuan pengguna)

1. Buat DB lokal `aura_local` dan beri hak ke user lokal (butuh akses root MySQL, dilakukan pengguna).
2. `config/config.php` lokal menunjuk `aura_local`.
3. Muat `db/schema.sql` + migrasi 002–036 berurutan (atau dump skema produksi tanpa data pribadi).
4. Isi data sintetis: Ketua, Wakil, Panitera, Sekretaris, 2 hakim, 2 staf kepaniteraan, 2 staf kesekretariatan, masing-masing punya akun dengan NIP.
5. Ambil template izin aktif dari VPS (baca saja) ke `templates/uploaded/` lokal.

## Fase 1 — Migrasi `db/037_izin_modul.sql`

Aditif, InnoDB, tanpa cascade delete.

- `izin_pengaturan` (id=1): `mode` (`nonaktif/aktif/pemeliharaan`), `zona_waktu`, `jadwal_kerja` (JSON per hari: mulai/selesai), `izin_lisan_aktif`, `wa_aktif`, `wa_token`.
- `izin_hari_libur`: tanggal, keterangan.
- `izin_pemetaan_pegawai`: `pegawai_id` unik, `kelompok` (`ketua/wakil/hakim/panitera/sekretaris/kepaniteraan/kesekretariatan`).
- `izin_pejabat`: `kode` unik (`ketua/wakil/panitera/sekretaris`), `pegawai_id`.
- `izin_berhalangan`: `pegawai_id`, `mulai`, `selesai`, `jenis` (`cuti/dinas_luar/lainnya`), catatan, pembuat, `dibatalkan_at`.
- `izin_permohonan`: `kode` unik, pemohon user/pegawai, snapshot nama/NIP/jabatan/kelompok, `jenis` (`keluar_sementara/pulang_awal`), `tanggal`, `jam_mulai`, `jam_kembali` nullable, `alasan`, `status`, `versi`, `asal_permohonan_id`, snapshot mengetahui (user, nama, jabatan) nullable, `pemberi_eksternal` (teks KPTA) nullable, `lisan` + `waktu_kejadian` + `dicatat_oleh` (dipakai hanya bila saklar aktif), timestamp dibuat/diajukan/diputus.
- `izin_tahap`: permohonan, pemberi user/pegawai, snapshot nama/jabatan, `status` (`menunggu/selesai/ditolak/diganti`), catatan, waktu, `diganti_dari`. Satu tahap `menunggu` per permohonan.
- `izin_audit`: append-only.
- `izin_notifikasi`: penerima, permohonan, peristiwa, pesan, `dibaca_at`; unik (peristiwa, penerima, permohonan).
- `izin_wa_outbox`: penerima, nomor, pesan, permohonan, `status`, `percobaan`, `coba_lagi_at`, `error`; unik (peristiwa, penerima, permohonan).
- `izin_dokumen`: permohonan unik, `nomor` unik (`IZN/{tahun}/{urutan}`), `status` (`diproses/siap/gagal`), template id, snapshot nilai JSON, path PDF privat, hash, error.
- `pegawai.no_telp` (kolom baru, diisi cron sinkron).

## Fase 2 — Logika inti `src/Izin/`

- `Routing.php`: fungsi murni `tentukan(kelompok, pejabat, berhalangan, pemohonPegawaiId)` → pemberi, mengetahui, atau error. Matriks bagian 8 rancangan. Tes: `tests/izin-routing.php`, semua baris matriks + Ketua + kasus kosong/sama dengan pemohon.
- `Waktu.php`: validasi jenis/tanggal/jam terhadap jadwal, libur, zona; “Mulai sekarang” diisi server. Tes: `tests/izin-waktu.php`.
- `Permohonan.php`: buat draf, ajukan, setujui, tolak, batalkan, cabut, catat hasil PTA, alihkan. Tiap aksi: transaksi → `SELECT … FOR UPDATE` → cek status/versi/aktor/akun aktif → tulis status + tahap + audit + notifikasi + outbox → commit. Cek bentrok interval dengan lock baris pegawai.
- `Dokumen.php`: snapshot nilai → `DocxGenerator::generate()` → `soffice` ke PDF di `storage/izin/` (di luar web root, atau ditolak Nginx) → hash → `siap`. Retry pakai snapshot dan nomor yang sama.
- `Wa.php`: format nomor + kirim Fonnte (port kecil dari `restu/includes/whatsapp.php`), adapter palsu untuk tes.
- Tes integrasi DB: `tests/izin-transisi.php` di `aura_local` (klik ganda, setuju vs batal, audit gagal → rollback, bentrok interval).

## Fase 3 — Halaman

Pakai layout dan CSS AURA (`views/layout_atas.php`, `assets/css/style.css`).

- `izin/index.php`: izin saya + tombol ajukan + tautan antrean.
- `izin/form.php`: identitas otomatis, jenis, tanggal, “Mulai sekarang”/jam, jam kembali, alasan, pratinjau rute dari server.
- `izin/detail.php`: ringkasan, status, tombol sesuai hak, status surat, status WA.
- `izin/tugas.php`: antrean “Berikan izin”.
- `izin/aksi.php`: semua POST, CSRF.
- `izin/unduh.php`: GET, cek akses, sajikan PDF arsip.
- `admin/izin_pengaturan.php`: mode, jadwal, libur, pemetaan kelompok, pejabat, berhalangan, WA.
- `admin/izin_register.php`: filter, paginasi, CSV (tanpa alasan, lindungi formula).
- `login.php`: parameter `lanjut` hanya path relatif internal `izin/…`.
- Menu sidebar: “Izin Keluar” mengarah ke modul saat mode ≠ `nonaktif`.

## Fase 4 — Tutup jalur lama

- `surat/index.php`: bila mode ≠ `nonaktif` dan `kode=izin_keluar_kantor` → GET redirect ke `izin/`, POST ditolak 403 untuk semua peran.
- Cari jalur lain yang bisa membuat surat izin (prefill, ZIP, unduh template).
- Tes: `tests/izin-jalur-lama.php`.

## Fase 5 — WhatsApp

- `cron/izin_wa.php`: tiap menit, `flock`, batch 20, cek tugas masih aktif sebelum kirim, retry 1 lalu 5 menit, maksimal 3.
- `cron/sync_pegawai_dari_restu.php`: tambah kolom `no_telp` (tetap satu arah).
- Uji dengan adapter palsu; kirim nyata hanya ke nomor uji yang disetujui pengguna.

## Fase 6 — Portal kotak hijau

- `api/izin_hari_ini.php` di AURA: token portal di config, field minimum, hanya tanggal hari ini (zona kantor).
- `~/repo/port/izin.php`: perantara server, cache 60 detik, token tidak ke browser.
- `~/repo/port/index.html`: slide “Izin pegawai hari ini” di `kotakSalam()`, kedaluwarsa 2 menit, gagal ≠ “tidak ada izin”.
- `port` tidak punya Git: salin cadangan sebelum mengubah.

## Fase 7 — Verifikasi

- Jalankan semua `tests/izin-*.php` + tes lama.
- Uji browser dengan akun sintetis: alur kepaniteraan, hakim, Ketua→KPTA, penolakan, pembatalan, pengalihan.
- Contoh PDF pegawai dan hakim untuk ditinjau pengguna.
- Review keamanan terarah: akses objek, unduhan, CSRF, jalur lama.
- Laporan: selesai+diuji / belum diuji / asumsi / tindakan produksi yang belum dilakukan.

## Fase 8 — Deployment (perlu izin terpisah)

Backup DB `aura` + `templates/uploaded` + storage, migrasi 037, whitelist Nginx, cron WA, token Fonnte, pemetaan pegawai/pejabat oleh admin, mode `aktif`, smoke test. Rollback: mode `pemeliharaan` (jalur lama tetap tertutup), jangan drop tabel.

## Masih terbuka (tidak menghambat Fase 0–7)

- Ketua berhalangan sebagai pemberi izin (hakim/Wakil): sementara pengajuan diblokir dengan pesan.
- Jam kerja per hari: diisi admin; perlu angka resmi sebelum aktif.
- Penerimaan format di SIKEP: dinilai dari contoh PDF.
