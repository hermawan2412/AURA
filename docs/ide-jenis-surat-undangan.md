# Ide: Jenis surat Undangan diperluas (lampiran + arsip NAS + peserta)

Status: **desain disepakati, belum diimplementasi** — dicatat 2026-09-18, hasil sinkron
visi via chat sebelum eksekusi. Jangan mulai coding dari dokumen ini tanpa konfirmasi
ulang kalau udah lewat waktu lama (cek chat/memory dulu, desain bisa berubah).

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
