# Ide: Jenis surat Undangan diperluas (lampiran + arsip NAS + peserta)

Status: **item 1+4 (Daftar Hadir + peserta) SELESAI & sudah live di VPS. Item 3 (Notula)
SELESAI dibangun & diuji lokal, 2026-09-18** — belum di-deploy ke VPS. Item 2 (arsip NAS)
**belum dikerjakan**, nunggu giliran build terpisah. Jangan mulai coding item 2 dari
dokumen ini tanpa konfirmasi ulang kalau udah lewat waktu lama (cek chat/memory dulu).

## Implementasi Notula (item 3) — selesai

Notula jadi jenis_surat SENDIRI (kode `notula`), bukan sub-fitur Undangan - pakai
100% engine generik yang sudah ada (variabel_surat/peran_pegawai_surat/template_surat),
gak ada tabel baru khusus notula. 2 keputusan yang disepakati sebelum build:

- **Prefill, bukan input manual**: tombol "Buat Notula" di halaman Riwayat Surat
  Diterbitkan (`admin/surat_diterbitkan.php`, cuma muncul di baris Undangan utama)
  ke `surat/index.php?kode=notula&dari={surat_diterbitkan.id}` - form Notula otomatis
  ke-isi hari/tanggal/waktu/tempat/acara (kode variabel SENGAJA sama persis kayak
  Undangan, jadi cocok langsung tanpa mapping) + peserta rapat (ringkasan teks dari
  peserta Daftar Hadir sumbernya) + dasar (nomor+tanggal surat undangan sumbernya,
  kalau ada). **Field tanggal (tipe date) gak ke-prefill** - nilai yang kesimpen di
  histori sudah lewat format tanggal_indonesia ("25 September 2026"), bukan format
  YYYY-MM-DD yang dibutuhin `<input type=date>` - keterbatasan yang diketahui, bukan
  bug, user tinggal pilih ulang tanggalnya (semua field lain tetap ke-prefill).
  Konsekuensi baru: `surat_diterbitkan` dapat kolom `tabel_lengkap` (JSON, snapshot
  blok_tabel_surat submission - sebelumnya cuma nilai skalar yang kesimpen).
- **Upload foto beneran**: variabel_surat dapet `tipe_input='file'` baru (ditangani
  khusus di `surat/index.php` - render `<input type=file>`, validasi MIME asli lewat
  `Aurat\Surat\FotoUpload` mirip pola `TemplateUpload`, simpan ke `uploads/notula_foto/`
  yang di-deny-all lewat .htaccess sama kayak `templates/uploaded/`). `DocxGenerator`
  dapat param `$gambar` - dipasang lewat `setImageValue()`, bukan `setValue()` biasa;
  field kosong otomatis ke-blank (bukan nampilin `${foto_notula}` mentah) karena
  `NilaiResolver` sudah jatuh ke `placeholder_default=''` buat variabel manual yang
  gak diisi, jadi gak perlu logic blanking terpisah.

Notulis & Pejabat yang Mengundang: 2 `peran_pegawai_surat` baru (`notulis`,
`pejabat_acara`) - **TIDAK ada akun/login baru**, cuma dropdown pilih pegawai, sama
kayak `petugas_cuti`/`diperintah` yang sudah ada di app lain.

Interpretasi 1 hal ambigu di draft: baris "Jalannya Rapat :" di tabel dibiarkan
KOSONG (bukan diksh macro) - dibaca sebagai judul section buat "Isi:"/"Foto:" di
bawahnya, bukan field data sendiri (redundan kalau dua-duanya diisi). Kalau ternyata
salah baca, tinggal edit `templates/notula.docx` (repack ulang lewat skill
docx-template-merge) - gak perlu ubah skema.

Diuji end-to-end lokal: generate dari Undangan yang sudah ada (prefill jalan,
termasuk peserta), upload foto JPG beneran (ke-embed sbg PNG di docx, bukan cuma
placeholder blank), nol macro `${...}` tersisa. **Bug ditemukan & diperbaiki pas
testing**: pembacaan awal `$_FILES['var_file'][$kode]` salah struktur (PHP nge-array
`$_FILES` multi-dimensi nama field per-KEY-DI-DALAM 'name'/'type'/dst, bukan
sebaliknya) - selalu `false`, foto gak pernah ke-upload sebelum fix.

## Implementasi Daftar Hadir (item 1+4) — selesai

Mekanisme: `template_surat` dapat kolom baru `tipe_dokumen` (`utama`/`lampiran`) -
1 jenis_surat sekarang bisa punya template UTAMA (surat aslinya) + template
LAMPIRAN (mis. Daftar Hadir), keduanya di-generate BARENG dari 1 submit form,
dibungkus jadi 1 `.zip` kalau ada lebih dari 1 template aktif (`db/033_lampiran_
undangan.sql`, `src/DocxGenerator.php::generateZipDanUnduh()`,
`surat/index.php`). `surat_diterbitkan` dapat kolom `induk_id` - baris lampiran
nunjuk baris utama dari submission yang sama, biar keliatan sepaket di histori.

Blok tabel peserta baru: `blok_tabel_surat` kode `peserta` di jenis_surat
`undangan` (No/Nama/Bagian/Ket - pegawai-picker, generik pakai mekanisme yang
sama kayak Surat Tugas), terpisah dari field "Kepada Yth." (`tujuan`) yang
sudah ada duluan.

Template `templates/daftar_hadir_undangan.docx` dibangun dari draft asli user
(`draft daftar hadir.docx`) lewat macro-injection (skill docx-template-merge),
BUKAN rebuild - 13 macro: `jenis_kegiatan` (baru), `penandatangan_nip` (baru),
sisanya reuse by-kode dari variabel Undangan yang sudah ada (`nama_acara`,
`hari`, `tanggal_acara`, `waktu`, `tempat`, `tanggal_surat`,
`penandatangan_nama_lengkap`). Admin upload lewat tab baru "Dokumen Lampiran"
di `admin/template_surat.php` (sama alur admin-UI-upload yang sudah baku di
proyek ini, bukan file transfer).

Mekanisme `tipe_dokumen` ini GENERIK - bisa dipakai jenis_surat lain yang
butuh lampiran di masa depan, gak cuma Undangan/Daftar Hadir.

**Belum dilakukan**: deploy ke VPS (`aura.pa-rantau.go.id`) - butuh migrasi
`033` (inget `sed USE aurat->aura`) + upload `templates/daftar_hadir_
undangan.docx` lewat admin UI produksi (bukan file transfer, sama kayak semua
template di proyek ini) + pasang 9 variabel yang di-reuse + set peran
`penandatangan` (id beda per server, jangan hardcode dari sesi ini).

Draft `draft notula.docx` juga sudah diterima & dianalisis strukturnya
(1 tabel gabungan: field info + baris "Isi:"/"Foto:" merged full-width +
TTD ganda Notulis/Pejabat) tapi belum di-macro-inject - nunggu giliran build
item 3.

## Latar belakang

`undangan` sekarang cuma jenis_surat biasa (surat undangan doang, satu dokumen). Mau
diperluas jadi rangkaian: undangan -> daftar hadir (lampiran, sepaket) -> notulen
(susulan, sesudah acara). Plus auto-arsip ke NAS.

## 1. Daftar hadir (lampiran, di-generate BARENG undangan)

- Peserta diinput **terpisah** dari field "Kepada Yth." yang udah ada (Kepada Yth. =
  alamat tujuan formal surat/institusi-jabatan; daftar hadir = individu spesifik yang
  diundang hadir & tanda tangan).
- Implementasi: `blok_tabel_surat` baru khusus `undangan` (pola sama kayak tabel
  pegawai di Surat Tugas - employee-picker + baris manual, generik, gak perlu
  mekanisme baru di engine).
- Output: peserta dari tabel itu otomatis ngisi baris di dokumen daftar hadir,
  kolom tanda tangan dikosongin (ditandatangan fisik pas acara, bukan digital).
- Di-generate bareng undangan (bukan susulan kayak notulen) - begitu undangan jadi,
  daftar hadir juga langsung bisa didownload.
- Format dokumen (docx) belum ditentukan - user nyusul kasih contoh/draft.

## 2. Notulen (record terpisah, susulan setelah acara)

- **Bukan** field tambahan di form Undangan yang sama - notulen adalah entitas baru,
  dibuat setelah undangan ada, link ke undangan induknya (tarik tanggal/peserta/agenda
  dari situ, gak input ulang).
- Alurnya: undangan dibuat (sebelum acara) -> acara berlangsung -> notulen dibuat
  belakangan (record baru, merujuk undangan_id), isinya uraian jalannya rapat/keputusan
  yang emang baru ada setelah acara selesai.
- **Role notulis: TIDAK perlu akun/login baru.** Pola sama kayak `petugas_cuti`/
  `diperintah` yang udah ada - notulis cuma "ditunjuk" via dropdown pegawai
  (`peran_pegawai_surat` baru, misal `notulis`), yang login & isi form notulen tetap
  admin/pengelola. Gak nambah tier baru ke role 3-tier (`pengguna`/`pengelola`/`admin`)
  yang baru aja jadi (db/031).
- Format dokumen notulen belum ditentukan - user nyusul.

## 3. Auto-arsip output ke NAS

- **Scope: cuma Undangan (+ daftar hadir, + notulen kalau udah ada) dulu** - bukan
  langsung digeneralisir ke semua jenis_surat AURA, meskipun mekanismenya generik dari
  sisi implementasi (gampang di-extend nanti tanpa nulis ulang).
- Desain sama persis kayak [[project_lucu_pa_rantau]]'s NAS-archive idea (dicatat di
  RESTU punya `docs/ide-arsip-nas.md`, 2026-09-18): app simpen 1 copy permanen lokal
  di VPS abis generate (synchronous, gak gantung ke NAS), lalu cron `rsync` over SSH
  ke NAS Synology tiap beberapa menit (async, gak nge-block request user, auto-retry
  kalau NAS lagi unreachable).
- Detail koneksi NAS (hostname/DDNS, port, user, folder tujuan, keypair) - lihat
  `docs/ide-arsip-nas.md` di repo RESTU, prinsipnya sama, tinggal disesuaikan folder
  tujuan (arsip Undangan AURA, bukan arsip cuti RESTU).

## Yang belum diputuskan / nyusul

- Format .docx daftar hadir & notulen (user nyusul kasih contoh/draft asli).
- Struktur data notulen (field apa aja selain "uraian" - agenda, keputusan, dll -
  nunggu draft format).
- Apakah 1 undangan bisa punya lebih dari 1 notulen (rapat lanjutan?) atau strictly
  1:1 - belum dibahas, defaultnya 1:1 kecuali user bilang lain.
- Detail teknis NAS (hostname/kredensial) - belum dikasih user, lihat pertanyaan yang
  sama yang masih terbuka di `docs/ide-arsip-nas.md` RESTU.

## Urutan eksekusi yang masuk akal (belum final, cuma draft urutan)

1. `blok_tabel_surat` peserta daftar hadir + template docx-nya (begitu format dikasih)
2. Entitas `notulen` (tabel baru, form input, link ke `undangan_id`) + `peran_pegawai_surat`
   role `notulis` + template docx-nya (begitu format dikasih)
3. Arsip lokal permanen (app-side) buat ketiga dokumen di atas
4. Cron rsync ke NAS (butuh detail koneksi NAS dari user dulu)
