@extends('layouts.app')

@section('title', 'Riwayat Transaksi')
@section('description', 'Lihat daftar transaksi penjualan yang sudah dilakukan.')

@section('content')

<section class="page active">

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">▤</div>
            <div class="stat-content">
                <span class="stat-label">Total Transaksi</span>
                <strong id="statCount">0</strong>
                <small id="statVoid">0 dibatalkan</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">↻</div>
            <div class="stat-content">
                <span class="stat-label">Omzet</span>
                <strong id="statRevenue">Rp 0</strong>
                <small>Transaksi selesai</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">▣</div>
            <div class="stat-content">
                <span class="stat-label">Rata-rata / Transaksi</span>
                <strong id="statAvg">Rp 0</strong>
                <small>Transaksi selesai</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">△</div>
            <div class="stat-content">
                <span class="stat-label">Menu Terjual</span>
                <strong id="statQty">0</strong>
                <small>Total porsi</small>
            </div>
        </div>

    </div>

    <div class="page-card section-gap">

        <div class="table-toolbar">
            <input type="text" id="trxSearch" class="search-input" placeholder="Cari no. transaksi / menu...">
            <select id="trxShift" class="select">
                <option value="semua">Semua Shift</option>
                <option value="1">Shift 1 (07.00 – 16.00)</option>
                <option value="2">Shift 2 (16.01 – 23.59)</option>
            </select>
            <select id="trxHour" class="select"></select>
            <select id="trxMethod" class="select">
                <option value="semua">Semua Metode</option>
                <option value="tunai">Tunai</option>
                <option value="qris">QRIS</option>
                <option value="debit">Debit</option>
            </select>
            <select id="trxStatus" class="select">
                <option value="semua">Semua Status</option>
                <option value="selesai">Selesai</option>
                <option value="batal">Batal</option>
            </select>
            <span class="table-meta" id="trxMeta"></span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No. Transaksi</th>
                        <th>Waktu</th>
                        <th>Item</th>
                        <th>Metode</th>
                        <th style="text-align:right">Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="trxBody"></tbody>
            </table>
        </div>

    </div>

</section>

<div id="modalRoot"></div>

<script>
(() => {

    // ---------- DATA (nanti dari backend) ----------
    const ITEMS = @json(config('demo.items'));
    const MENUS = @json(config('demo.menus'));
    const TRX   = @json(config('demo_transactions.list'));

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const METHOD_LABEL = { tunai: 'Tunai', qris: 'QRIS', debit: 'Debit' };

    // Shift 1: 07.00 – 16.00  |  Shift 2: 16.01 – 23.59  (dalam menit sejak 00.00)
    const SHIFTS = {
        1: { label: 'Shift 1', from: 7 * 60,      to: 16 * 60,      hours: [7, 16]  },
        2: { label: 'Shift 2', from: 16 * 60 + 1, to: 23 * 60 + 59, hours: [16, 23] },
    };

    let keyword = '';
    let fShift  = 'semua';
    let fHour   = 'semua';
    let fMethod = 'semua';
    let fStatus = 'semua';

    const $  = id => document.getElementById(id);
    const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');

    const fmtDate = d => {
        const [y, m, day] = d.split('-');
        return `${Number(day)} ${MONTHS[Number(m) - 1]} ${y}`;
    };

    // ---------- HITUNGAN ----------
    const trxTotal = t => t.items.reduce((s, i) => s + MENUS[i.menu].price * i.qty, 0);
    const trxQty   = t => t.items.reduce((s, i) => s + i.qty, 0);

    const trxHpp = t => t.items.reduce((s, i) =>
        s + i.qty * Object.entries(MENUS[i.menu].recipe)
            .reduce((c, [item, amt]) => c + amt * (ITEMS[item].price / ITEMS[item].size), 0), 0);

    // bahan terpakai (satuan terkecil) sesuai SOP
    const trxUsage = t => {
        const u = {};
        t.items.forEach(i => {
            Object.entries(MENUS[i.menu].recipe).forEach(([item, amt]) => {
                u[item] = (u[item] || 0) + amt * i.qty;
            });
        });
        return u;
    };

    const itemText = t => t.items.map(i => `${MENUS[i.menu].name} × ${i.qty}`).join(', ');

    // ---------- SHIFT & JAM ----------
    const toMinutes = time => {
        const [h, m] = time.split(':').map(Number);
        return h * 60 + m;
    };

    const hourOf = time => Number(time.split(':')[0]);

    const shiftOf = time => {
        const m = toMinutes(time);
        return Object.keys(SHIFTS).find(k => m >= SHIFTS[k].from && m <= SHIFTS[k].to) || null;
    };

    const pad = n => String(n).padStart(2, '0');

    // ---------- FILTER ----------
    const filtered = () => TRX.filter(t =>
        (fShift  === 'semua' || shiftOf(t.time) === fShift) &&
        (fHour   === 'semua' || hourOf(t.time) === Number(fHour)) &&
        (fMethod === 'semua' || t.method === fMethod) &&
        (fStatus === 'semua' || t.status === fStatus) &&
        (t.no.toLowerCase().includes(keyword.toLowerCase()) ||
         itemText(t).toLowerCase().includes(keyword.toLowerCase())));

    // ---------- RENDER ----------
    // opsi jam menyesuaikan shift yang dipilih (semua shift = jam 07 s/d 23)
    function renderHourOptions() {
        const [from, to] = fShift === 'semua' ? [7, 23]
            : SHIFTS[fShift].hours;

        const hours = [];
        for (let h = from; h <= to; h++) hours.push(h);

        if (fHour !== 'semua' && !hours.includes(Number(fHour))) fHour = 'semua';

        $('trxHour').innerHTML = '<option value="semua">Semua Jam</option>' +
            hours.map(h => `<option value="${h}" ${String(h) === String(fHour) ? 'selected' : ''}>Jam ${pad(h)}.00 – ${pad(h)}.59</option>`).join('');
    }

    function renderStats(list) {
        const done = list.filter(t => t.status === 'selesai');
        const rev  = done.reduce((s, t) => s + trxTotal(t), 0);

        $('statCount').textContent   = done.length;
        $('statVoid').textContent    = list.filter(t => t.status === 'batal').length + ' dibatalkan';
        $('statRevenue').textContent = rp(rev);
        $('statAvg').textContent     = rp(done.length ? rev / done.length : 0);
        $('statQty').textContent     = done.reduce((s, t) => s + trxQty(t), 0);
    }

    function renderTable() {
        const list = filtered();

        renderStats(list);
        $('trxMeta').textContent = list.length + ' transaksi';

        if (!list.length) {
            $('trxBody').innerHTML = '<tr><td colspan="7"><div class="page-empty">Transaksi tidak ditemukan</div></td></tr>';
            return;
        }

        $('trxBody').innerHTML = list.map(t => `
            <tr>
                <td><strong>${t.no}</strong></td>
                <td>${fmtDate(t.date)} · ${t.time}</td>
                <td>${itemText(t)}</td>
                <td><span class="status info">${METHOD_LABEL[t.method]}</span></td>
                <td style="text-align:right"><strong>${rp(trxTotal(t))}</strong></td>
                <td>${t.status === 'selesai'
                    ? '<span class="status success">Selesai</span>'
                    : '<span class="status danger">Batal</span>'}</td>
                <td><button type="button" class="outline-button btn-sm" data-detail="${t.no}">Detail</button></td>
            </tr>`).join('');
    }

    // ---------- MODAL ----------
    const openModal  = html => $('modalRoot').innerHTML = `<div class="modal-backdrop" id="modalBg">${html}</div>`;
    const closeModal = () => $('modalRoot').innerHTML = '';

    function showDetail(no) {
        const t = TRX.find(x => x.no === no);
        if (!t) return;

        const total = trxTotal(t);
        const batal = t.status === 'batal';

        const lines = t.items.map(i => `
            <div class="summary-line">
                <span>${MENUS[i.menu].name} × ${i.qty}</span>
                <span>${rp(MENUS[i.menu].price * i.qty)}</span>
            </div>`).join('');

        const pay = `
            <div class="summary-line"><span>Metode</span><span>${METHOD_LABEL[t.method]}</span></div>
            ${t.method === 'tunai' && t.cash ? `
            <div class="summary-line"><span>Uang diterima</span><span>${rp(t.cash)}</span></div>
            <div class="summary-line"><span>Kembalian</span><span>${rp(t.cash - total)}</span></div>` : ''}`;

        let extra = '';

        if (batal) {
            extra = `<div class="info-note error">Transaksi dibatalkan. ${t.reason || ''} Stok bahan tidak terpotong.</div>`;
        } else {
            const used = Object.entries(trxUsage(t)).map(([item, amt]) => `
                <div class="summary-line">
                    <span>${ITEMS[item].name}</span>
                    <span>−${amt.toLocaleString('id-ID')} ${ITEMS[item].unit}</span>
                </div>`).join('');
            const cost = trxHpp(t);

            extra = `
                <div class="summary-box">
                    <div class="summary-title">BAHAN TERPAKAI (SESUAI SOP)</div>
                    ${used}
                </div>
                <div class="summary-box">
                    <div class="summary-line"><span>Estimasi HPP</span><span>${rp(cost)}</span></div>
                    <div class="summary-line"><span>Estimasi laba kotor</span><span><b>${rp(total - cost)}</b></span></div>
                </div>`;
        }

        openModal(`
            <div class="modal-box wide">
                <h4>Detail ${t.no}</h4>
                <p>${fmtDate(t.date)} · ${t.time} · ${shiftOf(t.time) ? SHIFTS[shiftOf(t.time)].label : '-'} · ${t.cashier}</p>
                <div class="summary-box">
                    ${lines}
                    <div class="summary-line"><span><b>Total</b></span><span><b>${rp(total)}</b></span></div>
                    ${pay}
                </div>
                ${extra}
                <div class="modal-actions">
                    <button type="button" class="primary-button" data-close>Tutup</button>
                </div>
            </div>`);
    }

    // ---------- EVENT ----------
    document.addEventListener('click', e => {
        const t = e.target;

        if (t.closest('[data-detail]')) return showDetail(t.closest('[data-detail]').dataset.detail);
        if (t.closest('[data-close]') || t.id === 'modalBg') return closeModal();
    });

    document.addEventListener('input', e => {
        if (e.target.id === 'trxSearch') { keyword = e.target.value; renderTable(); }
    });

    document.addEventListener('change', e => {
        if (e.target.id === 'trxShift')  { fShift  = e.target.value; renderHourOptions(); renderTable(); }
        if (e.target.id === 'trxHour')   { fHour   = e.target.value; renderTable(); }
        if (e.target.id === 'trxMethod') { fMethod = e.target.value; renderTable(); }
        if (e.target.id === 'trxStatus') { fStatus = e.target.value; renderTable(); }
    });

    renderHourOptions();
    renderTable();

})();
</script>

@endsection