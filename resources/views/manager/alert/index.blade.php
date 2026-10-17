@extends('layouts.app')

@section('title', 'Alert')
@section('description', 'Alert restock dan alert waste yang perlu perhatian.')

@section('content')

<section class="page active">

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">△</div>
            <div class="stat-content">
                <span class="stat-label">Alert Baru</span>
                <strong id="stAll">0</strong>
                <small class="danger-text">Perlu ditinjau</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">≡</div>
            <div class="stat-content">
                <span class="stat-label">Alert Restock</span>
                <strong id="stRestock">0 Item</strong>
                <small class="warning" id="stRestockSub">-</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">Rp</div>
            <div class="stat-content">
                <span class="stat-label">Alert Waste</span>
                <strong id="stWaste">0 Laporan</strong>
                <small class="danger-text" id="stWasteSub">-</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">↻</div>
            <div class="stat-content">
                <span class="stat-label">Draft Pengiriman</span>
                <strong id="stDraft">0 Draft</strong>
                <small id="stDraftSub">-</small>
            </div>
        </div>

    </div>

    <div id="flashRoot"></div>

    <div class="page-card section-gap">

        <div class="table-toolbar">
            <input type="text" id="alertSearch" class="search-input" placeholder="Cari alert...">
            <select id="alertType" class="select">
                <option value="semua">Semua Jenis</option>
                <option value="restock">Restock</option>
                <option value="waste">Waste</option>
            </select>
            <select id="alertStatus" class="select">
                <option value="semua">Semua Status</option>
                <option value="baru">Baru</option>
                <option value="selesai">Sudah Ditindaklanjuti</option>
            </select>
            <span class="table-meta" id="alertMeta"></span>
        </div>

        <div class="table-wrapper">
            <table class="table-fixed">
                <colgroup>
                    <col style="width: 9%">
                    <col style="width: 24%">
                    <col style="width: 31%">
                    <col style="width: 14%">
                    <col style="width: 12%">
                    <col style="width: 10%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Jenis</th>
                        <th>Alert</th>
                        <th>Keterangan</th>
                        <th>Waktu</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="alertBody"></tbody>
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

    const DRAFT_NO = '#RS-024';
    const MONTHS   = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    const $  = id => document.getElementById(id);
    const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');

    const fmtDate = d => {
        const [y, m, day] = d.split('-');
        return `${Number(day)} ${MONTHS[Number(m) - 1]} ${y}`;
    };

    // ---------- STATE ----------
    let keyword = '';
    let fType   = 'semua';
    let fStatus = 'semua';

    // stok di bawah / sama dengan batas minimum
    const LOW = Object.entries(ITEMS)
        .filter(([, i]) => i.stock <= i.min)
        .map(([key, i]) => ({ key, ...i }));

    // kebutuhan = stok minimum - stok aktual, dibulatkan ke atas ke kemasan utuh
    const suggest = i => Math.max(1, Math.ceil((i.min - i.stock) / i.size));

    const draftQty = {};
    LOW.forEach(i => draftQty[i.key] = suggest(i));

    let draftApproved = false;           // draft Instruksi Pengiriman sudah disetujui?
    const wasteDone   = {};              // { idMutasi: 'dievaluasi' | 'direorder' }

    let reorderQty = {};                 // qty reorder manual yang sedang dibuka

    // waktu alert restock = waktu mutasi terakhir (stok terakhir berubah)
    const lastMut = [...MUT].sort((a, b) => (b.date + b.time).localeCompare(a.date + a.time))[0];

    // ---------- HELPER ----------
    // 3430 ml (isi 700) => "4 Botol 630 ml"
    const fmtStock = (key, amount) => {
        const i = ITEMS[key];
        if (i.size === 1) return `${amount.toLocaleString('id-ID')} ${i.pack}`;
        const p = Math.floor(amount / i.size);
        const r = amount % i.size;
        return `${p} ${i.pack}` + (r ? ` ${r.toLocaleString('id-ID')} ${i.unit}` : '');
    };

    const lineValue = l => (Math.abs(l.amount) / ITEMS[l.item].size) * ITEMS[l.item].price;
    const wasteValue = m => m.lines.reduce((s, l) => s + lineValue(l), 0);

    const wasteLines = m => m.lines.map(l =>
        `${ITEMS[l.item].name} ${Math.abs(l.amount).toLocaleString('id-ID')} ${ITEMS[l.item].unit}`).join(', ');

    // ---------- BANGUN DAFTAR ALERT ----------
    function buildAlerts() {
        const list = [];

        LOW.forEach(i => {
            list.push({
                id: 'r-' + i.key,
                type: 'restock',
                sort: lastMut.date + ' ' + lastMut.time,
                title: (i.stock <= 0 ? 'Stok Habis — ' : 'Stok Menipis — ') + i.name,
                text: `Stok ${fmtStock(i.key, i.stock)} · minimum ${fmtStock(i.key, i.min)}`,
                when: fmtDate(lastMut.date) + ' · ' + lastMut.time,
                done: draftApproved,
                doneLabel: 'Diteruskan ke DC',
                key: i.key,
            });
        });

        MUT.filter(m => m.type === 'waste').forEach(m => {
            const reason = m.ref.replace('Waste · ', '');
            list.push({
                id: 'w-' + m.id,
                type: 'waste',
                sort: m.date + ' ' + m.time,
                title: 'Waste — ' + reason,
                text: `${wasteLines(m)} · kerugian ${rp(wasteValue(m))}`,
                when: fmtDate(m.date) + ' · ' + m.time,
                done: !!wasteDone[m.id],
                doneLabel: wasteDone[m.id] === 'direorder' ? 'Reorder ke DC' : 'Sudah Dievaluasi',
                mut: m,
            });
        });

        // yang baru di atas, lalu yang terbaru
        return list.sort((a, b) =>
            (a.done - b.done) || b.sort.localeCompare(a.sort));
    }

    // ---------- RENDER ----------
    function renderStats(all) {
        const baru     = all.filter(a => !a.done);
        const restock  = baru.filter(a => a.type === 'restock');
        const waste    = baru.filter(a => a.type === 'waste');
        const habis    = restock.filter(a => ITEMS[a.key].stock <= 0).length;
        const loss     = waste.reduce((s, a) => s + wasteValue(a.mut), 0);

        $('stAll').textContent        = baru.length;
        $('stRestock').textContent    = restock.length + ' Item';
        $('stRestockSub').textContent = habis ? habis + ' item habis' : 'Belum ada yang habis';
        $('stWaste').textContent      = waste.length + ' Laporan';
        $('stWasteSub').textContent   = waste.length ? rp(loss) + ' total kerugian' : 'Tidak ada waste baru';

        const draftOpen = LOW.length && !draftApproved;
        $('stDraft').textContent    = (draftOpen ? 1 : 0) + ' Draft';
        $('stDraftSub').textContent = LOW.length
            ? (draftApproved ? DRAFT_NO + ' sudah diteruskan ke DC' : DRAFT_NO + ' menunggu approval')
            : 'Tidak ada draft';
    }

    function renderTable() {
        const all = buildAlerts();
        renderStats(all);

        const list = all.filter(a =>
            (fType === 'semua' || a.type === fType) &&
            (fStatus === 'semua' || (fStatus === 'baru' ? !a.done : a.done)) &&
            (a.title + ' ' + a.text).toLowerCase().includes(keyword.toLowerCase()));

        $('alertMeta').textContent = list.length + ' alert';

        if (!list.length) {
            $('alertBody').innerHTML = '<tr><td colspan="6"><div class="page-empty">Alert tidak ditemukan</div></td></tr>';
            return;
        }

        $('alertBody').innerHTML = list.map(a => `
            <tr>
                <td>${a.type === 'restock'
                    ? '<span class="status warning">Restock</span>'
                    : '<span class="status danger">Waste</span>'}</td>
                <td><strong>${a.title}</strong></td>
                <td>${a.text}</td>
                <td class="col-nowrap">${a.when}</td>
                <td>${a.done
                    ? `<span class="status success">${a.doneLabel}</span>`
                    : '<span class="status info">Baru</span>'}</td>
                <td><button type="button" class="outline-button btn-sm" data-detail="${a.id}">Detail</button></td>
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

    // ---- detail alert restock ----
    function showRestock(key) {
        const i    = ITEMS[key];
        const need = Math.max(0, i.min - i.stock);
        const qty  = draftQty[key];

        openModal(`
            <div class="modal-box wide">
                <h4>${i.stock <= 0 ? 'Stok Habis' : 'Stok Menipis'} · ${i.name}</h4>
                <p>Sistem mendeteksi stok mencapai atau di bawah batas minimum.</p>

                <div class="summary-box">
                    <div class="summary-line"><span>Stok saat ini</span><span>${fmtStock(key, i.stock)}</span></div>
                    <div class="summary-line"><span>Batas minimum</span><span>${fmtStock(key, i.min)}</span></div>
                    <div class="summary-line bad"><span>Kekurangan (min − stok)</span><span>${need.toLocaleString('id-ID')} ${i.unit}</span></div>
                </div>

                <div class="summary-box">
                    <div class="summary-title">DRAFT INSTRUKSI PENGIRIMAN ${DRAFT_NO}</div>
                    <div class="summary-line"><span>Saran jumlah kirim</span><span>${qty} ${i.pack}</span></div>
                    <div class="summary-line"><span>Harga per ${i.pack}</span><span>${rp(i.price)}</span></div>
                    <div class="summary-line"><span><b>Estimasi biaya</b></span><span><b>${rp(qty * i.price)}</b></span></div>
                </div>

                <div class="info-note">${draftApproved
                    ? `Draft ${DRAFT_NO} sudah disetujui dan diteruskan ke antrean DC.`
                    : `Item ini termasuk dalam draft ${DRAFT_NO} (${LOW.length} item). Draft harus disetujui Manager sebelum diteruskan ke DC.`}</div>

                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>Tutup</button>
                    ${draftApproved ? '' : `<button type="button" class="primary-button" data-open-draft>Tinjau Draft</button>`}
                </div>
            </div>`);
    }

    // ---- draft instruksi pengiriman (semua item) ----
    const draftTotal = () => LOW.reduce((s, i) => s + (draftQty[i.key] || 0) * i.price, 0);
    const draftCount = () => LOW.reduce((s, i) => s + (draftQty[i.key] || 0), 0);

    function showDraft() {
        openModal(`
            <div class="modal-box wide" style="max-width:680px">
                <h4>Draft Instruksi Pengiriman ${DRAFT_NO}</h4>
                <p>Dibuat otomatis oleh sistem (stok minimum − stok aktual). Sesuaikan jumlah bila perlu, lalu setujui agar diteruskan ke DC.</p>

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
                    <button type="button" class="primary-button" id="btnApproveDraft" ${draftCount() ? '' : 'disabled'}>Setujui &amp; Teruskan ke DC</button>
                </div>
            </div>`);
    }

    function approveDraft() {
        draftApproved = true;
        closeModal();
        renderTable();
        flash(`Draft ${DRAFT_NO} disetujui (${draftCount()} kemasan, ${rp(draftTotal())}) dan diteruskan ke antrean DC.`);
    }

    // ---- detail alert waste ----
    function showWaste(id) {
        const m = MUT.find(x => x.id === Number(id));
        if (!m) return;

        const reason = m.ref.replace('Waste · ', '');
        const state  = wasteDone[m.id];

        const lines = m.lines.map(l => `
            <div class="summary-line">
                <span>${ITEMS[l.item].name}</span>
                <span>${Math.abs(l.amount).toLocaleString('id-ID')} ${ITEMS[l.item].unit} · ${rp(lineValue(l))}</span>
            </div>`).join('');

        openModal(`
            <div class="modal-box wide">
                <h4>Waste · ${reason}</h4>
                <p>${fmtDate(m.date)} · ${m.time} · dicatat oleh Kepala Shift</p>

                <div class="summary-box">
                    <div class="summary-title">BARANG TERBUANG</div>
                    ${lines}
                    <div class="summary-line bad"><span><b>Total kerugian</b></span><span><b>${rp(wasteValue(m))}</b></span></div>
                </div>

                <div class="info-note">${state
                    ? (state === 'direorder' ? 'Reorder manual sudah dikirim ke antrean DC.' : 'Alert ini sudah dievaluasi.')
                    : 'Stok cabang sudah berkurang otomatis. Evaluasi dampaknya terhadap persediaan, lalu lakukan reorder manual ke DC bila dibutuhkan.'}</div>

                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>Tutup</button>
                    ${state ? '' : `
                    <button type="button" class="outline-button" data-evaluate="${m.id}">Tandai Dievaluasi</button>
                    <button type="button" class="primary-button" data-reorder="${m.id}">Reorder Manual</button>`}
                </div>
            </div>`);
    }

    // ---- reorder manual dari alert waste ----
    function showReorder(id) {
        const m = MUT.find(x => x.id === Number(id));
        if (!m) return;

        reorderQty = {};
        m.lines.forEach(l => {
            const i = ITEMS[l.item];
            reorderQty[l.item] = Math.max(1, Math.ceil(Math.abs(l.amount) / i.size));
        });

        const total = () => Object.entries(reorderQty).reduce((s, [k, q]) => s + q * ITEMS[k].price, 0);

        openModal(`
            <div class="modal-box wide">
                <h4>Reorder Manual ke DC</h4>
                <p>Jumlah awal disesuaikan dengan barang yang terbuang. Ubah bila perlu.</p>

                <div class="summary-box">
                    ${m.lines.map(l => `
                    <div class="form-field" style="margin-bottom:10px">
                        <label class="form-label">${ITEMS[l.item].name}</label>
                        <div class="form-row">
                            <input type="number" min="0" step="1" class="form-input" data-reorder-qty="${l.item}" value="${reorderQty[l.item]}">
                            <span class="form-unit">${ITEMS[l.item].pack}</span>
                        </div>
                    </div>`).join('')}
                    <div class="summary-line"><span><b>Estimasi biaya</b></span><span><b id="reorderTotal">${rp(total())}</b></span></div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>Batal</button>
                    <button type="button" class="primary-button" id="btnSendReorder" data-wid="${m.id}">Kirim ke DC</button>
                </div>
            </div>`);
    }

    function sendReorder(id) {
        const qty = Object.values(reorderQty).reduce((s, q) => s + q, 0);

        if (qty <= 0) {
            flash('Jumlah reorder belum diisi.');
            return;
        }

        wasteDone[id] = 'direorder';
        closeModal();
        renderTable();
        flash(`Reorder manual (${qty} kemasan) dikirim ke antrean DC.`);
    }

    // ---------- EVENT ----------
    document.addEventListener('click', e => {
        const t = e.target;

        const d = t.closest('[data-detail]');
        if (d) {
            const id = d.dataset.detail;
            return id.startsWith('r-') ? showRestock(id.slice(2)) : showWaste(id.slice(2));
        }

        if (t.closest('[data-open-draft]')) return showDraft();
        if (t.closest('[data-evaluate]'))   { wasteDone[t.closest('[data-evaluate]').dataset.evaluate] = 'dievaluasi'; closeModal(); renderTable(); return flash('Alert waste ditandai sudah dievaluasi.'); }
        if (t.closest('[data-reorder]'))    return showReorder(t.closest('[data-reorder]').dataset.reorder);
        if (t.closest('[data-close]') || t.id === 'modalBg') return closeModal();
        if (t.id === 'btnApproveDraft')     return approveDraft();
        if (t.id === 'btnSendReorder')      return sendReorder(t.dataset.wid);
    });

    document.addEventListener('input', e => {
        const t = e.target;

        if (t.id === 'alertSearch') { keyword = t.value; renderTable(); }

        if (t.dataset.qty) {
            const key = t.dataset.qty;
            draftQty[key] = Math.max(0, parseInt(t.value, 10) || 0);

            $('sub-' + key).textContent = rp(draftQty[key] * ITEMS[key].price);
            $('draftTotal').textContent = rp(draftTotal());
            $('btnApproveDraft').disabled = !draftCount();
        }

        if (t.dataset.reorderQty) {
            const key = t.dataset.reorderQty;
            reorderQty[key] = Math.max(0, parseInt(t.value, 10) || 0);

            $('reorderTotal').textContent = rp(
                Object.entries(reorderQty).reduce((s, [k, q]) => s + q * ITEMS[k].price, 0));
        }
    });

    document.addEventListener('change', e => {
        if (e.target.id === 'alertType')   { fType   = e.target.value; renderTable(); }
        if (e.target.id === 'alertStatus') { fStatus = e.target.value; renderTable(); }
    });

    renderTable();

})();
</script>

@endsection