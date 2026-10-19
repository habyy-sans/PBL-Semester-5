@extends('layouts.app')

@section('title', 'Restock')
@section('description', 'Tinjau, setujui, dan pantau Instruksi Pengiriman ke DC.')

@section('content')

<section class="page active">

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">△</div>
            <div class="stat-content">
                <span class="stat-label">Menunggu Approval</span>
                <strong id="stApproval">0 Draft</strong>
                <small class="warning" id="stApprovalSub">-</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">↻</div>
            <div class="stat-content">
                <span class="stat-label">Di Antrean DC</span>
                <strong id="stQueue">0 Instruksi</strong>
                <small>Sudah disetujui Manager</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">▱</div>
            <div class="stat-content">
                <span class="stat-label">Dalam Pengiriman</span>
                <strong id="stShip">0 Instruksi</strong>
                <small id="stShipSub">-</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">≡</div>
            <div class="stat-content">
                <span class="stat-label">Selesai Diterima</span>
                <strong id="stDone">0 Instruksi</strong>
                <small class="positive">Stok sudah bertambah</small>
            </div>
        </div>

    </div>

    <div id="flashRoot"></div>

    <div class="page-card section-gap">

        <div class="table-toolbar">
            <input type="text" id="rsSearch" class="search-input" placeholder="Cari no. instruksi...">
            <select id="rsStatus" class="select">
                <option value="semua">Semua Status</option>
                <option value="approval">Menunggu Approval</option>
                <option value="disetujui">Diteruskan ke DC</option>
                <option value="dikirim">Dikirim DC</option>
                <option value="diterima">Diterima</option>
            </select>
            <select id="rsSource" class="select">
                <option value="semua">Semua Sumber</option>
                <option value="otomatis">Otomatis</option>
                <option value="manual">Manual</option>
            </select>
            <span class="table-meta" id="rsMeta"></span>
            <button type="button" class="primary-button" id="btnManual">+ Reorder Manual</button>
        </div>

        <div class="table-wrapper">
            <table class="table-fixed">
                <colgroup>
                    <col style="width: 12%">
                    <col style="width: 14%">
                    <col style="width: 11%">
                    <col style="width: 9%">
                    <col style="width: 16%">
                    <col style="width: 23%">
                    <col style="width: 15%">
                </colgroup>
                <thead>
                    <tr>
                        <th>No. Instruksi</th>
                        <th>Tanggal</th>
                        <th>Sumber</th>
                        <th>Bahan</th>
                        <th style="text-align:right">Estimasi Biaya</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="rsBody"></tbody>
            </table>
        </div>

    </div>

</section>

<div id="modalRoot"></div>

<script>
(() => {

    // ---------- ENDPOINT BACKEND ----------
    const URLS = {
        draft:  @json(route('manager.restock.draft')),
        approve: @json(route('manager.restock.approve')),
    };

    const CSRF  = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    const STATUS = {
        approval:   { label: 'Menunggu Approval',     cls: 'warning' },
        disetujui:  { label: 'Diteruskan ke DC',      cls: 'info'    },
        dikirim:    { label: 'Dikirim DC',            cls: 'info'    },
        diterima:   { label: 'Diterima',              cls: 'success' },
    };

    const INFO = {
        approval:   'Menunggu persetujuan Manager.',
        disetujui:  'Sudah disetujui dan masuk antrean kerja DC. DC akan menyiapkan barang dan menerbitkan Surat Jalan Digital.',
        dikirim:    'DC sudah mengirim barang. Menunggu barang tiba di cabang, lalu dikonfirmasi Kepala Shift (IC).',
        diterima:   'Barang sudah dikonfirmasi IC dan stok cabang sudah bertambah.',
    };

    const REASONS = ['Lonjakan pengunjung', 'Pengganti barang waste', 'Stok kurang dari perkiraan', 'Lainnya'];

    const $  = id => document.getElementById(id);
    const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');

    // ---------- STATE (diisi dari backend) ----------
    let ITEMS      = {};     // info bahan, kunci = bahan_id
    let draftItems = [];     // usulan restock real-time (stok < minimum)
    let orders     = [];     // riwayat instruksi (draft_instruksi)

    let keyword  = '';
    let fStatus  = 'semua';
    let fSource  = 'semua';

    let draftQty = {};       // jumlah kemasan per bahan pada draft otomatis

    // reorder manual (dialog)
    let mLines  = {};
    let mPick   = null;
    let mAdd    = 1;
    let mReason = REASONS[0];
    let mNote   = '';

    // 3430 ml (isi 700) => "4 Kotak 630 ml"
    const fmtStock = (item, amount) => {
        if (!item) return String(amount);
        const size = Number(item.isi_per_kemasan) || 1;
        if (size <= 1) return `${amount.toLocaleString('id-ID')} ${item.satuan}`;
        const p = Math.floor(amount / size);
        const r = Math.round((amount - p * size) * 100) / 100;
        return `${p.toLocaleString('id-ID')} ${item.nama_kemasan}` + (r ? ` ${r.toLocaleString('id-ID')} ${item.satuan}` : '');
    };

    const today = () => {
        const d = new Date();
        return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
    };

    // kebutuhan (satuan dasar) dibulatkan ke atas ke kemasan utuh
    const suggestPacks = i => Math.max(1, Math.ceil(i.kebutuhan / (Number(i.isi_per_kemasan) || 1)));

    const orderCost = o => o.draft
        ? o.items.reduce((s, l) => s + l.qty * ITEMS[l.bahan_id].harga_beli, 0)
        : o.items.reduce((s, l) => s + l.qty_base * l.harga_beli, 0);

    // ---------- MUAT DATA DARI BACKEND ----------
    async function loadData() {
        $('rsBody').innerHTML = '<tr><td colspan="7"><div class="page-empty">Memuat data...</div></td></tr>';

        const res  = await fetch(URLS.draft, { headers: { 'Accept': 'application/json' } });
        const json = await res.json().catch(() => null);

        if (!res.ok || !json) {
            $('rsBody').innerHTML = '<tr><td colspan="7"><div class="page-empty">Gagal memuat data. Muat ulang halaman.</div></td></tr>';
            return;
        }

        ITEMS = {};
        json.items.forEach(i => ITEMS[String(i.bahan_id)] = i);
        draftItems = json.items;
        orders = json.orders || [];

        draftQty = {};
        draftItems.forEach(i => draftQty[i.bahan_id] = suggestPacks(i));

        if (mPick === null) mPick = Object.keys(ITEMS)[0] ?? null;

        renderTable();
    }

    // ---------- BANGUN DAFTAR INSTRUKSI ----------
    const draftOrder = () => draftItems.length ? [{
        draft: true,
        no: 'DRAFT',
        date: today(),
        source: 'otomatis',
        status: 'approval',
        items: draftItems.map(i => ({ bahan_id: i.bahan_id, qty: draftQty[i.bahan_id] || 0 })),
    }] : [];

    const allOrders = () => [
        ...draftOrder(),
        ...orders.map(o => ({ ...o, draft: false })),
    ];

    // ---------- RENDER ----------
    function renderStats(all) {
        const count = st => all.filter(o => o.status === st).length;

        const hasDraft = draftItems.length > 0;
        const ship     = count('dikirim');

        $('stApproval').textContent    = hasDraft ? '1 Draft' : '0 Draft';
        $('stApprovalSub').textContent = hasDraft ? draftItems.length + ' bahan di bawah minimum' : 'Tidak ada draft';
        $('stQueue').textContent       = count('disetujui') + ' Instruksi';
        $('stShip').textContent        = ship + ' Instruksi';
        $('stShipSub').textContent     = ship ? 'Menunggu konfirmasi IC' : 'Tidak ada pengiriman';
        $('stDone').textContent        = count('diterima') + ' Instruksi';
    }

    function renderTable() {
        const all = allOrders();
        renderStats(all);

        const list = all.filter(o =>
            (fStatus === 'semua' || o.status === fStatus) &&
            (fSource === 'semua' || o.source === fSource) &&
            (String(o.no) + ' ' + (o.draft ? 'draft usulan' : '')).toLowerCase().includes(keyword.toLowerCase()));

        $('rsMeta').textContent = list.length + ' instruksi';

        if (!list.length) {
            $('rsBody').innerHTML = '<tr><td colspan="7"><div class="page-empty">Instruksi tidak ditemukan</div></td></tr>';
            return;
        }

        $('rsBody').innerHTML = list.map(o => `
            <tr>
                <td><strong>${o.draft ? 'Draft Otomatis' : o.no}</strong></td>
                <td class="col-nowrap">${o.date}</td>
                <td><span class="status info">${o.source === 'otomatis' ? 'Otomatis' : 'Manual'}</span></td>
                <td>${o.items.length} jenis</td>
                <td style="text-align:right"><strong>${rp(orderCost(o))}</strong></td>
                <td><span class="status ${STATUS[o.status].cls}">${STATUS[o.status].label}</span></td>
                <td>${o.status === 'approval'
                    ? `<button type="button" class="primary-button btn-sm" data-review>Tinjau</button>`
                    : `<button type="button" class="outline-button btn-sm" data-detail="${o.no}">Detail</button>`}</td>
            </tr>`).join('');
    }

    // ---------- MODAL ----------
    const openModal  = html => $('modalRoot').innerHTML = `<div class="modal-backdrop" id="modalBg">${html}</div>`;
    const closeModal = () => $('modalRoot').innerHTML = '';

    function flash(msg, ok = true) {
        $('flashRoot').innerHTML = `<div class="flash-success" style="${ok ? '' : 'background:#fdecea;color:#b3261e'}">${msg}</div>`;
        setTimeout(() => $('flashRoot').innerHTML = '', 5000);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ---- tinjau draft otomatis ----
    const dSubtotal = i => (draftQty[i.bahan_id] || 0) * i.harga_beli;
    const dTotal    = () => draftItems.reduce((s, i) => s + dSubtotal(i), 0);
    const dCount    = () => draftItems.reduce((s, i) => s + (draftQty[i.bahan_id] || 0), 0);

    function showDraft() {
        if (!draftItems.length) return;

        openModal(`
            <div class="modal-box wide" style="max-width:680px">
                <h4>Draft Instruksi Pengiriman (Usulan Otomatis)</h4>
                <p>Dibuat otomatis oleh sistem (stok minimum − stok aktual). Sesuaikan jumlah bila perlu, lalu setujui agar diteruskan ke antrean DC.</p>

                <div class="table-wrapper" style="margin-top:12px">
                    <table>
                        <thead>
                            <tr>
                                <th>Bahan</th>
                                <th>Stok</th>
                                <th>Minimum</th>
                                <th>Jumlah Kirim</th>
                                <th style="text-align:right">Estimasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${draftItems.map(i => `
                            <tr>
                                <td><strong>${i.nama_bahan}</strong></td>
                                <td>${fmtStock(i, i.stok_aktual)}</td>
                                <td>${fmtStock(i, i.stok_minimum)}</td>
                                <td>
                                    <div class="form-row">
                                        <input type="number" min="0" step="1" class="table-input qty" data-qty="${i.bahan_id}" value="${draftQty[i.bahan_id]}">
                                        <span class="form-unit">${i.nama_kemasan}</span>
                                    </div>
                                </td>
                                <td style="text-align:right" id="sub-${i.bahan_id}">${rp(dSubtotal(i))}</td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>

                <div class="summary-box">
                    <div class="summary-line"><span><b>Total estimasi biaya</b></span><span><b id="draftTotal">${rp(dTotal())}</b></span></div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>Batal</button>
                    <button type="button" class="primary-button" id="btnApprove" ${dCount() ? '' : 'disabled'}>Setujui &amp; Teruskan ke DC</button>
                </div>
            </div>`);
    }

    async function approveDraft() {
        const payload = {
            asal: 'otomatis',
            items: draftItems
                .map(i => ({
                    bahan_id: i.bahan_id,
                    qty_diminta: (draftQty[i.bahan_id] || 0) * (Number(i.isi_per_kemasan) || 1),
                }))
                .filter(i => i.qty_diminta > 0),
        };

        if (!payload.items.length) return;

        const res  = await fetch(URLS.approve, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json().catch(() => ({}));

        closeModal();

        if (!res.ok) {
            flash(json.message || 'Persetujuan draft gagal. Coba lagi.', false);
            return;
        }

        flash(`Draft ${json.no} disetujui (${json.count} jenis bahan) dan diteruskan ke antrean DC.`);
        await loadData();
    }

    // ---- detail instruksi ----
    function showOrder(no) {
        const o = allOrders().find(x => x.no === no);
        if (!o) return;

        const rows = o.items.map(l => {
            const it = ITEMS[String(l.item)] || {};
            const pack = l.pack || it.nama_kemasan || '';
            const name = l.name || it.nama_bahan || '-';
            return `
            <tr>
                <td><strong>${name}</strong></td>
                <td>${l.qty} ${pack}</td>
                <td>${l.qty_base.toLocaleString('id-ID')} ${it.satuan || ''}</td>
                <td style="text-align:right">${rp(l.qty_base * l.harga_beli)}</td>
            </tr>`;
        }).join('');

        openModal(`
            <div class="modal-box wide" style="max-width:680px">
                <h4>Instruksi Pengiriman ${o.no}</h4>
                <p>${o.date} · ${o.source === 'otomatis' ? 'Otomatis dari alert restock' : 'Reorder manual'}</p>

                <div class="table-wrapper" style="margin-top:12px">
                    <table>
                        <thead>
                            <tr>
                                <th>Bahan</th>
                                <th>Dipesan</th>
                                <th>Satuan Dasar</th>
                                <th style="text-align:right">Estimasi</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>

                <div class="summary-box">
                    <div class="summary-line"><span><b>Total estimasi biaya</b></span><span><b>${rp(orderCost(o))}</b></span></div>
                    <div class="summary-line"><span>Status</span><span>${STATUS[o.status].label}</span></div>
                </div>

                <div class="info-note">${INFO[o.status]}</div>

                <div class="modal-actions">
                    <button type="button" class="primary-button" data-close>Tutup</button>
                </div>
            </div>`);
    }

    // ---- reorder manual ----
    const mTotal = () => Object.entries(mLines).reduce((s, [k, q]) => s + q * (ITEMS[k]?.harga_beli || 0), 0);
    const mCount = () => Object.values(mLines).reduce((s, q) => s + q, 0);

    function showManual() {
        const keys = Object.keys(mLines);

        const options = Object.entries(ITEMS)
            .sort((a, b) => a[1].nama_bahan.localeCompare(b[1].nama_bahan))
            .map(([k, i]) => `<option value="${k}" ${k === mPick ? 'selected' : ''}>${i.nama_bahan} (${i.nama_kemasan})</option>`)
            .join('');

        openModal(`
            <div class="modal-box wide">
                <h4>Reorder Manual ke DC</h4>
                <p>Untuk kebutuhan di luar restock otomatis, misalnya lonjakan pengunjung atau pengganti barang waste.</p>

                <div class="form-field" style="margin-top:14px">
                    <label class="form-label">Tambah Bahan</label>
                    <div class="form-row">
                        <select id="mPick" class="form-input">${options}</select>
                        <input type="number" min="1" step="1" id="mAdd" class="form-input unit" value="${mAdd}">
                        <button type="button" class="outline-button" data-madd>Tambah</button>
                    </div>
                    <div class="form-hint">Jumlah dalam kemasan.</div>
                </div>

                <div class="summary-box">
                    <div class="summary-title">DAFTAR BAHAN</div>
                    ${keys.length ? keys.map(k => `
                    <div class="form-row" style="margin-bottom:8px">
                        <span style="flex:1;font-size:10px">${ITEMS[k].nama_bahan}</span>
                        <input type="number" min="0" step="1" class="table-input qty" data-mqty="${k}" value="${mLines[k]}">
                        <span class="form-unit">${ITEMS[k].nama_kemasan}</span>
                        <button type="button" class="outline-button btn-sm" data-mremove="${k}">Hapus</button>
                    </div>`).join('') : '<div class="form-hint">Belum ada bahan dipilih.</div>'}
                    <div class="summary-line"><span><b>Estimasi biaya</b></span><span><b id="mTotal">${rp(mTotal())}</b></span></div>
                </div>

                <div class="form-field">
                    <label class="form-label">Alasan</label>
                    <select id="mReason" class="form-input">
                        ${REASONS.map(r => `<option ${r === mReason ? 'selected' : ''}>${r}</option>`).join('')}
                    </select>
                </div>

                <div class="form-field">
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea id="mNote" class="form-input" placeholder="Tulis catatan untuk DC...">${mNote}</textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>Batal</button>
                    <button type="button" class="primary-button" id="btnSendManual" ${mCount() ? '' : 'disabled'}>Kirim ke DC</button>
                </div>
            </div>`);
    }

    async function sendManual() {
        if (!mCount()) return;

        const payload = {
            asal: 'manual',
            reason: mNote ? `${mReason} — ${mNote}` : mReason,
            items: Object.entries(mLines)
                .filter(([, q]) => q > 0)
                .map(([k, q]) => ({
                    bahan_id: Number(k),
                    qty_diminta: q * (Number(ITEMS[k].isi_per_kemasan) || 1),
                })),
        };

        const res  = await fetch(URLS.approve, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json().catch(() => ({}));

        if (!res.ok) {
            closeModal();
            flash(json.message || 'Reorder manual gagal dikirim. Coba lagi.', false);
            return;
        }

        mLines = {}; mAdd = 1; mReason = REASONS[0]; mNote = '';

        closeModal();
        flash(`Reorder manual ${json.no} (${json.count} jenis bahan) dikirim ke antrean DC.`);
        await loadData();
    }

    // ---------- EVENT ----------
    document.addEventListener('click', e => {
        const t = e.target;

        if (t.closest('[data-review]'))  return showDraft();
        if (t.closest('[data-detail]'))  return showOrder(t.closest('[data-detail]').dataset.detail);
        if (t.closest('[data-madd]')) {
            if (mPick && ITEMS[mPick]) {
                mLines[mPick] = (mLines[mPick] || 0) + Math.max(1, mAdd);
            }
            mAdd = 1;
            return showManual();
        }
        if (t.closest('[data-mremove]')) { delete mLines[t.closest('[data-mremove]').dataset.mremove]; return showManual(); }
        if (t.closest('[data-close]') || t.id === 'modalBg') return closeModal();
        if (t.id === 'btnManual')        return showManual();
        if (t.id === 'btnApprove')       return approveDraft();
        if (t.id === 'btnSendManual')    return sendManual();
    });

    document.addEventListener('input', e => {
        const t = e.target;

        if (t.id === 'rsSearch') { keyword = t.value; renderTable(); }

        if (t.dataset.qty) {
            const id = t.dataset.qty;
            draftQty[id] = Math.max(0, parseInt(t.value, 10) || 0);

            $('sub-' + id).textContent = rp(draftQty[id] * ITEMS[id].harga_beli);
            $('draftTotal').textContent = rp(dTotal());
            $('btnApprove').disabled = !dCount();
        }

        if (t.dataset.mqty) {
            mLines[t.dataset.mqty] = Math.max(0, parseInt(t.value, 10) || 0);

            $('mTotal').textContent = rp(mTotal());
            $('btnSendManual').disabled = !mCount();
        }

        if (t.id === 'mAdd')  mAdd  = Math.max(1, parseInt(t.value, 10) || 1);
        if (t.id === 'mNote') mNote = t.value;
    });

    document.addEventListener('change', e => {
        const t = e.target;

        if (t.id === 'rsStatus') { fStatus = t.value; renderTable(); }
        if (t.id === 'rsSource') { fSource = t.value; renderTable(); }
        if (t.id === 'mPick')    { mPick   = t.value; showManual(); }
        if (t.id === 'mReason')  { mReason = t.value; }
    });

    loadData();

})();
</script>

@endsection
