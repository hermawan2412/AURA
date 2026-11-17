#!/usr/bin/env python3
"""Turunkan template modul izin dari template izin_keluar_kantor yang ada (format lokal, migrasi 036).

Hasil: templates/izin_modul_pegawai.docx, templates/izin_modul_hakim.docx.
- Pegawai: tambah baris "Waktu Izin : ${waktu_izin}" sesudah "Unit Kerja" penerima (format asli tidak punya tanggal/jam).
- Pegawai: blok "Mengetahui" diisi sistem (${mengetahui_judul}/${mengetahui_jabatan}/${mengetahui_nama_lengkap}), catatan "** Coret salah satu" dan catatan kaki "*" dibuang.
- Keduanya: tambah paragraf kecil ${keterangan_aura} di akhir ("Disetujui melalui AURA pada ...").
Tata letak lain tidak diubah. Jalankan ulang bila template sumber berubah:
    python3 migrasi/buat_template_izin_modul.py
"""
import re
import shutil
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent / 'templates'

KETERANGAN = (
    '<w:p><w:pPr><w:spacing w:before="240"/><w:ind w:left="142"/></w:pPr>'
    '<w:r><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:i/><w:sz w:val="16"/><w:szCs w:val="16"/></w:rPr>'
    '<w:t xml:space="preserve">${keterangan_aura}</w:t></w:r></w:p>'
)


def ubah(sumber, tujuan, fungsi):
    with zipfile.ZipFile(ROOT / sumber) as zin, zipfile.ZipFile(ROOT / tujuan, 'w', zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename == 'word/document.xml':
                data = fungsi(data.decode('utf-8')).encode('utf-8')
            zout.writestr(item, data)


def tambah_keterangan(xml):
    i = xml.rindex('<w:sectPr')
    return xml[:i] + KETERANGAN + xml[i:]


def pegawai(xml):
    # Klon baris "Unit Kerja : ${penerima_izin_unit_kerja}" (tab stop-nya sejajar dgn Nama/NIP/Jabatan)
    # jadi "Waktu Izin : ${waktu_izin}", disisipkan tepat sesudahnya.
    paragraf = [m for m in re.finditer(r'<w:p[ >].*?</w:p>', xml, re.S) if '${penerima_izin_unit_kerja}' in m.group(0)]
    assert len(paragraf) == 1, 'paragraf penerima_izin_unit_kerja tidak unik'
    p = paragraf[0]
    baru = re.sub(r' w14:(paraId|textId)="[^"]*"', '', p.group(0))
    for lama, ganti in (('<w:t>U</w:t>', '<w:t>W</w:t>'), ('<w:t>nit</w:t>', '<w:t>aktu</w:t>'), ('<w:t>Kerja</w:t>', '<w:t>Izin</w:t>'),
                        ('${penerima_izin_unit_kerja}', '${waktu_izin}')):
        assert baru.count(lama) == 1, lama
        baru = baru.replace(lama, ganti)
    xml = xml[:p.end()] + baru + xml[p.end():]
    xml = slot_ttd(blok_mengetahui(xml), '${pemberi_izin_nama_lengkap}', 'ttd_pemberi')
    xml = slot_ttd(xml, '${mengetahui_nama_lengkap}', 'ttd_mengetahui')
    return tambah_keterangan(xml)


def ganti_paragraf(xml, cocok, fungsi):
    hasil = [m for m in re.finditer(r'<w:p[ >].*?</w:p>', xml, re.S) if cocok(re.sub(r'<[^>]+>', '', m.group(0)))]
    assert len(hasil) == 1, 'paragraf tidak unik'
    m = hasil[0]
    return xml[:m.start()] + fungsi(m.group(0)) + xml[m.end():]


def blok_mengetahui(xml):
    # Blok "Mengetahui" diisi sistem (Wakil yang diberi tahu), bukan "Ketua/Wakil Ketua ... **" untuk dicoret;
    # bila tidak ada pejabat mengetahui, ketiga baris dikosongkan. Catatan "** Coret salah satu" dibuang.
    xml = ganti_paragraf(xml, lambda t: t == 'Mengetahui', lambda p: p.replace('<w:t>Mengetahui</w:t>', '<w:t>${mengetahui_judul}</w:t>'))

    def jabatan(p):
        n = [0]
        def isi(m):
            n[0] += 1
            return m.group(1) + ('${mengetahui_jabatan}' if n[0] == 1 else '') + '</w:t>'
        return re.sub(r'(<w:t[^>]*>)[^<]*</w:t>', isi, p)
    xml = ganti_paragraf(xml, lambda t: t.startswith('Ketua/Wakil Ketua') and t.endswith('**'), jabatan)
    xml = ganti_paragraf(xml, lambda t: t == '** Coret salah satu', lambda p: '')
    # Catatan kaki "*" (aturan pemberi izin/mengetahui) juga dibuang - sudah ditegakkan sistem.
    xml = ganti_paragraf(xml, lambda t: t == 'Keterangan.', lambda p: '')
    xml = ganti_paragraf(xml, lambda t: t.startswith('* Yang memberikan izin'), lambda p: '')
    xml = ganti_paragraf(xml, lambda t: t.startswith('Dengan ini saya*'), lambda p: p.replace('<w:t>*</w:t>', '<w:t></w:t>', 1))
    return xml



def slot_ttd(xml, penanda_nama, placeholder):
    """Ganti baris-baris kosong tepat di atas nama penanda tangan (paragraf TERAKHIR yang memuat
    penanda_nama) dengan SATU paragraf setinggi baris-baris itu (tinggi minimal) berisi ${placeholder}.
    Gambar TTD masuk ke situ; bila tidak ada gambar, ruang tanda tangan basah tetap sama."""
    ps = list(re.finditer(r'<w:p[ >].*?</w:p>', xml, re.S))
    teks = lambda m: re.sub(r'<[^>]+>', '', m.group(0)).strip()
    i = max(k for k, m in enumerate(ps) if penanda_nama in m.group(0))
    j = i
    while j > 0 and teks(ps[j - 1]) == '' and '<w:drawing' not in ps[j - 1].group(0):
        j -= 1
    kosong = i - j
    assert kosong >= 2, (placeholder, kosong)
    ppr = re.search(r'<w:pPr>.*?</w:pPr>', ps[i].group(0), re.S)
    ppr = ppr.group(0) if ppr else '<w:pPr></w:pPr>'
    ppr = re.sub(r'<w:spacing[^>]*/>', '', ppr)
    spasi = '<w:spacing w:before="0" w:after="0" w:line="%d" w:lineRule="atLeast"/>' % (kosong * 276)
    gaya = re.search(r'<w:pStyle [^>]*/>', ppr)  # urutan skema: pStyle dulu, baru spacing
    ppr = ppr.replace(gaya.group(0), gaya.group(0) + spasi, 1) if gaya else ppr.replace('<w:pPr>', '<w:pPr>' + spasi, 1)
    baru = '<w:p>' + ppr + '<w:r><w:t xml:space="preserve">${%s}</w:t></w:r></w:p>' % placeholder
    return xml[:ps[j].start()] + baru + xml[ps[i].start():]


ubah('izin_keluar_kantor_pegawai.docx', 'izin_modul_pegawai.docx', pegawai)
ubah('izin_keluar_kantor_hakim.docx', 'izin_modul_hakim.docx', lambda x: tambah_keterangan(slot_ttd(x, '${pemberi_izin_nama_lengkap}', 'ttd_pemberi')))
print('OK templates/izin_modul_pegawai.docx, templates/izin_modul_hakim.docx')
