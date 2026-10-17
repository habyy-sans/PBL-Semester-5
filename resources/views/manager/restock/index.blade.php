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
                <option value="konfirmasi">Menunggu Konfirmasi IC</option>
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

    // ---------- DATA (nanti dari backend) ----------
    const ITEMS = @json(config('demo.items'));
    const MUT   = @json(config('demo.mutations'));
    const SHIP  = @json(config('demo.shipments'));
    const ORD   = @json(config('demo_restock.orders'));

    const DRAFT_NO = '#RS-024';
    const MONTHS   = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    const STATUS = {
        approval:   { label: 'Menunggu Approval',     cls: 'warning' },
        disetujui:  { label: 'Diteruskan ke DC',      cls: 'info'    },
        dikirim:    { label: 'Dikirim DC',            cls: 'info'    },
        konfirmasi: { label: 'Menunggu Konfirmasi IC', cls: 'warning' },
        diterima:   { label: 'Diterima',              cls: 'success' },
    };

    // status Surat Jalan => status Instruksi Pengiriman
    const SHIP_MAP = { dikirim: 'dikirim', menunggu: 'konfirmasi', diterima: 'diterima' };

    const REASONS = ['Lonjakan pengunjung', 'Pengganti barang waste', 'Stok kurang dari perkiraan', 'Lainnya'];

    const $  = id => document.getElementById(id);
    const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');

    const fmtDate = d => {
        const [y, m, day] = d.split('-');
        return `${Number(day)} ${MONTHS[Number(m) - 1]} ${y}`;
    };

    // 3430 ml (isi 700) => "4 Botol 630 ml"
    const fmtStock = (key, amount) => {
        const i = ITEMS[key];
        if (i.size === 1) return `${amount.toLocaleString('id-ID')} ${i.pack}`;
        const p = Math.floor(amount / i.size);
        const r = amount % i.size;
        return `${p} ${i.pack}` + (r ? ` ${r.toLocaleString('id-ID')} ${i.unit}` : '');
    };

    // ---------- STATE ----------
    let keyword = '';
    let fStatus = 'semua';
    let fSource = 'semua';

    // "hari ini" pada demo = tanggal mutasi terakhir
    const lastMut = [...MUT].sort((a, b) => (b.date + b.time).localeCompare(a.date + a.time))[0];

    // bahan di bawah / sama dengan batas minimum => isi draft otomatis
    const LOW = Object.entries(ITEMS)
        .filter(([, i]) => i.stock <= i.min)
        .map(([key, i]) => ({ key, ...i }));

    // kebutuhan = stok minimum - stok aktual, dibulatkan ke atas ke kemasan utuh
    const suggest = i => Math.max(1, Math.ceil((i.min - i.stock) / i.size));

    const draftQty = {};
    LOW.forEach(i => draftQty[i.key] = suggest(i));

    let draftApproved = false;
    const created = [];                    // reorder manual yang dibuat di sesi ini

    // reorder manual (dialog)
    let mLines  = {};
    let mPick   = Object.keys(ITEMS)[0];
    let mAdd    = 1;
    let mReason = REASONS[0];
    let mNote   = '';

    // ---------- BANGUN DAFTAR INSTRUKSI ----------
    const history = () => ORD.map(o => {
        const sh = SHIP.find(s => s.no === o.shipment);
        return {
            no: o.no, date: o.date, source: o.source, reason: o.reason || '',
            shipment: o.shipment,
            status: SHIP_MAP[sh.status],
            items: sh.items.map(i => ({ key: i.item, qty: i.sent, sent: i.sent, received: i.received, note: i.note })),
        };
    });

    const draftOrder = () => ({
        no: DRAFT_NO, date: lastMut.date, source: 'otomatis', reason: '', shipment: null,
        status: draftApproved ? 'disetujui' : 'approval',
        items: LOW.filter(i => draftApproved ? draftQty[i.key] > 0 : true)
                  .map(i => ({ key: i.key, qty: draftQty[i.key] })),
    });

    const allOrders = () => [
        ...(LOW.length ? [draftOrder()] : []),
        ...created.slice().reverse(),
        ...history(),
    ];

    const orderCost = o => o.items.reduce((s, i) => s + i.qty * ITEMS[i.key].price, 0);

    const draftTotal = () => LOW.reduce((s, i) => s + (draftQty[i.key] || 0) * i.price, 0);
    const draftCount = () => LOW.reduce((s, i) => s + (draftQty[i.key] || 0), 0);

    // ---------- RENDER ----------
    function renderStats(all) {
        const count = st => all.filter(o => o.status === st).length;

        const approval = count('approval');
        const ship     = count('dikirim') + count('konfirmasi');

        $('stApproval').textContent    = approval + ' Draft';
        $('stApprovalSub').textContent = approval ? DRAFT_NO + ' perlu ditinjau' : 'Tidak ada draft';
        $('stQueue').textContent       = count('disetujui') + ' Instruksi';
        $('stShip').textContent        = ship + ' Instruksi';
        $('stShipSub').textContent     = count('konfirmasi') + ' menunggu konfirmasi IC';
        $('stDone').textContent        = count('diterima') + ' Instruksi';
    }

    function renderTable() {
        const all = allOrders();
        renderStats(all);

        const list = all.filter(o =>
            (fStatus === 'semua' || o.status === fStatus) &&
            (fSource === 'semua' || o.source === fSource) &&
            o.no.toLowerCase().includes(keyword.toLowerCase()));

        $('rsMeta').textContent = list.length + ' instruksi';

        if (!list.length) {
            $('rsBody').innerHTML = '<tr><td colspan="7"><div class="page-empty">Instruksi tidak ditemukan</div></td></tr>';
            return;
        }

        $('rsBody').innerHTML = list.map(o => `
            <tr>
                <td><strong>${o.no}</strong></td>
                <td class="col-nowrap">${fmtDate(o.date)}</td>
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

    function flash(msg) {
        $('flashRoot').innerHTML = `<div class="flash-success">${msg}</div>`;
        setTimeout(() => $('flashRoot').innerHTML = '', 4000);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ---- tinjau draft otomatis ----
    function showDraft() {
        openModal(`
            <div class="modal-box wide" style="max-width:680px">
                <h4>Draft Instruksi Pengiriman ${DRAFT_NO}</h4>
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
                            ${LOW.map(i => `
                            <tr>
                                <td><strong>${i.name}</strong></td>
                                <td>${fmtStock(i.key, i.stock)}</td>
                                <td>${fmtStock(i.key, i.min)}</td>
                                <td>
                                    <div class="form-row">
                                        <input type="number" min="0" step="1" class="table-input qty" data-qty="${i.key}" value="${draftQty[i.key]}">
                                        <span class="form-unit">${i.pack}</span>
                                    </div>
                                </td>
                                <td style="text-align:right" id="sub-${i.key}">${rp(draftQty[i.key] * i.price)}</td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>

                <div class="summary-box">
                    <div class="summary-line"><span><b>Total estimasi biaya</b></span><span><b id="draftTotal">${rp(draftTotal())}</b></span></div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>Batal</button>
                    <button type="button" class="primary-button" id="btnApprove" ${draftCount() ? '' : 'disabled'}>Setujui &amp; Teruskan ke DC</button>
                </div>
            </div>`);
    }

    function approveDraft() {
        draftApproved = true;
        closeModal();
        renderTable();
        flash(`Draft ${DRAFT_NO} disetujui (${draftCount()} kemasan, ${rp(draftTotal())}) dan diteruskan ke antrean DC.`);
    }

    // ---- detail instruksi ----
    function showOrder(no) {
        const o = allOrders().find(x => x.no === no);
        if (!o) return;

        const hasShip = !!o.shipment;

        const rows = o.items.map(i => {
            const it = ITEMS[i.key];
            return `
            <tr>
                <td><strong>${it.name}</strong></td>
                <td>${i.qty} ${it.pack}</td>
                ${hasShip ? `
                <td>${i.sent} ${it.pack}</td>
                <td>${i.received === null || i.received === undefined ? '-' : i.received + ' ' + it.pack}</td>` : ''}
                <td style="text-align:right">${rp(i.qty * it.price)}</td>
            </tr>`;
        }).join('');

        const notes = hasShip
            ? o.items.filter(i => i.note).map(i => `${ITEMS[i.key].name}: ${i.note}`).join('. ')
            : '';

        const info = {
            approval:   'Menunggu persetujuan Manager.',
            disetujui:  'Sudah disetujui dan masuk antrean kerja DC. DC akan menyiapkan barang dan menerbitkan Surat Jalan Digital.',
            dikirim:    `DC sudah mengirim barang lewat Surat Jalan ${o.shipment}. Menunggu barang tiba di cabang.`,
            konfirmasi: `Surat Jalan ${o.shipment} sudah tiba dan menunggu Kepala Shift (IC) memeriksa serta mengonfirmasi penerimaan.`,
            diterima:   `Surat Jalan ${o.shipment} sudah dikonfirmasi IC dan stok cabang sudah bertambah.`,
        }[o.status];

        openModal(`
            <div class="modal-box wide" style="max-width:680px">
                <h4>Instruksi Pengiriman ${o.no}</h4>
                <p>${fmtDate(o.date)} · ${o.source === 'otomatis' ? 'Otomatis dari alert restock' : 'Reorder manual' + (o.reason ? ' · ' + o.reason : '')}${hasShip ? ' · ' + o.shipment : ''}</p>

                <div class="table-wrapper" style="margin-top:12px">
                    <table>
                        <thead>
                            <tr>
                                <th>Bahan</th>
                                <th>Dipesan</th>
                                ${hasShip ? '<th>Dikirim</th><th>Diterima</th>' : ''}
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

                <div class="info-note">${info}</div>
                ${notes ? `<div class="info-note error">Catatan penerimaan — ${notes}</div>` : ''}

                <div class="modal-actions">
                    <button type="button" class="primary-button" data-close>Tutup</button>
                </div>
            </div>`);
    }

    // ---- reorder manual ----
    const mTotal = () => Object.entries(mLines).reduce((s, [k, q]) => s + q * ITEMS[k].price, 0);
    const mCount = () => Object.values(mLines).reduce((s, q) => s + q, 0);

    function showManual() {
        const keys = Object.keys(mLines);

        const options = Object.entries(ITEMS)
            .sort((a, b) => a[1].name.localeCompare(b[1].name))
            .map(([k, i]) => `<option value="${k}" ${k === mPick ? 'selected' : ''}>${i.name} (${i.pack})</option>`)
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
                    <div class="form-hint">Jumlah dalam kemasan (${ITEMS[mPick].pack}).</div>
                </div>

                <div class="summary-box">
                    <div class="summary-title">DAFTAR BAHAN</div>
                    ${keys.length ? keys.map(k => `
                    <div class="form-row" style="margin-bottom:8px">
                        <span style="flex:1;font-size:10px">${ITEMS[k].name}</span>
                        <input type="number" min="0" step="1" class="table-input qty" data-mqty="${k}" value="${mLines[k]}">
                        <span class="form-unit">${ITEMS[k].pack}</span>
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

    function sendManual() {
        if (!mCount()) return;

        const no = '#RS-' + String(25 + created.length).padStart(3, '0');

        created.push({
            no, date: lastMut.date, source: 'manual', reason: mReason, note: mNote, shipment: null,
            status: 'disetujui',
            items: Object.entries(mLines).filter(([, q]) => q > 0).map(([key, qty]) => ({ key, qty })),
        });

        const total = mTotal();

        mLines = {}; mAdd = 1; mReason = REASONS[0]; mNote = '';

        closeModal();
        renderTable();
        flash(`Reorder manual ${no} (${rp(total)}) dikirim ke antrean DC.`);
    }

    // ---------- EVENT ----------
    document.addEventListener('click', e => {
        const t = e.target;

        if (t.closest('[data-review]'))  return showDraft();
        if (t.closest('[data-detail]'))  return showOrder(t.closest('[data-detail]').dataset.detail);
        if (t.closest('[data-madd]')) {
            mLines[mPick] = (mLines[mPick] || 0) + Math.max(1, mAdd);
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
            const key = t.dataset.qty;
            draftQty[key] = Math.max(0, parseInt(t.value, 10) || 0);

            $('sub-' + key).textContent = rp(draftQty[key] * ITEMS[key].price);
            $('draftTotal').textContent = rp(draftTotal());
            $('btnApprove').disabled = !draftCount();
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

    renderTable();

})();
</script>

@endsection