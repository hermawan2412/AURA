/**
 * Widget pencarian pegawai (live search via api/pegawai_cari.php), dipakai oleh
 * surat/index.php (mesin generic). Menggantikan blok <script> yang sebelumnya
 * copy-paste hampir identik di tiap surat/{kode}.php.
 */
window.AuratPicker = window.AuratPicker || {};

(function (AuratPicker) {
    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, delay);
        };
    }

    /** Pratinjau kasar di browser utk kolom sumber='pegawai_fungsi' — nilai final tetap dihitung server-side. */
    function pratinjauFungsiPegawai(fungsiPasca, p) {
        if (fungsiPasca === 'nama_bergelar') {
            var bagian = [];
            if (p.gelar_depan) { bagian.push(p.gelar_depan); }
            bagian.push(p.nama_lengkap);
            var nama = bagian.join(' ');
            if (p.gelar_belakang) { nama += ', ' + p.gelar_belakang; }
            return nama;
        }
        if (fungsiPasca === 'pangkat_golongan') {
            if (p.pangkat && p.golongan_ruang) { return p.pangkat + ', ' + p.golongan_ruang; }
            return p.pangkat || p.golongan_ruang || '';
        }
        if (fungsiPasca === 'jabatan_satuan_kerja') {
            return [p.jabatan, p.unit_kerja].filter(function (s) { return s; }).join(' — ');
        }
        return '(dihitung otomatis)';
    }

    /**
     * Slot pemilih SATU pegawai (peran_pegawai_surat), mis. "pemohon", "penetap".
     *
     * config: {
     *   inputId:            id <input> pencarian nama/NIP
     *   hasilId:            id <div> daftar hasil pencarian
     *   hiddenIdField:      id <input type="hidden"> penyimpan id pegawai terpilih
     *   targetTerpilihId:   id <div> ringkasan pegawai terpilih (opsional)
     *   apiUrl:             default '../api/pegawai_cari.php'
     * }
     */
    AuratPicker.initTunggal = function (config) {
        var input = document.getElementById(config.inputId);
        var hasilBox = document.getElementById(config.hasilId);
        var idField = document.getElementById(config.hiddenIdField);
        var terpilihBox = config.targetTerpilihId ? document.getElementById(config.targetTerpilihId) : null;
        var apiUrl = config.apiUrl || '../api/pegawai_cari.php';

        if (!input || !hasilBox || !idField) {
            return;
        }

        var cari = debounce(function () {
            var q = input.value.trim();
            idField.value = '';
            if (q.length < 2) {
                hasilBox.style.display = 'none';
                return;
            }

            fetch(apiUrl + '?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    hasilBox.innerHTML = '';
                    if (!data.length) {
                        hasilBox.style.display = 'none';
                        return;
                    }
                    data.forEach(function (p) {
                        var row = document.createElement('div');
                        row.className = 'picker-row';
                        row.innerHTML = '<div><b></b><span></span></div>';
                        row.querySelector('b').textContent = p.nama_lengkap;
                        row.querySelector('span').textContent = p.nip + ' · ' + (p.jabatan || '');
                        row.addEventListener('click', function () {
                            idField.value = p.id;
                            input.value = p.nama_lengkap;
                            hasilBox.style.display = 'none';
                            if (terpilihBox) {
                                terpilihBox.innerHTML = '';
                                var ringkasan = document.createElement('div');
                                ringkasan.className = 'alert alert-info';
                                ringkasan.textContent = p.nama_lengkap + ' — ' + p.nip;
                                terpilihBox.appendChild(ringkasan);
                            }
                        });
                        hasilBox.appendChild(row);
                    });
                    hasilBox.style.display = 'block';
                });
        }, 200);

        input.addEventListener('input', cari);

        document.addEventListener('click', function (e) {
            if (e.target !== input) {
                hasilBox.style.display = 'none';
            }
        });
    };

    /**
     * Blok tabel berulang (blok_tabel_surat), mis. daftar anggota Tim Kerja di SK —
     * cari & tambah pegawai, urutan bisa diseret, kolom manual_per_baris bisa diisi.
     * Pola sama dengan drag-reorder di sk.php (lama), digeneralisasi utk kolom apa pun.
     *
     * config: {
     *   blokKode, inputId, hasilId, tbodyId, kosongId,
     *   kolom: [{kode, label, sumber, field_pegawai}],  // dari blok_tabel_surat_kolom
     *   apiUrl: default '../api/pegawai_cari.php'
     * }
     */
    AuratPicker.initTabel = function (config) {
        var input = document.getElementById(config.inputId);
        var hasilBox = document.getElementById(config.hasilId);
        var tbody = document.getElementById(config.tbodyId);
        var kosong = config.kosongId ? document.getElementById(config.kosongId) : null;
        var apiUrl = config.apiUrl || '../api/pegawai_cari.php';
        var kolom = config.kolom || [];
        var blokKode = config.blokKode;

        if (!input || !hasilBox || !tbody) {
            return;
        }

        var baris = []; // [{ pegawai: {...dari api...}, manual: {kolom_kode: nilai} }]
        var dragFrom = null;

        function render() {
            tbody.innerHTML = '';
            if (baris.length === 0) {
                if (kosong) {
                    tbody.appendChild(kosong);
                }
                return;
            }

            baris.forEach(function (b, idx) {
                var tr = document.createElement('tr');
                tr.draggable = true;
                tr.dataset.idx = idx;

                var tdHandle = document.createElement('td');
                tdHandle.textContent = '⠿';
                tdHandle.style.cssText = 'cursor:grab; color:var(--ink-dim);';
                tr.appendChild(tdHandle);

                kolom.forEach(function (k) {
                    var td = document.createElement('td');
                    if (k.sumber === 'auto_nomor') {
                        td.textContent = idx + 1;
                    } else if (k.sumber === 'pegawai_field') {
                        td.textContent = b.pegawai[k.field_pegawai] || '';
                    } else if (k.sumber === 'pegawai_fungsi') {
                        // Nilai sebenarnya dihitung server-side saat submit (Aurat\Surat\NilaiResolver::panggilFungsiPasca);
                        // ini cuma pratinjau kasar di browser utk beberapa fungsi umum berbasis baris pegawai.
                        td.textContent = pratinjauFungsiPegawai(k.fungsi_pasca, b.pegawai);
                    } else if (k.sumber === 'manual_per_baris') {
                        var inp = document.createElement('input');
                        inp.type = (k.tipe === 'date') ? 'date' : 'text';
                        inp.name = 'blok[' + blokKode + '][manual][' + k.kode + '][]';
                        inp.value = b.manual[k.kode] || '';
                        inp.style.cssText = 'width:100%; border:1px solid var(--border-strong); border-radius:6px; padding:6px 8px; font-size:0.83rem;';
                        inp.addEventListener('input', function () { b.manual[k.kode] = inp.value; });
                        td.appendChild(inp);
                    }
                    tr.appendChild(td);
                });

                var tdAksi = document.createElement('td');
                var hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'blok[' + blokKode + '][pegawai_id][]';
                hiddenId.value = b.pegawai.id;
                tdAksi.appendChild(hiddenId);
                var rm = document.createElement('button');
                rm.type = 'button';
                rm.textContent = '×';
                rm.style.cssText = 'background:none; border:none; cursor:pointer; font-size:1rem; color:var(--ink-dim);';
                rm.addEventListener('click', function () { baris.splice(idx, 1); render(); });
                tdAksi.appendChild(rm);
                tr.appendChild(tdAksi);

                tr.addEventListener('dragstart', function () { dragFrom = idx; });
                tr.addEventListener('dragover', function (e) { e.preventDefault(); });
                tr.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (dragFrom === null || dragFrom === idx) {
                        return;
                    }
                    var moved = baris.splice(dragFrom, 1)[0];
                    baris.splice(idx, 0, moved);
                    dragFrom = null;
                    render();
                });

                tbody.appendChild(tr);
            });
        }

        var cari = debounce(function () {
            var q = input.value.trim();
            if (q.length < 2) {
                hasilBox.style.display = 'none';
                return;
            }

            fetch(apiUrl + '?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    hasilBox.innerHTML = '';
                    if (!data.length) {
                        hasilBox.style.display = 'none';
                        return;
                    }
                    data.forEach(function (p) {
                        var row = document.createElement('div');
                        row.className = 'picker-row';
                        row.innerHTML = '<div><b></b><span></span></div>';
                        row.querySelector('b').textContent = p.nama_lengkap;
                        row.querySelector('span').textContent = p.nip + ' · ' + (p.jabatan || '');
                        row.addEventListener('click', function () {
                            if (!baris.some(function (b) { return b.pegawai.id === p.id; })) {
                                baris.push({ pegawai: p, manual: {} });
                                render();
                            }
                            input.value = '';
                            hasilBox.style.display = 'none';
                        });
                        hasilBox.appendChild(row);
                    });
                    hasilBox.style.display = 'block';
                });
        }, 200);

        input.addEventListener('input', cari);
        document.addEventListener('click', function (e) {
            if (e.target !== input) {
                hasilBox.style.display = 'none';
            }
        });

        render();
    };
})(window.AuratPicker);
