#!/usr/bin/env python3
"""Turunkan template modul izin dari template izin_keluar_kantor yang ada (format lokal, migrasi 036).

Hasil: templates/izin_modul_pegawai.docx, templates/izin_modul_hakim.docx.
- Pegawai: tambah baris "Waktu Izin : ${waktu_izin}" sesudah "Unit Kerja" penerima (format asli tidak punya tanggal/jam).
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
    return tambah_keterangan(xml[:p.end()] + baru + xml[p.end():])


ubah('izin_keluar_kantor_pegawai.docx', 'izin_modul_pegawai.docx', pegawai)
ubah('izin_keluar_kantor_hakim.docx', 'izin_modul_hakim.docx', tambah_keterangan)
print('OK templates/izin_modul_pegawai.docx, templates/izin_modul_hakim.docx')
