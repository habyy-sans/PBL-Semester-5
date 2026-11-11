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
                    <col style="width: 25%">
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
        generate: @json(route('manager.restock.generate-automatic')),
        draft:  @json(route('manager.restock.draft')),
        update: @json(route('manager.restock.update-draft')),
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
    const esc = str => {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

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
    let deepLinkHandled = false;

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

    const orderCost = o =>
    o.items.reduce((s, l) => s + l.qty * l.harga_beli, 0);

    
// ---------- MUAT DATA DARI BACKEND ----------

async function loadData() {
    $('rsBody').innerHTML = '<tr><td colspan="7"><div class="page-empty">Memuat data...</div></td></tr>';

    const generateRes = await fetch(URLS.generate, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
        },
    });

    const generateJson = await generateRes.json().catch(() => null);

    if (!generateRes.ok || !generateJson) {
        $('rsBody').innerHTML = '<tr><td colspan="7"><div class="page-empty">Gagal memproses draft otomatis.</div></td></tr>';
        return;
    }

    const res = await fetch(URLS.draft, {
        headers: { 'Accept': 'application/json' }
    });

    const json = await res.json().catch(() => null);

    if (!res.ok || !json) {
        $('rsBody').innerHTML = '<tr><td colspan="7"><div class="page-empty">Gagal memuat data. Muat ulang halaman.</div></td></tr>';
        return;
    }

    ITEMS = {};
    (json.manual_items || json.items).forEach(i => {
        ITEMS[String(i.bahan_id)] = i;
    });

    // Daftar stok menipis tetap khusus untuk restock otomatis.
    draftItems = json.items;

    // Daftar instruksi sepenuhnya berasal dari database.
    orders = json.orders || [];

    if (mPick === null) {
        mPick = Object.keys(ITEMS)[0] ?? null;
    }

    renderTable();

    if (!deepLinkHandled) {
        deepLinkHandled = true;
        const draftId = new URLSearchParams(window.location.search).get('draft_id');

        if (draftId) {
            showDraft(draftId);
        }
    }
}


    // ---------- BANGUN DAFTAR INSTRUKSI ----------
    // Semua instruksi diambil dari database.
    // Tidak ada lagi baris draft sementara.
    const allOrders = () => orders.map(o => ({
        ...o,
        draft: false,
    }));

    // ---------- RENDER ----------
    function renderStats(all) {
        const count = st => all.filter(o => o.status === st).length;

        const pending = all.filter(o => o.status === 'approval');
        const pendingItems = pending.reduce((s, o) => s + o.items.length, 0);

        const ship = count('dikirim');

        $('stApproval').textContent = pending.length + ' Draft';
        $('stApprovalSub').textContent = pending.length
            ? pendingItems + ' detail bahan menunggu persetujuan'
            : 'Tidak ada draft';

        $('stQueue').textContent = count('disetujui') + ' Instruksi';
        $('stShip').textContent = ship + ' Instruksi';
        $('stShipSub').textContent = ship
            ? 'Menunggu konfirmasi IC'
            : 'Tidak ada pengiriman';

        $('stDone').textContent = count('diterima') + ' Instruksi';
    }

    function renderTable() {
        const all = allOrders();
        renderStats(all);

        const list = all.filter(o =>
            (fStatus === 'semua' || o.status === fStatus) &&
            (fSource === 'semua' || o.source === fSource) &&
            String(o.no).toLowerCase().includes(keyword.toLowerCase())
        );

        $('rsMeta').textContent = list.length + ' instruksi';

        if (!list.length) {
            $('rsBody').innerHTML =
                '<tr><td colspan="7"><div class="page-empty">Instruksi tidak ditemukan</div></td></tr>';
            return;
        }

        $('rsBody').innerHTML = list.map(o => `
            <tr>
                <td><strong>${esc(o.no)}</strong></td>
                <td class="col-nowrap">${esc(o.date)}</td>
                <td><span class="status info">${o.source === 'otomatis' ? 'Otomatis' : 'Manual'}</span></td>
                <td>${o.items.length} jenis</td>
                <td style="text-align:right"><strong>${rp(orderCost(o))}</strong></td>
                <td><span class="status ${STATUS[o.status].cls}">${STATUS[o.status].label}</span></td>
                <td>
                    ${o.status === 'approval'
                        ? `<button type="button" class="primary-button btn-sm" data-review="${esc(o.id)}">Tinjau</button>`
                        : `<button type="button" class="outline-button btn-sm" data-detail="${esc(o.no)}">Detail</button>`
                    }
                </td>
            </tr>
        `).join('');
    }

    // ---------- MODAL ----------
    const openModal  = html => $('modalRoot').innerHTML = `<div class="modal-backdrop" id="modalBg">${html}</div>`;
    const closeModal = () => $('modalRoot').innerHTML = '';

    function flash(msg, ok = true) {
        $('flashRoot').innerHTML = `<div class="flash-success" style="${ok ? '' : 'background:#fdecea;color:#b3261e'}">${msg}</div>`;
        setTimeout(() => $('flashRoot').innerHTML = '', 5000);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }


    // ---- REVIEW & EDIT DRAFT TERSIMPAN ----
    let reviewQty = {};
    let activeDraftId = null;

    function reviewSubtotal(line) {
        const qty = reviewQty[line.item] || 0;
        return qty * line.harga_beli;
    }

    function reviewTotal(order) {
        return order.items.reduce(
            (sum, line) => sum + reviewSubtotal(line),
            0
        );
    }

    function reviewPayload(order) {
        return {
            draft_id: order.id,
            asal: order.source,
            items: order.items
                .map(line => ({
                    bahan_id: Number(line.item),
                    qty_diminta:
                        (reviewQty[line.item] || 0) *
                        (Number(line.isi_per_kemasan) || 1),
                }))
                .filter(line => line.qty_diminta > 0),
        };
    }

    function showDraft(draftId) {
        const order = orders.find(o =>
            Number(o.id) === Number(draftId) &&
            o.status === 'approval'
        );

        if (!order) {
            flash('Draft tidak ditemukan atau sudah tidak menunggu persetujuan.', false);
            return;
        }

        activeDraftId = order.id;

        // Jumlah awal diambil dari database.
        reviewQty = {};

        order.items.forEach(line => {
            reviewQty[line.item] = Number(line.qty);
        });

        openModal(`
            <div class="modal-box wide" style="max-width:680px">
                <h4>Review Draft ${order.no}</h4>
                <p>Periksa dan sesuaikan jumlah bahan sebelum menyetujui instruksi pengiriman ke DC.</p>

                <div class="info-note">
                    Sumber: ${order.source === 'otomatis' ? 'Draft otomatis' : 'Reorder manual'}
                    · Status: Menunggu persetujuan
                </div>

                <div class="table-wrapper" style="margin-top:12px">
                    <table>
                        <thead>
                            <tr>
                                <th>Bahan & Informasi Kemasan</th>
                                <th>Jumlah Pesanan</th>
                                <th style="text-align:right">Estimasi Biaya</th>
                            </tr>
                        </thead>
                        <tbody>
                            
                        ${order.items.map(line => `
                            <tr>
                                <td style="min-width:210px">
                                    <strong>${line.name}</strong>

                                    <div style="font-size:11px;color:#8b735f;margin-top:5px">
                                        1 ${line.pack} =
                                        ${Number(line.isi_per_kemasan).toLocaleString('id-ID')}
                                        ${line.satuan}
                                    </div>

                                    <div style="font-size:11px;color:#8b735f;margin-top:3px">
                                        Harga beli per ${line.pack}:
                                        <strong>${rp(line.harga_beli)}</strong>
                                    </div>

                                    <div style="font-size:11px;color:#8b735f;margin-top:3px">
                                        Minimum pemesanan:
                                        <strong>${line.minimum_order_qty || 1} ${line.pack}</strong>
                                    </div>
                                </td>

                                <td style="min-width:190px">
                                    <div class="form-row">
                                        <input
                                            type="number"
                                            min="${line.minimum_order_qty || 1}"
                                            step="1"
                                            class="table-input"
                                            data-review-qty="${line.item}"
                                            value="${reviewQty[line.item]}"
                                        >
                                        <span class="form-unit">${line.pack}</span>
                                    </div>

                                    <div style="font-size:11px;color:#8b735f;margin-top:5px">
                                        Setara dengan
                                        ${(
                                            reviewQty[line.item] *
                                            Number(line.isi_per_kemasan)
                                        ).toLocaleString('id-ID')}
                                        ${line.satuan}
                                    </div>
                                </td>

                                <td style="text-align:right;white-space:nowrap">
                                    <strong id="review-sub-${line.item}">
                                        ${rp(reviewSubtotal(line))}
                                    </strong>
                                </td>
                            </tr>
                        `).join('')}

                        </tbody>
                    </table>
                </div>

                <div class="summary-box">
                    <div class="summary-line">
                        <span><b>Total estimasi biaya</b></span>
                        <span><b id="reviewTotal">${rp(reviewTotal(order))}</b></span>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>
                        Batal
                    </button>

                    <button type="button" class="outline-button" id="btnSaveDraft">
                        Simpan Perubahan
                    </button>

                    <button type="button" class="primary-button" id="btnApprove">
                        Setujui &amp; Teruskan ke DC
                    </button>
                </div>
            </div>
        `);
    }

    // ---- SIMPAN PERUBAHAN DRAFT ----
    async function saveDraft() {
        const order = orders.find(o =>
            Number(o.id) === Number(activeDraftId)
        );

        if (!order) return;

        const payload = reviewPayload(order);

        if (!payload.items.length) {
            flash('Minimal satu bahan harus memiliki jumlah pesanan lebih dari nol.', false);
            return;
        }

        const res = await fetch(URLS.update, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const json = await res.json().catch(() => ({}));

        if (!res.ok) {
            flash(json.message || 'Gagal menyimpan perubahan draft.', false);
            return;
        }

        closeModal();
        flash(`Perubahan draft ${json.no} berhasil disimpan.`);
        await loadData();
    }

    // ---- APPROVE DRAFT TERSIMPAN ----
    async function approveDraft() {
        const order = orders.find(o =>
            Number(o.id) === Number(activeDraftId)
        );

        if (!order) return;

        const payload = reviewPayload(order);

        if (!payload.items.length) {
            flash('Minimal satu bahan harus memiliki jumlah pesanan lebih dari nol.', false);
            return;
        }

        const res = await fetch(URLS.approve, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const json = await res.json().catch(() => ({}));

        if (!res.ok) {
            flash(json.message || 'Persetujuan draft gagal. Coba lagi.', false);
            return;
        }

        closeModal();
        flash(`Draft ${json.no} disetujui dan diteruskan ke antrean DC.`);
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
                <td style="text-align:right">${rp(l.qty * l.harga_beli)}</td>
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
        const moq = Number(ITEMS[mPick]?.minimum_order_qty) || 1;
        if (mAdd < moq) mAdd = moq;

        const options = Object.entries(ITEMS)
        .sort((a, b) => a[1].nama_bahan.localeCompare(b[1].nama_bahan))
        .map(([k, i]) => `
            <option value="${k}" ${k === mPick ? 'selected' : ''}>
                ${esc(i.nama_bahan)} (${esc(i.nama_kemasan)}) — MOQ ${i.minimum_order_qty || 1}
            </option>
        `)
        .join('');

        openModal(`
            <div class="modal-box wide">
                <h4>Reorder Manual ke DC</h4>
                <p>Untuk kebutuhan di luar restock otomatis, misalnya lonjakan pengunjung atau pengganti barang waste.</p>

                <div class="form-field" style="margin-top:14px">
                    <label class="form-label">Tambah Bahan</label>
                    <div class="form-row">
                        <select id="mPick" class="form-input">${options}</select>
                        <input type="number" min="${moq}" step="1" id="mAdd" class="form-input unit" value="${mAdd}">
                        <button type="button" class="outline-button" data-madd>Tambah</button>
                    </div>
                    <div class="form-hint">
                        MOQ: ${ITEMS[mPick]?.minimum_order_qty || 1} ${ITEMS[mPick]?.nama_kemasan || ''}
                    </div>
                </div>

                <div class="summary-box">
                    <div class="summary-title">DAFTAR BAHAN</div>
                    ${keys.length ? keys.map(k => `
                    <div class="form-row" style="margin-bottom:8px">
                        <span style="flex:1;font-size:10px">${ITEMS[k].nama_bahan}</span>
                        <input
                            type="number"
                            min="${ITEMS[k].minimum_order_qty || 1}"
                            step="1"
                            class="table-input qty"
                            data-mqty="${k}"
                            value="${mLines[k]}"
                        >
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

        if (t.closest('[data-review]')) {
            return showDraft(t.closest('[data-review]').dataset.review);
        }
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
        if (t.id === 'btnSaveDraft') return saveDraft();
        if (t.id === 'btnApprove') return approveDraft();
        if (t.id === 'btnSendManual')    return sendManual();
    });

    document.addEventListener('input', e => {
        const t = e.target;

        if (t.id === 'rsSearch') { keyword = t.value; renderTable(); }

        if (t.dataset.reviewQty) {
            const id = t.dataset.reviewQty;

            reviewQty[id] = Math.max(
                0,
                parseInt(t.value, 10) || 0
            );

            const order = orders.find(o =>
                Number(o.id) === Number(activeDraftId)
            );

            if (order) {
                const line = order.items.find(x =>
                    String(x.item) === String(id)
                );

                if (line) {
                    $('review-sub-' + id).textContent =
                        rp(reviewSubtotal(line));

                    $('reviewTotal').textContent =
                        rp(reviewTotal(order));

                    const baseQty =
                        reviewQty[id] * Number(line.isi_per_kemasan);

                    const baseInfo = t.parentElement.nextElementSibling;

                    if (baseInfo) {
                        baseInfo.textContent =
                            `Setara dengan ${baseQty.toLocaleString('id-ID')} ${line.satuan}`;
                    }
                }
            }
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
