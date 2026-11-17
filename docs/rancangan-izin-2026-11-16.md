# Rancangan modul pengajuan izin AURA

Tanggal: 16 November 2026. Pelaksana implementasi: Claude.

Status: rancangan untuk ditinjau, bukan izin deployment. Pengguna memilih AURA dan persetujuan langsung di aplikasi. Rincian bertanda **usulan** belum merupakan kebijakan pimpinan yang disahkan.

Builder check independen: selesai pada 16 November 2026 untuk rancangan awal; tidak menemukan pertanyaan penghambat atau kontradiksi material untuk penyusunan rencana implementasi. Tambahan WhatsApp dan kebutuhan mendadak setelah review tersebut belum diperiksa ulang reviewer. Pemeriksaan ini menilai kelengkapan rancangan, bukan menguji aplikasi atau mengesahkan kebijakan.

Dokumen ini awalnya disusun di repo website, lalu dipindah ke repo AURA (`docs/`) pada 16 November 2026. **Target implementasi adalah repository AURA**, yang deployment-nya berada di `/var/www/aura` pada VPS alias SSH `aurat-vps`. Jangan membangun modul ini di Joomla/web-parantau dan jangan menyunting produksi sebagai workspace pengembangan. Temukan checkout sumber AURA dan instruksi proyek sebelum mengerjakan.

## 0. Rangkuman akhir dan petunjuk mulai untuk Claude

### Lokasi kerja

| Kebutuhan | Lokasi pada komputer pengguna |
|---|---|
| Dokumen rancangan ini | `/Users/hermawancahyo/repo/aura/docs/rancangan-izin-2026-11-16.md` |
| Sumber AURA, target utama implementasi | `/Users/hermawancahyo/repo/aura` |
| Sumber portal lokal, integrasi kotak hijau | `/Users/hermawancahyo/repo/port` |
| Referensi integrasi RESTU | `/Users/hermawancahyo/repo/restu` |
| Deployment AURA yang pernah diperiksa | SSH `aurat-vps`, direktori `/var/www/aura` |

Direktori lokal tersebut telah ditemukan, tetapi kesesuaiannya dengan deployment belum diverifikasi. Folder `port` tidak mempunyai metadata Git pada pemeriksaan ini; gunakan salinan kerja/backup sebelum perubahan, jangan menganggap tersedia rollback Git. Alamat dan direktori deployment portal di server lokal belum diketahui. Jangan menganggap salinan `portal-paket`, `portal-backup-*`, atau `portal-pa-rantau-*` sebagai sumber aktif.

### Hasil pembahasan

- Pengguna memilih AURA untuk pengajuan izin keluar sementara/pulang awal, dengan persetujuan langsung melalui aplikasi, WhatsApp otomatis untuk respons cepat, dan tampilan pegawai yang mendapat izin pada kotak hijau portal lokal.
- Jalur dari pimpinan: hakim/Wakil → Ketua; kepaniteraan → Panitera, diketahui Wakil; kesekretariatan → Sekretaris, diketahui Wakil. Jika Panitera/Sekretaris cuti atau dinas luar, ke Wakil; jika Wakil juga cuti, ke Ketua.
- Reuse identitas, data pegawai, layout, serta template AURA. RESTU menjadi referensi data/integrasi yang sudah ada. Pertahankan pemisahan izin dengan saldo cuti dan presensi.
- Utamakan form singkat di ponsel, identitas otomatis, pilihan mulai sekarang, serta tautan WhatsApp langsung ke detail setelah login. WhatsApp memberi kabar; keputusan dan audit tetap disimpan di AURA.
- Keputusan dan penerbitan surat mempunyai status terpisah. Gangguan provider WhatsApp atau konversi PDF sesudah commit tidak membatalkan keputusan. Tutup jalur cetak izin lama agar surat final hanya berasal dari proses yang sah.
- Sediakan arsip/register, otorisasi per objek, audit, transaksi aman terhadap klik ganda, serta pengalihan pejabat yang tercatat. Portal hanya menerima data minimum izin yang sudah disetujui; lihat bagian 19.

### Rekomendasi penyederhanaan yang belum menjadi keputusan pengguna

**Diputuskan pengguna 16 November 2026: mengetahui tidak wajib, hanya pemberitahuan.** Satu persetujuan pemberi izin cukup untuk status `disetujui` dan surat final. Wakil menerima notifikasi AURA dan WhatsApp serta dapat membaca detail, tanpa tombol Ketahui, tanpa tahap, dan tanpa menghambat pegawai maupun penerbitan surat. Bagian 2, 6, 8, 9, 10, 12, 17, dan 19 sudah disesuaikan.

Saran efisiensi lainnya: memakai provider WhatsApp yang sudah ada; menunda pengingat lima menit dan laporan kompleks sampai dibutuhkan; pengelola menetapkan pengganti sesuai matriks tanpa sinkron kalender otomatis; rilis alur inti dahulu. Saran ini belum otomatis menghapus fitur di bagian rinci. Jangan membangun dua varian workflow sekaligus untuk menghindari keputusan.

Saran operasional: tetapkan pengelola utama/cadangan untuk kontak dan pejabat pengganti; susun prosedur izin lisan saat darurat dengan waktu kejadian dan waktu pencatatan yang terpisah; uji coba satu unit selama dua minggu. Pencatatan izin lisan belum termasuk perilaku yang disetujui, sehingga jangan menyediakan backdating tanpa kebijakan. Ukur waktu keputusan, kegagalan notifikasi, dan kebutuhan bantuan manual.

Keputusan 16 November 2026 (rincian di bagian 2): pegawai boleh keluar setelah pemberi izin menyetujui; mengetahui hanya pemberitahuan; izin lisan berupa skema di balik saklar yang mati; template lama dengan TTD basah ditambah keterangan AURA; pengelola awal admin AURA; izin Ketua di luar AURA (ruang lingkup hanya kantor). Diputuskan juga: jam kerja Senin–Kamis 08.00–16.30, Jumat 08.00–17.00 WITA; bila Ketua berhalangan, izin hakim dialihkan ke Wakil. Yang masih terbuka: penerimaan format di SIKEP. Claude dapat meneliti repo serta menyusun rencana sambil menandai pertanyaan ini, tanpa mengulang pilihan AURA versus RESTU.

## 1. Tujuan dan ukuran keberhasilan

Pegawai mengajukan izin keluar sementara atau pulang sebelum waktunya; pejabat berwenang memutuskan melalui akun sendiri; AURA menyimpan jejak keputusan dan menyediakan surat final serta register yang dapat ditelusuri. Berlaku setiap hari kerja, termasuk Jumat sore yang menjadi kebutuhan awal.

Hasil yang diharapkan:

- Satu permohonan mempunyai satu identitas, alur keputusan yang jelas, dan satu arsip final.
- Pemohon tidak memilih sendiri pemberi izin dan tidak dapat mencetak surat final tanpa proses persetujuan.
- Pimpinan dapat memantau permohonan tertunda dan rekap izin tanpa membuka percakapan pribadi.
- Pengelola dapat menyiapkan dokumen untuk diunggah manual ke SIKEP, sesuai format yang nantinya dikonfirmasi.
- Waktu pengajuan, keputusan, dan penerbitan tercatat untuk mengukur penggunaan serta waktu layanan. Data ini dapat dipakai sebagai bahan evaluasi inovasi; tidak menjanjikan pemenuhan penilaian WBK.

## 2. Keputusan, asumsi, dan hal yang harus dikonfirmasi

### Sudah dipilih pengguna

- Modul berada di AURA.
- Persetujuan dilakukan langsung di aplikasi.
- WhatsApp otomatis memberi pemberitahuan dan tautan ke AURA; keputusan tetap melalui akun AURA. Pengguna menekankan kebutuhan mendadak dan respons cepat.
- Daftar pegawai yang mendapat izin ditampilkan pada kotak hijau portal lokal; integrasi dijelaskan pada bagian 19.
- Claude mengerjakan implementasi; pekerjaan sesi ini hanya rancangan.
- Mengetahui hanya pemberitahuan, bukan tahap wajib (16 November 2026).
- Izin darurat/lisan: skema dibuat sekarang (waktu kejadian, waktu pencatatan, pencatat) di balik saklar `izin_pengaturan` yang default mati. Selama mati, opsi tidak tampil dan server menolak jam mundur.
- Surat memakai template `izin_keluar_kantor` yang sudah ada (sub_jenis pegawai/hakim, migrasi 018/036). Ruang TTD dibiarkan kosong untuk tanda tangan basah seperti sekarang, ditambah baris kecil “Disetujui melalui AURA pada [waktu]”. Tanpa gambar TTD, bukan TTE.
- Jam kerja: Senin–Kamis 08.00–16.30, Jumat 08.00–17.00 WITA.
- Ketua berhalangan: izin hakim dialihkan ke Wakil.
- Pengelola data modul (pejabat berhalangan, nomor WA, pemetaan kelompok) default admin AURA; petugas lain bisa ditambahkan lewat `izin_petugas`.
- Ruang lingkup hanya izin di lingkungan kantor (diputuskan pengguna 16 November 2026, menggantikan keputusan sebelumnya tentang izin Ketua lewat KPTA): izin Ketua tidak diproses AURA; pengajuan oleh Ketua ditolak dengan pesan. Status `diajukan_ke_pta` dan kolom `pemberi_eksternal` dihapus lewat migrasi 038.

### Arahan pimpinan dari percakapan

- Wakil dan hakim meminta izin kepada Ketua.
- Pegawai kepaniteraan meminta izin kepada Panitera, diketahui Wakil Ketua.
- Pegawai kesekretariatan meminta izin kepada Sekretaris, diketahui Wakil Ketua.
- Jika Panitera/Sekretaris cuti atau dinas luar, pegawai meminta izin langsung kepada Wakil.
- Jika Wakil juga cuti, permohonan dialihkan kepada Ketua.

### Usulan default untuk rancangan, konfirmasi sebelum aktivasi

| Topik | Usulan | Mengapa perlu konfirmasi |
|---|---|---|
| Wakil berhalangan sebagai penerima pemberitahuan | Pemberitahuan tetap ke Wakil saja; tidak dialihkan ke Ketua | Pemberitahuan tidak menghambat apa pun; cukup tercatat |
| Wakil dinas luar | Perlakukan sebagai berhalangan seperti cuti | Percakapan hanya menyebut Wakil cuti |
| Panitera/Sekretaris sebagai pemohon | Langsung kepada Wakil; jika berhalangan kepada Ketua | Belum disebut dalam arahan |
| Ketua berhalangan sebagai pemberi izin | Hakim → Wakil (diputuskan 16 November 2026). Wakil sebagai pemohon tetap diblokir karena tidak boleh menyetujui izinnya sendiri | Ketua dan Wakil sama-sama berhalangan: blokir dengan pesan |
| Surat final | PDF arsip dengan identitas pejabat, waktu keputusan, dan ID permohonan; DOCX hanya salinan kerja jika diperlukan | Format SIKEP dan kebutuhan tanda tangan perlu dipastikan |
| Pembatalan setelah final | Hanya pemberi izin pada permohonan tersebut yang dapat mencabut, dengan alasan | Kebijakan pencabutan belum disebut |
| Permohonan mendadak | Sediakan pilihan “Mulai sekarang”; permohonan yang menunggu tetap dapat diputus pada hari izin meski jam mulai terlewati, dengan waktu keputusan sebenarnya | Pengguna menghendaki respons cepat; ini belum menetapkan bahwa pegawai boleh meninggalkan kantor sebelum diizinkan |
| Permohonan retrospektif | Pengajuan baru dengan jam mulai lampau masih ditolak; pengecualian izin yang sudah disampaikan lisan menunggu kebijakan | Kebutuhan mendadak tidak otomatis mengizinkan backdating |

Claude boleh menyiapkan implementasi dan pengujian memakai default ini, dengan penanda jelas dalam laporan. Jangan menyatakan default sebagai keputusan pimpinan. Sebelum penggunaan nyata, minta konfirmasi terbundel atas kebijakan yang masih terbuka dan contoh surat final. Mengetahui sudah diputuskan sebagai pemberitahuan saja; jangan menambah mesin workflow yang dapat dikonfigurasi untuk menghidupkannya kembali.

## 3. Bukti kondisi aplikasi saat diperiksa

Pemeriksaan baca saja pada 16 November 2026 melalui SSH. Tidak melakukan transaksi uji, tidak membaca isi tabel pegawai, dan tidak membuktikan status migrasi database maupun cron yang sedang aktif.

| Lokasi pada VPS | Temuan yang relevan |
|---|---|
| `aura/composer.json` | PHP >=8.1, PHPWord, autoload namespace `Aurat\\` |
| `aura/src/Auth.php` | Sesi menyimpan `user_id` dan `peran`; peran pengguna/pengelola/admin. Whitelist pengguna memuat `izin_keluar_kantor`. Login belum menyimpan NIP dalam sesi |
| `aura/db/030_sync_akun_dari_restu.sql`, `DEPLOY.md` | Tersedia rancangan sinkron akun berbasis NIP dari RESTU; implementer harus memeriksa skema dan hasil sinkron aktual |
| `aura/cron/sync_pegawai_dari_restu.php` | Tersedia mekanisme sinkron data dari RESTU; jangan mengubahnya menjadi sinkron dua arah |
| `aura/surat/index.php:315` | Ledger dicatat sebelum generator dipanggil; kegagalan ledger tidak menghentikan unduhan |
| `aura/src/Surat/SuratDiterbitkanRepository.php` | Menyimpan nilai formulir, nomor, tanggal, pembuat; catatan bisa dihapus lewat halaman admin |
| `aura/src/DocxGenerator.php` | Ada `generate()` yang membuat berkas, selain metode generate dan unduh; manfaatkan ini sebelum membuat generator baru |
| `aura/db/018_izin_keluar_kantor.sql` | Jenis surat izin, subjenis pegawai/hakim, peran pemberi/penerima/mengetahui |
| `aura/db/036_izin_keluar_format_lokal.sql` | Format pegawai lokal memiliki blok mengetahui; variabel tanggal/jam izin eksplisit ada pada varian hakim, belum pada daftar variabel pegawai di migrasi ini |
| `restu/includes/cuti.php`, `user/approve_cuti.php` | Ada pola approval jabatan, Plh, notifikasi, dan audit; cukup referensi, jangan menyalin aturan cuti atau mengubah RESTU |
| Nginx AURA | Root web adalah root repo dan ada whitelist PHP; endpoint baru perlu aturan eksplisit |

README lama RESTU menyatakan sebagian modul belum dibuat, sedangkan kode yang diperiksa sudah memuatnya. Utamakan kode dan verifikasi runtime, bukan status dokumentasi lama. Nama migrasi maupun komentar bukan bukti bahwa konfigurasi produksi sama persis.

## 4. Alternatif dan keputusan penempatan

1. **RESTU:** fondasi approval sudah ada, tetapi pengguna memilih agar layanan persuratan izin tetap di AURA.
2. **AURA hanya cetak dan arsip:** perubahan lebih kecil, tetapi tidak memenuhi pilihan persetujuan langsung dalam aplikasi.
3. **AURA dengan modul pengajuan tersendiri — dipilih:** menggunakan akun, data pegawai, layout, serta template yang ada; menambahkan status, routing, keputusan, dan arsip yang wajib tercatat.

Jangan membuat aplikasi ketiga, framework baru, atau engine workflow generik. Modul khusus izin cukup. RESTU tetap sumber sinkron data yang sudah tersedia; keputusan izin disimpan di AURA tanpa bergantung pada panggilan RESTU saat setiap approval.

## 5. Batas versi pertama

Termasuk: pengajuan mandiri, draf, dua jenis izin, routing jabatan, pencatatan pejabat berhalangan, persetujuan/penolakan, pemberitahuan mengetahui kepada Wakil, pembatalan/pencabutan, notifikasi dalam aplikasi dan WhatsApp otomatis melalui integrasi yang diverifikasi, PDF final, register, ekspor CSV terbatas, audit, proteksi jalur surat lama, dan integrasi ringkasan izin di portal lokal.

Tidak termasuk: pengurangan saldo cuti, perubahan presensi/potongan tunjangan, unggah SIKEP otomatis, tanda tangan elektronik tersertifikasi, WhatsApp gateway baru, aplikasi mobile, pelacakan lokasi, bukti kembali lewat GPS, dan migrasi riwayat lama menjadi izin yang seolah sudah disetujui.

Tidak perlu unggahan lampiran pada versi pertama. Lampiran dan pencatatan jam kembali aktual dapat ditambahkan setelah ada kebutuhan yang disepakati. Jam kembali yang diminta sekarang adalah rencana, bukan bukti kehadiran.

## 6. Aktor dan kewenangan

Peran aplikasi (`pengguna/pengelola/admin`) berbeda dari kewenangan jabatan. Menjadi admin tidak otomatis berhak memberi izin.

| Aktor | Kewenangan |
|---|---|
| Pemohon | Membuat dan mengubah draf sendiri; mengajukan; melihat milik sendiri; membatalkan sebelum final; mengunduh arsip sendiri |
| Pemberi izin yang ditugaskan | Melihat permohonan yang ditugaskan; setuju/tolak saat gilirannya; mencabut sesuai kebijakan |
| Pejabat mengetahui | Menerima pemberitahuan dan dapat membaca detail permohonan terkait; tidak ada aksi |
| Pengelola izin | Register seluruh unit, ekspor, pemulihan penerbitan gagal, pengalihan tugas dengan alasan sesuai aturan; tidak menyetujui atas nama pejabat |
| Admin | Mengatur pemetaan dan akses pengelola izin, pejabat berhalangan, konfigurasi modul; tidak memalsukan keputusan |

Usulan: akses register penuh diberikan secara eksplisit melalui daftar petugas modul, tidak otomatis kepada semua pengelola surat. Otorisasi diperiksa pada server untuk setiap detail, aksi, unduhan, dan ekspor.

Identitas pemohon diambil dari `user_login` berdasarkan sesi lalu dicocokkan dengan NIP pegawai aktif. NIP kosong/ganda/tidak cocok menolak pengajuan dengan petunjuk menghubungi pengelola; jangan memberi pemilih nama bebas. Akun nonaktif kehilangan akses aksi meskipun sesi lama masih ada.

## 7. Formulir dan aturan waktu

- Identitas otomatis: nama, NIP, jabatan, unit, kelompok rute.
- Jenis: `keluar_sementara` atau `pulang_awal`.
- Tanggal izin, jam mulai, alasan wajib; alasan 10–2.000 karakter sebagai usulan batas validasi.
- Sediakan pilihan “Mulai sekarang”; waktu mulai ditetapkan server saat submit, bukan saat form pertama dibuka. Ini menghindari penolakan akibat waktu mengetik formulir. Untuk jam yang dipilih manual, tolak waktu yang telah lewat saat submit sesuai kebijakan nonretrospektif.
- Keluar sementara: rencana jam kembali wajib, lebih besar dari jam mulai, hari yang sama.
- Pulang awal: tidak meminta jam kembali; interval dianggap sampai akhir hari kerja menurut jadwal lokal yang dikonfigurasi.
- Tanggal surat ditetapkan saat finalisasi, tidak bebas dipilih pemohon. Tanggal izin tetap tercantum terpisah.
- Tidak hardcode Jumat. Pengelola mengatur jadwal jam kerja lokal per hari dan tanggal pengecualian secara sederhana; konfirmasi jam kantor sebelum aktivasi.
- Tolak tanggal nonkerja atau waktu di luar jadwal yang dikonfigurasi. Jangan menebak kalender libur nasional maupun menambah layanan API kalender untuk versi pertama.
- Tampilkan rute hasil perhitungan server sebelum submit. Pemohon tidak dapat menggantinya lewat POST.
- Blokir interval tumpang tindih untuk pemohon yang sama pada status menunggu atau disetujui; interval berdampingan boleh. Draf/ditolak/dibatalkan/dicabut tidak memblokir.
- Simpan timestamp kejadian dalam UTC, tampilkan dengan zona kantor yang dikonfigurasi. Konfirmasikan zona PA Rantau; **jangan mengambil Asia/Makassar dari perangkat pengguna sebagai zona kantor**. Nilai tanggal dan jam izin adalah waktu lokal kantor, dengan zona tersimpan pada snapshot.

Jam mulai yang lewat selama menunggu tidak otomatis mengubah status atau memaksa pemohon membuat ulang permohonan. Usulan untuk kebutuhan mendadak: keputusan tetap dapat dicatat pada hari izin, dengan penanda “Jam mulai yang dimohon telah lewat” dan catatan wajib jika seluruh interval izin sudah lewat. Waktu pengajuan, interval yang dimohon, dan waktu keputusan ditampilkan terpisah; jangan memundurkan timestamp atau menyatakan izin telah diberikan sebelum keputusan sebenarnya. Setelah tanggal izin berakhir, blokir persetujuan baru sampai kebijakan retrospektif ditentukan; penolakan/pembatalan tetap boleh. Retry penerbitan untuk izin yang sudah sah berstatus disetujui tidak diblokir oleh waktu yang lewat. Default ini perlu konfirmasi pimpinan sebelum aktivasi.

## 8. Routing persetujuan

Pemetaan menggunakan kode kelompok eksplisit: `hakim`, `wakil`, `kepaniteraan`, `kesekretariatan`, `panitera`, `sekretaris`, `ketua`. Jangan mencocokkan substring jabatan atau nama orang.

| Kelompok | Kondisi | Pemberi izin | Pemberitahuan mengetahui |
|---|---|---|---|
| Hakim/Wakil | Ketua tersedia | Ketua: setuju/tolak | Tidak ada |
| Hakim | Ketua berhalangan; Wakil tersedia | Wakil: setuju/tolak | Tidak ada |
| Wakil | Ketua berhalangan | Blokir; Wakil tidak menyetujui izinnya sendiri | — |
| Kepaniteraan | Panitera tersedia | Panitera: setuju/tolak | Wakil |
| Kesekretariatan | Sekretaris tersedia | Sekretaris: setuju/tolak | Wakil |
| Kepaniteraan/Kesekretariatan | Pemberi normal berhalangan; Wakil tersedia | Wakil: setuju/tolak | Tidak ada, Wakil sudah memutus sendiri |
| Kepaniteraan/Kesekretariatan | Pemberi normal dan Wakil berhalangan | Ketua: setuju/tolak | Tidak ada |
| Panitera/Sekretaris | Wakil tersedia | Wakil: setuju/tolak, usulan | Tidak ada |
| Panitera/Sekretaris | Wakil berhalangan | Ketua: setuju/tolak, usulan | Tidak ada |
| Ketua | Selalu | Ketua sendiri: langsung disetujui dan ditandatangani sendiri (keputusan 17 November 2026) | — |
| Kelompok tidak dipetakan | Belum ada kebijakan | Blokir submit, jelaskan konfigurasi belum lengkap | — |

Tidak ada auto-approve bagi jabatan puncak. Tidak boleh menyetujui permohonan sendiri. Jika kandidat akhir kosong, nonaktif, tidak punya akun pegawai yang valid, atau sama dengan pemohon, blokir proses dan tampilkan alasan kepada pengelola; jangan melewati tahap diam-diam.

### Pejabat berhalangan dan pengalihan

- Admin mencatat pejabat, jenis berhalangan (cuti/dinas luar/lainnya yang disetujui), mulai/selesai, dan catatan. Tidak membaca kalender RESTU otomatis pada versi pertama.
- Usulan penentuan awal: gunakan ketersediaan saat permohonan dikirim karena yang dibutuhkan adalah pejabat yang dapat mengambil keputusan saat itu. Tanggal izin tetap ditampilkan. Konfirmasi kebijakan ini sebelum aktivasi.
- Simpan snapshot rute ketika submit. Perubahan jabatan/berhalangan tidak mengubah sejarah atau mengganti penerima secara diam-diam.
- Pada setiap aksi, periksa ulang akun aktif, kewenangan jabatan, dan ketersediaan sekarang. Jika tidak lagi memenuhi, hentikan aksi dan minta pengelola melakukan pengalihan tugas tertunda.
- Pengalihan memakai kandidat dari matriks, bukan pemilih semua pegawai. Wajib alasan, audit, notifikasi penerima baru; penerima lama kehilangan hak aksi.
- Tahap yang sudah diputus tidak diubah.
- Jika berhalangan berakhir, jangan otomatis menarik tugas dari penerima pengganti. Pengelola dapat mengevaluasi ulang secara eksplisit.

## 9. Status dan transisi

Pisahkan status permohonan dari status berkas agar kegagalan konversi PDF tidak menghilangkan keputusan pejabat.

| Status permohonan | Aksi valid | Hasil |
|---|---|---|
| `draf` | Ubah / ajukan / batalkan oleh pemohon | `draf` / `menunggu_persetujuan` / `dibatalkan` |
| `menunggu_persetujuan` | Setuju / tolak oleh pemberi; batalkan oleh pemohon | `disetujui` (sekaligus antre pemberitahuan mengetahui bila ada); `ditolak`; `dibatalkan` |
| `disetujui` | Terbitkan/unduh berkas; cabut oleh pemberi sesuai kebijakan | Status tetap; atau `dicabut` |
| `ditolak`, `dibatalkan`, `dicabut` | Lihat riwayat; salin sebagai draf baru | Permohonan lama tetap terminal |

Sesudah submit, isi permohonan terkunci. Koreksi menjadi permohonan baru dengan `asal_permohonan_id`; keputusan lama tidak dibawa sebagai persetujuan baru. Penolakan, pembatalan, pengalihan, dan pencabutan memerlukan alasan. Tidak menyediakan hapus permanen melalui UI modul, termasuk untuk admin.

Status berkas: `belum_dibuat`, `diproses`, `siap`, `gagal`. UI membedakan “Izin disetujui, surat sedang disiapkan” dari “Surat siap diunduh”.

### Transaksi dan klik bersamaan

Setiap perubahan: mulai transaksi → kunci permohonan dan tahap terkait → periksa status, versi, aktor, waktu dan izin → tulis keputusan + audit + notifikasi + pekerjaan WhatsApp ke outbox → commit. Jika audit atau pencatatan outbox gagal, transaksi batal; pengguna melihat kegagalan dan dapat mencoba ulang. Panggilan jaringan WhatsApp dilakukan worker setelah commit, tidak di dalam transaksi atau request persetujuan. Gangguan provider tidak membatalkan keputusan yang telah tersimpan.

Gunakan nomor versi untuk mendeteksi layar lama. Klik ganda tidak membuat keputusan, nomor, maupun dokumen ganda. Approval bersamaan dengan pembatalan hanya boleh menghasilkan satu transisi sah; tampilkan hasil terbaru kepada pihak yang kalah. Pada submit, serialisasikan pengecekan bentrok per pegawai (misalnya lock baris pegawai) agar dua permohonan bersamaan tidak lolos pemeriksaan interval.

## 10. Struktur data yang diusulkan

Ini skema logis; Claude menentukan tipe SQL, indeks rinci, dan penamaan yang konsisten setelah memeriksa skema aktual. Gunakan InnoDB/FK bila kompatibel; referensi data historis tidak boleh memakai cascade delete. Semua data inti di DB AURA.

| Tabel | Kolom/isi utama |
|---|---|
| `izin_pengaturan` | Aktivasi modul, zona kantor, jadwal mingguan dan pengecualian tanggal, versi kebijakan; satu konfigurasi aktif |
| `izin_petugas` | `user_id`, kapabilitas pengelola izin; unique pengguna/kapabilitas |
| `izin_pemetaan_pegawai` | `pegawai_id` unique, kelompok routing, aktif, pembaru dan waktu |
| `izin_pejabat` | Kode jabatan Ketua/Wakil/Panitera/Sekretaris, pegawai pemegang, periode berlaku; cegah periode bertumpuk untuk jabatan yang sama |
| `izin_berhalangan` | Pegawai pejabat, waktu mulai/akhir, jenis, catatan, pembuat, pembatalan catatan; riwayat dipertahankan |
| `izin_permohonan` | ID internal, kode permohonan unique, pemohon user/pegawai, snapshot identitas/unit/kelompok/zona, jenis, tanggal, jam mulai/kembali/akhir interval, alasan, status, versi, asal permohonan, waktu dibuat/diajukan/final, versi kebijakan |
| `izin_tahap` | Permohonan, pejabat pemberi pegawai/user, snapshot nama/jabatan, status `menunggu/selesai/ditolak/diganti`, keputusan, catatan, waktu, referensi tahap yang diganti; hanya satu tugas aktif per permohonan. Pejabat mengetahui cukup disimpan sebagai snapshot nama/jabatan/user nullable di `izin_permohonan` |
| `izin_audit` | Permohonan nullable untuk perubahan konfigurasi, aktor user dan snapshot identitas, aksi, status sebelum/sesudah, data perubahan yang relevan, alasan, waktu; append-only pada aplikasi |
| `izin_notifikasi` | Penerima user, permohonan, peristiwa, pesan ringkas, dibuat/dibaca; unique peristiwa/penerima untuk deduplikasi |
| `izin_wa_outbox` | ID peristiwa, penerima user, nomor tujuan tervalidasi, kategori pesan, permohonan/tahap, status antre/diproses/diterima_provider/gagal/tidak_pasti/dibatalkan, jumlah percobaan, jadwal retry, lease, provider message ID, kode error aman; unique peristiwa/penerima/kanal |
| `izin_dokumen` | Permohonan unique untuk final v1, nomor register unique, status berkas, template ID/versi/hash, snapshot data render, path privat DOCX/PDF, hash PDF, waktu terbit, error internal ringkas, lease pemrosesan |

Indeks minimal: pemohon/tanggal, status/tanggal, tahap penerima/status, audit permohonan/waktu, notifikasi penerima/dibaca. Snapshot nama/jabatan dan template mencegah surat lama berubah ketika data pegawai disinkron ulang.

Pengaturan kelompok, jabatan, dan kewenangan izin dipisahkan dari kolom yang ditulis sinkron RESTU. Jangan mengandalkan komentar lama `Auth.php` tentang tidak ada relasi pegawai; periksa NIP aktual dari migrasi 030.

## 11. Surat final, format, dan penerbitan

### Isi surat

Gunakan dua varian template yang sudah dikenal, pegawai dan hakim, dengan versi baru yang mempertahankan tata letak lokal. Pemilihan varian berasal dari kelompok pemohon di server; Wakil menggunakan varian hakim sebagai usulan yang harus dicek pada contoh keluaran.

Keduanya wajib menampilkan identitas pemohon, jenis izin, tanggal dan rentang waktu, alasan, identitas pemberi, waktu persetujuan, ID/nomor register, serta nama/jabatan pejabat mengetahui bila ada (tanpa waktu konfirmasi, karena mengetahui hanya pemberitahuan). Pulang awal menyatakan “pulang sebelum waktu kerja berakhir”; jangan menampilkan jam kembali fiktif.

Varian pegawai yang terdaftar dalam migrasi 036 belum memiliki variabel tanggal/jam izin eksplisit seperti hakim. Tambahkan variabel dan contoh hasilnya; jangan hanya memasukkan waktu ke teks alasan. Blok mengetahui disembunyikan atau diberi penjelasan “tidak diperlukan karena izin diberikan langsung oleh Wakil/Ketua” sesuai contoh yang disetujui, tanpa mengarang tanda tangan kedua.

Persetujuan akun dicetak sebagai keterangan keputusan elektronik beserta nama/jabatan/waktu. Jangan memasang gambar tanda tangan tanpa dasar/persetujuan atau menyebutnya TTE tersertifikasi. Penerimaan format di SIKEP belum diverifikasi. Claude menyiapkan contoh PDF untuk konfirmasi; jika diperlukan penandatanganan tambahan, itu keputusan ruang lingkup sebelum produksi.

### Penyimpanan dan kegagalan

1. Setelah status `disetujui`, buat satu record dokumen dan reservasi nomor register dengan unique constraint. Usulan nomor internal: `IZN/{tahun}/{urutan}`; ini bukan klaim format nomor surat resmi. Jika kantor membutuhkan nomor surat resmi, petakan aturan nomor AURA setelah dikonfirmasi.
2. Bekukan versi/berkas template dan semua nilai render pada finalisasi. Simpan salinan template yang diperlukan di penyimpanan privat agar retry tidak bergantung pada template aktif yang berubah.
3. Pakai `DocxGenerator::generate()` untuk menghasilkan DOCX ke berkas sementara privat, lalu konversi PDF. Periksa signature/hasil metode aktual sebelum pemakaian.
4. PDF dipilih sebagai arsip final. Periksa kemampuan konversi yang sudah ada di VPS; jangan menganggap PHPWord otomatis menghasilkan PDF. Jika tidak tersedia, buktikan pilihan converter di staging, batas waktu/memori, dan kualitas tata letak sebelum menambah layanan/paket produksi. Keputusan dependency diserahkan kepada Claude dengan alasan dan hasil uji.
5. Batasi satu worker/penerbitan bersamaan sebagai awal pada VPS kecil. Versi pertama boleh memakai proses sinkron dengan timeout dan tombol retry pengelola; tidak wajib Redis/queue baru. Gunakan lease agar proses yang mati dapat dicoba ulang.
6. Tulis berkas sementara, verifikasi hasil dan hash, pindahkan secara atomik ke path final, baru tandai `siap`. Status `siap` mensyaratkan berkas final tersedia. Bila commit gagal setelah berkas dipindah, retry merekonsiliasi berdasarkan record/hash; tidak menerbitkan nomor baru. Bersihkan berkas sementara yatim secara terukur.
7. Kegagalan generator/konversi membuat status berkas `gagal`; keputusan tetap disimpan. Retry memakai snapshot dan nomor yang sama. Jangan membagikan dokumen final sebelum metadata dan berkas konsisten.
8. Unduhan berulang menyajikan berkas arsip yang sama, bukan generate ulang. Arsip historis tetap tersedia untuk pihak berwenang setelah pencabutan dengan label tegas pada halaman; jangan mengganti berkas lama diam-diam. Berkas yang sudah diunduh tidak dapat ditarik kembali, sehingga pemeriksa harus melihat status terkini di AURA.

Penyimpanan berada di luar document root bila memungkinkan. Semua unduhan melewati pemeriksaan akses. Tidak perlu halaman verifikasi publik atau QR publik pada v1; ID permohonan dapat dicari oleh pengguna berwenang di AURA. Jangan menampilkan NIP/alasan di URL.

### Hubungan dengan riwayat surat lama

Gunakan register `izin_permohonan`/`izin_dokumen` sebagai sumber resmi izin baru. Jangan memasukkan salinan izin baru ke ledger generik yang dapat dihapus atau menampilkan alasan kepada seluruh pengelola surat. Menu riwayat surat dapat memberi tautan menuju register izin yang aksesnya diperiksa ulang.

Riwayat izin lama tetap berlabel “surat dibuat melalui alur lama; persetujuan tidak tercatat di modul ini”. Tidak ada backfill status `disetujui`, nama approver, atau timestamp keputusan yang direkayasa.

## 12. Halaman dan interaksi

Pertahankan bahasa, layout, komponen, dan CSS AURA; ini bukan proyek redesign.

| Halaman usulan | Isi/tindakan |
|---|---|
| `izin/index.php` | Izin saya, status, tanggal/jam, tombol buat; tautan antrean tugas bila ada |
| `izin/form.php` | Draf, identitas otomatis, jenis/tanggal/waktu/alasan, pratinjau rute, ajukan |
| `izin/detail.php` | Ringkasan, status, urutan tahap dan waktu, alasan penolakan/pembatalan, tindakan sesuai hak, status surat |
| `izin/tugas.php` | Antrean “Berikan izin” untuk pengguna aktif |
| `izin/aksi.php` | POST untuk transisi; CSRF dan seluruh validasi server |
| `izin/unduh.php` | GET baca arsip; tidak boleh sekaligus memicu penerbitan atau perubahan status |
| `admin/izin_register.php` | Filter tanggal/status/jenis/unit/pemohon; paginasi; jumlah dan ekspor sesuai filter |
| `admin/izin_pengaturan.php` | Pemetaan kelompok/pejabat, petugas, kalender sederhana, konfigurasi modul |
| `admin/izin_berhalangan.php` | Periode berhalangan dan daftar tugas yang perlu dialihkan |

Nama file boleh disesuaikan pola repo. Penerbitan/retry dan perubahan penanda notifikasi dibaca adalah POST. Badge antrean berasal dari DB; v1 cukup diperbarui saat halaman dibuka, tidak perlu WebSocket.

Notifikasi: pengajuan → pemberi; disetujui → pemohon dan pejabat mengetahui; ditolak → pemohon; pembatalan/pencabutan → pihak terkait; pengalihan → penerima baru. Pesan tidak memuat alasan pribadi; tautan detail tetap terlindungi.

### WhatsApp untuk kebutuhan mendadak

- WhatsApp otomatis termasuk versi pertama. Notifikasi dalam AURA tetap sumber daftar tugas dan status; WhatsApp tidak menjadi kanal persetujuan. Tidak ada keputusan melalui balasan pesan, tombol approve dalam URL, atau tautan yang melewati login.
- Periksa `restu/includes/whatsapp.php` dan cara pemanggilannya, provider, sumber nomor telepon, batas pengiriman, serta kemampuan idempotensi/status. Keberadaan kode belum membuktikan gateway aktif. Jangan menjalankan tes pengiriman ke pegawai saat investigasi. Kredensial tetap di konfigurasi privat; jangan menyalin bootstrap RESTU atau menulis ke DB RESTU. Gunakan adapter kecil AURA untuk provider yang sama jika memang dapat dipakai.
- Nomor pegawai/pejabat harus bersumber dari data yang diverifikasi pengelola dan dinormalisasi sesuai kebutuhan provider. Jika belum ada kolom/sinkron nomor yang sesuai, simpan kontak khusus modul; jangan menebak nomor. Nomor hilang atau tidak valid tidak menggagalkan pengajuan: catat notifikasi tidak terkirim dan tampilkan kepada pemohon/pengelola agar dapat menghubungi pejabat langsung.
- Setelah commit, worker ringan membaca outbox dari DB. Usulan awal cron setiap satu menit, dengan lock agar tidak bertumpuk dan batas batch. Sasaran antrean mulai diproses kurang lebih satu menit pada kondisi normal, bukan jaminan pesan sampai/dibaca. Tidak perlu Redis atau layanan antrean baru. Ukur latensi aktual pada staging dan sesuaikan bila belum memadai.
- Pesan pengajuan dan pemberitahuan mengetahui berisi nama pemohon, jenis izin, tanggal/jam, tindakan yang diperlukan, serta tautan HTTPS ke detail AURA. Alasan pribadi, NIP, berkas PDF, dan kredensial tidak dikirim. Contoh: “AURA: [Nama] mengajukan izin pulang awal hari ini pukul [jam]. Mohon tinjau: [tautan AURA].”
- Sesudah login, pengguna kembali ke detail yang dituju melalui redirect internal yang divalidasi. Halaman detail menonjolkan ringkasan dan tombol keputusan sesuai tahap agar cepat digunakan di ponsel; tidak perlu membuka register terlebih dahulu.
- Kirim kepada pemberi pada submit, pejabat mengetahui saat disetujui, pemohon saat disetujui/ditolak, dan pejabat pengganti saat dialihkan. Pembatalan/pencabutan memberi kabar kepada pihak terkait. Jangan menggandakan pesan jika penerimanya orang yang sama.
- Retry hanya untuk kegagalan sementara yang diketahui aman, usulan jeda 1, 3, lalu 10 menit dan maksimal empat percobaan total. Nomor invalid/auth provider gagal tidak diulang tanpa perbaikan. Gunakan idempotency key provider bila tersedia. Jika timeout terjadi setelah request mungkin diterima, tandai `tidak_pasti` dan periksa status provider sebelum retry; tanpa dukungan status/idempotensi jangan menjanjikan exactly-once atau mengirim ulang secara buta.
- Sebelum setiap pengiriman/retry, cek bahwa tugas masih aktif dan penerimanya masih berhak. Batalkan pesan tugas lama jika sudah diputus, dibatalkan, dialihkan, atau batas hari izin terlewati. Pesan status akhir tetap boleh dikirim sesudahnya. Jangan kirim pengingat kedaluwarsa setelah gateway pulih.
- UI membedakan antre, diterima provider, gagal, dan tidak pasti. “Diterima provider” bukan “dibaca pejabat”. Status delivered/read hanya ditampilkan jika ada bukti provider; bila memakai webhook, validasi autentisitas dan deduplikasi callback. Webhook tidak wajib pada versi pertama jika provider tidak mendukung.
- Usulan pengingat: satu kali setelah lima menit untuk tahap aktif pada permohonan hari ini, apabila belum ada keputusan dan pemberitahuan awal sudah diterima provider. Ini pengingat kepada pejabat yang sama, bukan pengalihan kewenangan otomatis. Waktu lima menit perlu konfirmasi; tidak menambah eskalasi persetujuan otomatis atau pengiriman berulang tanpa batas.
- Bila WhatsApp gagal atau kebutuhan sangat mendesak, tampilkan bahwa pemohon dapat menghubungi pejabat langsung sambil keputusan tetap dicatat di AURA. Notifikasi tidak berarti izin sudah diberikan.
- Pengujian memakai adapter palsu dan nomor uji yang disetujui secara eksplisit. Pengujian otomatis tidak mengirim ke nomor produksi. Pemasangan worker/kredensial produksi termasuk tahap deployment yang memerlukan otorisasi.

Rekap menampilkan jumlah per status/jenis/unit dan waktu dari submit ke keputusan final. Durasi hanya dihitung untuk permohonan yang benar-benar final, bukan menganggap pending bernilai nol. Ekspor CSV mengikuti filter dan otorisasi, melindungi formula spreadsheet, serta tidak memuat alasan secara default. V1 tidak memerlukan grafik.

## 13. Proteksi jalur lama dan batas akses

- Saat modul aktif, GET menu/tautan `surat/index.php?kode=izin_keluar_kantor` diarahkan ke modul baru. POST generate jenis ini ditolak server-side untuk semua peran, termasuk admin.
- Cari semua endpoint generator lain, prefill, dan rute template yang bisa menghasilkan izin; penutupan tidak cukup hanya menghapus tombol. Unduhan template mentah oleh admin tetap fungsi administrasi, bukan arsip izin yang disetujui.
- Jenis surat lain tetap memakai jalur lama. Jangan mengganti perilaku ledger secara global untuk menyelesaikan masalah izin.
- CSRF pada semua mutasi, prepared SQL, escape output, validasi ID dan status, pembatasan path unduh, serta penolakan akses objek milik orang lain.
- Perubahan pemetaan/berhalangan diaudit. Audit tidak memiliki endpoint edit/hapus; ini bukan jaminan kebal manipulasi oleh administrator database.
- Nginx hanya membuka endpoint baru yang diperlukan; jangan membuka seluruh folder PHP atau storage. DB, template privat, konfigurasi, log, backup, dan berkas sementara tidak dapat diakses langsung lewat HTTP.

## 14. Susunan implementasi dan reuse

Usulan tanggung jawab di `src/Izin/`:

- Repository permohonan/tahap untuk query dan locking.
- Service routing untuk matriks dan pejabat berhalangan.
- Service transisi untuk otorisasi, state machine, audit, dan notifikasi dalam satu transaksi.
- Service dokumen untuk snapshot, generator yang sudah ada, konversi, penyimpanan, dan retry.

Pisahkan fungsi bila membantu pengujian; tidak wajib satu class per tabel. Halaman PHP tipis memanggil service dan layout yang sudah ada. Hindari menyalin seluruh `surat/index.php` atau seluruh `restu/includes/cuti.php`.

Ekstraksi kode generator hanya jika diperlukan; `DocxGenerator::generate()` sudah ada. Perubahan `Auth.php` dibatasi resolver identitas/hak modul bila memang belum ada helper. Jangan mengubah skema login atau sinkron sandi untuk fitur ini.

Nomor migrasi berikutnya ditentukan dari repository AURA aktual. `036` adalah berkas terakhir yang terlihat saat pemeriksaan, bukan jaminan nomor migrasi berikutnya.

## 15. Urutan kerja untuk Claude

1. Baca instruksi repo AURA, status git, kode/skema aktif, pemetaan NIP, template aktual, Nginx, serta konversi dokumen yang tersedia. Catat perbedaan dengan dokumen ini. Jangan mencetak rahasia atau data pribadi produksi dalam laporan.
2. Susun rencana implementasi per bagian dari rancangan ini. Daftar asumsi kebijakan tetap terlihat; tidak perlu mengulang diskusi pilihan RESTU/AURA.
3. Siapkan database dan akun sintetis lokal/staging. Tambahkan migrasi aditif, konfigurasi nonaktif, routing, dan state machine beserta pengujian inti.
4. Bangun halaman pengajuan, antrean keputusan/mengetahui, register, pengaturan, notifikasi dalam aplikasi, adapter WhatsApp, outbox dan worker dengan UI yang sudah ada. Verifikasi reuse provider RESTU dan uji dengan adapter palsu sebelum pengiriman ke nomor uji yang diizinkan.
5. Bangun arsip DOCX/PDF, idempotensi dan retry; tutup jalur izin lama ketika modul aktif. Tinjau contoh kedua varian surat.
6. Jalankan acceptance matrix dan regresi jenis surat lain. Siapkan bukti hasil, screenshot alur, contoh PDF sintetis, serta daftar file/migrasi/dependency yang berubah.
7. Minta keputusan atas butir kebijakan terbuka dan persetujuan deployment atas hasil yang konkret. Penugasan rancangan ini tidak mengizinkan deployment produksi.
8. Setelah deployment diizinkan: backup konsisten DB AURA + template + arsip privat + konfigurasi relevan; buktikan restore di lokasi terpisah. Terapkan migrasi, whitelist endpoint, lalu aktivasi setelah pemetaan valid. Jalankan smoke test terkontrol tanpa memalsukan izin pegawai.

## 16. Migrasi, aktivasi, dan rollback

- Semua tabel baru aditif; tidak menghapus surat/akun lama. Jangan memigrasi arsip lama menjadi pengajuan baru secara otomatis.
- Sebelum aktivasi, validasi semua pegawai yang akan memakai modul memiliki identitas dan kelompok benar, pejabat aktif mempunyai akun, dan format serta kalender/zona sudah disetujui.
- Gunakan tiga mode sederhana: `nonaktif` sebelum peluncuran; `aktif`; `pemeliharaan` setelah peluncuran bila bermasalah.
- `pemeliharaan` menghentikan pengajuan/keputusan/penerbitan baru, mempertahankan akses baca arsip, dan **tetap menutup generator izin lama**. Jangan memakai mematikan fitur untuk membuka kembali jalur tanpa persetujuan.
- Rollback aplikasi dilakukan dengan build kompatibel yang mempertahankan akses baca modul atau halaman pemeliharaan dan guard generator lama. Jangan kembali begitu saja ke build lama yang membypass approval.
- Jangan drop tabel baru atau restore backup lama di atas transaksi izin yang sudah masuk. Pertahankan data; pulihkan kode/dependency, kemudian rekonsiliasi dokumen `diproses/gagal` dari snapshot.
- Masukkan DB dan storage privat ke cakupan backup yang benar-benar teruji. Keberadaan cron backup lama bukan bukti bahwa arsip modul baru sudah terlindungi.

## 17. Acceptance matrix wajib

| ID | Skenario | Hasil yang harus dibuktikan |
|---|---|---|
| A01 | Hakim/Wakil mengajukan | Hanya Ketua yang berhak memutus; tidak ada tahap mengetahui |
| A02 | Kepaniteraan normal | Panitera setuju, langsung `disetujui` dan surat final; Wakil hanya menerima pemberitahuan tanpa aksi |
| A03 | Kesekretariatan normal | Sekretaris setuju, langsung `disetujui`; Wakil menerima pemberitahuan |
| A04 | Panitera/Sekretaris berhalangan | Wakil menjadi pemberi; satu keputusan cukup |
| A05 | Pemberi normal dan Wakil berhalangan | Ketua menjadi pemberi; satu keputusan cukup |
| A06 | Wakil berhalangan, pemberi normal hadir | Persetujuan Panitera/Sekretaris tetap berjalan; pemberitahuan ke Wakil tidak menghambat |
| A07 | Ketua kosong/berhalangan, pemetaan ambigu | Tidak auto-approve atau fallback ke orang sembarang |
| A07b | Ketua mengajukan | Langsung disetujui atas nama Ketua sendiri; tanpa Wakil, tanpa WA tugas; surat terbit |
| A07c | Saklar izin lisan mati | Opsi tidak tampil; POST dengan waktu kejadian lampau ditolak server |
| A08 | Pemohon memalsukan NIP, pejabat, kelompok, status POST | Ditolak atau diabaikan untuk field server; identitas/rute tetap server-side |
| A09 | Admin/pengelola tanpa penugasan mencoba approve | Ditolak meskipun dapat membuka register |
| A10 | Akun nonaktif, sesi lama, orang lain mengakses detail/unduh/CSV | Hak dicek ulang; tidak bocor data |
| A11 | Dua approval serentak / approve vs batal / submit ganda | Hanya satu transisi sah; tidak ada nomor/tahap/arsip ganda |
| A12 | Dua interval bersamaan untuk pegawai sama | Satu ditolak bila tumpang tindih; interval berdampingan lolos |
| A13 | Audit gagal pada keputusan | Status dan keputusan ikut rollback |
| A14 | Notifikasi tahap | Penerima tepat, tidak duplikat; pihak lama tak bisa bertindak setelah dialihkan |
| A15 | Mutasi jabatan/sinkron pegawai setelah submit | Riwayat tetap; tugas perlu pengalihan eksplisit bila kewenangan berubah |
| A16 | Generator/konversi gagal, timeout, crash setelah pindah berkas | Keputusan tersimpan; status tidak salah `siap`; retry nomor/snapshot sama |
| A17 | Template/data pegawai diubah setelah final | Unduhan arsip lama tetap identik menurut hash |
| A18 | Akses generator lama dengan GET/POST/admin/link prefill | Tidak bisa membuat izin final di luar modul saat aktif/pemeliharaan |
| A19 | Jenis surat lain | Generate, ledger, unduhan, dan hak akses tetap berjalan |
| A20 | Kalender, zona waktu, jam lewat, rencana kembali | Validasi mengikuti zona kantor dan aturan dua jenis izin |
| A21 | PDF pegawai/hakim/pengganti | Identitas, tanggal/jam, blok mengetahui, waktu keputusan sesuai snapshot; tata letak terbaca |
| A22 | Dicabut/ditolak/dibatalkan dan salin ulang | Status terminal terjaga; permohonan baru tidak mewarisi keputusan |
| A23 | CSRF/XSS/path traversal/CSV formula/storage HTTP | Ditolak atau di-escape; storage tidak dapat diunduh langsung |
| A24 | Restore staging dan mode pemeliharaan | DB, berkas, dan hash konsisten; rollback tidak membuka bypass lama |
| A25 | Submit/keputusan dengan provider WA mati | Keputusan tersimpan dan notifikasi AURA tersedia; outbox dapat diproses ulang tanpa menggandakan peristiwa |
| A26 | Worker ganda, timeout ambigu, retry, nomor invalid | Lease/deduplikasi bekerja; timeout ambigu tidak dikirim ulang buta; kegagalan terlihat dan rahasia tidak bocor |
| A27 | Tugas diputus/dialihkan sebelum WA terkirim atau retry | Pesan tugas usang dibatalkan; penerima baru mendapat tugas yang benar |
| A28 | Pengajuan “Mulai sekarang”, jam lewat saat menunggu | Waktu mulai berasal dari server; tidak memaksa pengajuan ulang; keputusan mencatat waktu sebenarnya sesuai kebijakan hari yang sama |
| A29 | Tautan WhatsApp dan pengingat | Login tetap wajib, kembali ke detail yang benar; pengingat sekali hanya untuk tugas aktif dan tidak mengalihkan kewenangan |
| A30 | Nomor tidak tersedia, batas pergantian hari, retry PDF | Pengajuan tetap masuk dengan peringatan WA; keputusan terlambat mengikuti batas tanggal; PDF izin yang sudah disetujui tetap bisa diterbitkan ulang melalui retry |
| A31 | API dan kotak hijau portal | Hanya izin disetujui pada tanggal kantor yang dipilih; pencabutan terhapus pada refresh; tidak ada alasan/NIP/token di peramban |
| A32 | AURA gagal dihubungi atau tanggal berganti | Portal menandai data lama/kedaluwarsa atau tidak tersedia, tidak menyatakan nihil izin; slide lama tetap bekerja |

Gunakan unit test untuk routing/transisi, integration test database untuk transaksi/race/akses, serta uji browser dengan akun sintetis untuk alur dan hasil dokumen. Framework tes mengikuti proyek; jangan memasang framework besar hanya demi dokumen ini. Uji kegagalan dilakukan di staging, bukan dengan merusak layanan produksi.

## 18. Serah terima dan batas selesai

Pekerjaan implementasi selesai ketika acceptance matrix lulus untuk kebijakan yang dikonfirmasi, dua contoh PDF disetujui, alur lama tidak bisa membypass, arsip dapat dipulihkan, dan laporan perubahan serta rollback tersedia. Deployment tetap tahap tersendiri dengan otorisasi pengguna.

Laporan Claude harus membedakan: selesai dan diuji; diimplementasikan tetapi belum diuji; asumsi menunggu keputusan; tindakan produksi yang belum dilakukan. Jangan menyebut “siap produksi” hanya berdasarkan lint atau keberadaan file.

### Penggunaan skills oleh Claude

Pengguna meminta pekerjaan menggunakan skills. Periksa katalog skills yang benar-benar tersedia pada lingkungan Claude dan baca `SKILL.md` terkait sebelum menerapkannya; jangan menganggap path atau plugin di lingkungan Codex otomatis tersedia di Claude.

Rancangan ini sudah disusun menggunakan `superpowers:brainstorming` dan builder check oleh reviewer independen. Lanjutkan dari keputusan AURA yang sudah dibuat, tanpa mengulang brainstorming dari nol. Konfirmasi hanya kebijakan terbuka yang memengaruhi pekerjaan.

| Tahap | Skill yang digunakan jika tersedia | Hasil yang diharapkan |
|---|---|---|
| Menyusun rencana | `superpowers:writing-plans` | Rencana berdasarkan rancangan ini dan kondisi repo aktual |
| Membatasi implementasi | `ponytail` atau `lean-build` | Memanfaatkan kode AURA dan menjaga batas versi pertama |
| Menjalankan rencana | `superpowers:executing-plans` | Perubahan bertahap beserta pemeriksaan tiap bagian |
| Mengimplementasikan perilaku | `superpowers:test-driven-development` | Tes bermakna untuk routing, otorisasi, transisi, dan kegagalan sebelum implementasi terkait |
| Migrasi dan pemulihan | `migration` | Migrasi aditif, bukti preservasi data, dan rollback yang tidak membuka bypass |
| Antarmuka | `impeccable` | Form, antrean, status, dan aksesibilitas mengikuti desain AURA yang ada |
| Pemeriksaan akses | `security-audit` | Review terarah pada approval, akses objek, unduhan privat, dan jalur generator lama |
| Menangani kegagalan | `superpowers:systematic-debugging` | Diagnosis berbukti sebelum mengubah kode |
| Review dan penyelesaian | `superpowers:requesting-code-review`, lalu `superpowers:verification-before-completion` | Temuan ditangani dan klaim hasil didukung pengujian aktual |

Gunakan skill pada tahap yang relevan, bukan menjalankan seluruh workflow sekaligus. Bila nama tersebut tidak tersedia, gunakan padanan yang tersedia dan sebutkan perbedaannya; jangan mengklaim telah memakai skill yang tidak dibaca. Delegasi mengikuti instruksi skill dan lingkungan Claude, tidak wajib membuat banyak agent untuk pekerjaan sederhana. Skills tidak memperluas izin mengubah produksi.

### Prompt yang dapat diberikan kepada Claude

> Baca `/Users/hermawancahyo/repo/aura/docs/rancangan-izin-2026-11-16.md`, mulai bagian 0, lalu seluruh rincian termasuk bagian 19. Target utama implementasi adalah `/Users/hermawancahyo/repo/aura`; integrasi portal ada di `/Users/hermawancahyo/repo/port`; referensi RESTU ada di `/Users/hermawancahyo/repo/restu`. Baca instruksi proyek dan verifikasi kode/skema aktual dahulu. Keputusan pengguna: tetap di AURA, persetujuan langsung dalam aplikasi, WhatsApp otomatis, dan tampilan ringkasan izin di kotak hijau portal. Susun rencana dan tandai kebijakan terbuka, terutama perbedaan asumsi awal mengetahui wajib dengan rekomendasi satu persetujuan pada bagian 0; jangan menerapkan keduanya sekaligus. Setelah kebijakan terkait dipastikan, implementasikan dan uji di lokal/staging dengan data sintetis. Serahkan bukti acceptance matrix, contoh PDF pegawai/hakim, migrasi, dan langkah rollback. Jangan mengubah produksi atau memasang dependency produksi tanpa otorisasi deployment.

> Gunakan skills sesuai panduan bagian 18: baca skill yang tersedia, mulai dari perencanaan, lalu implementasi dan pengujian, review keamanan terarah, serta verifikasi sebelum menyatakan selesai. Sebutkan skill yang benar-benar dipakai. Jangan mengulang keputusan penempatan fitur di AURA atau memperluas lingkup menjadi redesign/audit seluruh aplikasi.

## 19. Integrasi kotak hijau portal lokal

### Temuan kode lokal

`/Users/hermawancahyo/repo/port/index.html`, fungsi `kotakSalam()`, berisi kotak hijau (`.salam`) dengan slide jam, jadwal rapat, pegawai cuti hari ini, serta kegiatan/pengumuman. `cuti.php` menjadi perantara server ke API RESTU, menjaga token di server, dan membatasi field yang diteruskan. Frontend dan cache cuti memakai interval lima menit. Ini bukti kode lokal, bukan bukti versi yang sedang berjalan di server lokal.

### Perubahan yang diusulkan

- Tambahkan slide “Izin pegawai hari ini” dalam kotak hijau. Cuti RESTU dan izin AURA tetap dibedakan; data cuti tidak diubah menjadi izin. Pertahankan navigasi dan slide yang sudah ada.
- Isi minimum: nama, jenis izin, jam mulai dan rencana jam kembali untuk keluar sementara; pulang awal cukup jam mulai. Tidak menampilkan alasan, NIP, nomor telepon, dokumen, atau tugas persetujuan.
- Daftar hari ini dapat mencakup izin yang belum mulai atau intervalnya sudah selesai; beri label “Terjadwal”, “Dalam rentang izin”, atau “Rentang izin selesai” berdasarkan waktu kantor. Jangan menyebut pegawai “sudah keluar/sudah kembali”, karena tidak ada pencatatan kehadiran aktual.
- Sumber adalah status permohonan `disetujui`, bukan keberhasilan membuat PDF. Pending/ditolak/dibatalkan/dicabut tidak ditampilkan.
- AURA menyediakan endpoint baca khusus, misalnya `api/izin_hari_ini.php`, dengan token khusus portal dan field yang diizinkan secara eksplisit. Portal memakai perantara server seperti `izin.php` beserta konfigurasi privat. Jangan meneruskan token ke JavaScript, URL, repo, atau berkas cache publik. Nginx AURA memerlukan whitelist endpoint yang tepat.
- Kontrak respons minimum: `ok`, `tanggal` kantor, `zona_waktu`, `generated_at` UTC, dan `izin` berupa daftar ID tampilan/nama/jenis/jam_mulai/jam_kembali nullable. Endpoint hanya melayani tanggal hari ini menurut zona kantor. Kegagalan memakai HTTP non-200; daftar kosong dengan respons sukses berarti memang tidak ada izin.
- Usulkan refresh frontend dan cache server maksimum 60 detik saat halaman terlihat, dengan pencegahan fetch bertumpuk. Tampilkan waktu pembaruan sumber. Validasi bentuk/tanggal respons dan escape semua teks.
- Jangan mempertahankan cache gagal tanpa batas seperti pola fallback cuti lama. Usulkan masa layak data maksimum dua menit dari `generated_at`; setelah lewat atau tanggal kantor berganti, sembunyikan daftar dan tampilkan “Data izin belum tersedia”. Bila memakai data lama yang masih layak, beri label belum diperbarui; kegagalan koneksi tidak boleh ditampilkan sebagai “Tidak ada izin”. Evaluasi ulang kedaluwarsa di browser meskipun fetch gagal.
- Zona waktu portal saat ini menggunakan Asia/Makassar pada alur cuti. Claude harus memeriksa zona kantor yang benar dan menyelaraskan kontrak izin baru; jangan otomatis memakai tanggal browser atau mengubah kalender cuti lama tanpa uji regresi.
- Portal merupakan daftar informasi kantor, bukan kartu pribadi pengguna login. Sebelum deployment, pastikan batas akses jaringan portal lokal dan siapa yang dapat melihat nama serta rentang izin; jangan membuka endpoint tanpa autentikasi ke internet.
- Jika banyak pegawai, tampilkan jumlah dan pagination/daftar ringkas yang sesuai ruang kotak; jangan memotong nama secara diam-diam. Pilihan detail UI diserahkan kepada Claude dengan mempertahankan tampilan portal yang ada.

Urutan pengerjaan: selesaikan sumber data dan keputusan AURA → API baca terbatas → perantara dan slide portal → uji pencabutan, cache kedaluwarsa, pergantian hari, dan regresi slide cuti/rapat. Implementasi lokal tidak berarti deployment portal lokal otomatis sudah diizinkan.
