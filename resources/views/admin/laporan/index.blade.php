@extends('layouts.app')

@section('title', 'Laporan Seluruh Cabang')
@section('description', 'Laporan mutasi stok dan laba rugi seluruh cabang.')

@section('content')

@php
    $defaultStore = config('demo.default_store');

    // penjualan Store 1 dari data demo; Store 2 & 3 dari data dummy
    $trx = collect(config('demo_transactions.list'))
        ->map(fn ($t) => array_merge($t, ['store' => $defaultStore]))
        ->merge(config('demo_store_sales.transactions', []))
        ->values();

    $waste = collect(config('demo.mutations'))
        ->where('type', 'waste')
        ->map(fn ($m) => array_merge($m, ['store' => $defaultStore]))
        ->merge(config('demo_store_sales.waste', []))
        ->values();
@endphp

<section class="page active">

    <div class="table-toolbar">
        <select id="rpStore" class="select"></select>
        <select id="rpDate" class="select"></select>
        <span class="table-meta" id="rpMeta"></span>
        <button type="button" class="outline-button" id="btnCsv">Unduh CSV</button>
    </div>

    <!-- RINGKASAN -->
    <div class="report-grid">

        <div class="report-box">
            <span>Omzet</span>
            <strong id="rpOmzet">Rp 0</strong>
        </div>

        <div class="report-box">
            <span>HPP</span>
            <strong id="rpHpp">Rp 0</strong>
        </div>

        <div class="report-box">
            <span>Kerugian Waste</span>
            <strong class="danger-text" id="rpWaste">Rp 0</strong>
        </div>

        <div class="report-box">
            <span id="rpLabaLabel">Estimasi Laba</span>
            <strong id="rpLaba">Rp 0</strong>
        </div>

    </div>


    <!-- PERBANDINGAN STORE -->
    <div class="dashboard-card section-gap">

        <div class="card-header">
            <div>
                <h3>Perbandingan Store</h3>
                <p>Omzet, HPP, waste, dan estimasi laba tiap store</p>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="table-fixed">
                <colgroup>
                    <col style="width: 22%">
                    <col style="width: 11%">
                    <col style="width: 15%">
                    <col style="width: 15%">
                    <col style="width: 13%">
                    <col style="width: 16%">
                    <col style="width: 8%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Store</th>
                        <th style="text-align:right">Transaksi</th>
                        <th style="text-align:right">Omzet</th>
                        <th style="text-align:right">HPP</th>
                        <th style="text-align:right">Waste</th>
                        <th style="text-align:right">Laba</th>
                        <th style="text-align:right">Margin</th>
                    </tr>
                </thead>
                <tbody id="rpStores"></tbody>
            </table>
        </div>

    </div>


    <!-- LABA RUGI PER HARI -->
    <div class="dashboard-card section-gap">

        <div class="card-header">
            <div>
                <h3>Estimasi Laba Rugi per Hari</h3>
                <p>Omzet dikurangi HPP dan kerugian waste</p>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="table-fixed">
                <colgroup>
                    <col style="width: 18%">
                    <col style="width: 12%">
                    <col style="width: 16%">
                    <col style="width: 16%">
                    <col style="width: 14%">
                    <col style="width: 16%">
                    <col style="width: 8%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th style="text-align:right">Transaksi</th>
                        <th style="text-align:right">Omzet</th>
                        <th style="text-align:right">HPP</th>
                        <th style="text-align:right">Waste</th>
                        <th style="text-align:right">Laba</th>
                        <th style="text-align:right">Margin</th>
                    </tr>
                </thead>
                <tbody id="rpDaily"></tbody>
            </table>
        </div>

    </div>


    <!-- MUTASI STOK PER BAHAN -->
    <div class="dashboard-card section-gap">

        <div class="card-header">
            <div>
                <h3>Mutasi Stok per Bahan</h3>
                <p>Stok masuk dari DC, stok keluar karena penjualan, dan waste</p>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="table-fixed">
                <colgroup>
                    <col style="width: 24%">
                    <col style="width: 19%">
                    <col style="width: 19%">
                    <col style="width: 19%">
                    <col style="width: 19%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Bahan</th>
                        <th style="text-align:right">Stok Masuk</th>
                        <th style="text-align:right">Stok Keluar</th>
                        <th style="text-align:right">Waste</th>
                        <th style="text-align:right">Stok Saat Ini</th>
                    </tr>
                </thead>
                <tbody id="rpStock"></tbody>
            </table>
        </div>

    </div>


    <!-- PENJUALAN PER MENU -->
    <div class="dashboard-card section-gap">

        <div class="card-header">
            <div>
                <h3>Penjualan per Menu</h3>
                <p>Diurutkan dari yang paling banyak terjual</p>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="table-fixed">
                <colgroup>
                    <col style="width: 30%">
                    <col style="width: 14%">
                    <col style="width: 20%">
                    <col style="width: 20%">
                    <col style="width: 16%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Menu</th>
                        <th style="text-align:right">Terjual</th>
                        <th style="text-align:right">Omzet</th>
                        <th style="text-align:right">HPP</th>
                        <th style="text-align:right">Laba Kotor</th>
                    </tr>
                </thead>
                <tbody id="rpMenu"></tbody>
            </table>
        </div>

    </div>

</section>

<script>
(() => {

    // ---------- DATA (nanti dari backend) ----------
    const ITEMS  = @json(config('demo.items'));
    const MENUS  = @json(config('demo.menus'));
    const STORES = @json(config('demo.stores'));
    const STOCKS = @json(config('demo.stocks'));
    const SHIP   = @json(config('demo.shipments'));
    const TRX    = @json($trx);
    const WASTE  = @json($waste);

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const R      = 'style="text-align:right"';

    const $   = id => document.getElementById(id);
    const rp  = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    const nf  = n => Math.round(n).toLocaleString('id-ID');
    const pad = n => String(n).padStart(2, '0');
    const pct = n => n.toFixed(1).replace('.', ',') + '%';
    const sum = (list, fn) => list.reduce((s, x) => s + fn(x), 0);

    const fmtDate = d => {
        const [y, m, day] = d.split('-');
        return `${Number(day)} ${MONTHS[Number(m) - 1]} ${y}`;
    };

    // '11 Okt 2026' (format Surat Jalan) => '2026-10-11'
    const shipDate = str => {
        const [d, m, y] = str.split(' ');
        return `${y}-${pad(MONTHS.indexOf(m) + 1)}-${pad(d)}`;
    };

    // 3430 ml (isi 700) => "4 Botol 630 ml"
    const fmtStock = (key, amount) => {
        const i = ITEMS[key];
        if (i.size === 1) return `${nf(amount)} ${i.pack}`;
        const p = Math.floor(amount / i.size);
        const r = amount % i.size;
        return `${p} ${i.pack}` + (r ? ` ${nf(r)} ${i.unit}` : '');
    };

    // ---------- DATA TURUNAN ----------
    const STORE_IDS = Object.keys(STORES);
    const DONE      = TRX.filter(t => t.status === 'selesai');

    // stok masuk = Surat Jalan yang sudah dikonfirmasi IC (diterima x isi kemasan)
    const MASUK = SHIP.filter(s => s.status === 'diterima').flatMap(s =>
        s.items.map(i => ({
            store: s.store, date: shipDate(s.date), item: i.item,
            amount: i.received * ITEMS[i.item].size,
        })));

    const DATES = [...new Set([
        ...DONE.map(t => t.date), ...WASTE.map(w => w.date), ...MASUK.map(m => m.date),
    ])].sort().reverse();

    // hari yang punya penjualan / waste (untuk tabel laba rugi per hari)
    const SALE_DATES = [...new Set([...DONE.map(t => t.date), ...WASTE.map(w => w.date)])].sort().reverse();

    let fStore = 'semua';
    let fDate  = 'semua';

    // ---------- HITUNGAN ----------
    const recipeCost = id => Object.entries(MENUS[id].recipe)
        .reduce((c, [item, amt]) => c + amt * (ITEMS[item].price / ITEMS[item].size), 0);

    const trxTotal = t => t.items.reduce((s, i) => s + MENUS[i.menu].price * i.qty, 0);
    const trxHpp   = t => t.items.reduce((s, i) => s + i.qty * recipeCost(i.menu), 0);

    const wasteValue = w => w.lines.reduce((s, l) =>
        s + (Math.abs(l.amount) / ITEMS[l.item].size) * ITEMS[l.item].price, 0);

    const visibleStores = () => STORE_IDS.filter(id => fStore === 'semua' || id === fStore);
    const dateOk        = d => fDate === 'semua' || d === fDate;

    // hitung omzet, HPP, waste, laba untuk kondisi tertentu: pred(store, date)
    function calc(pred) {
        const tx    = DONE.filter(t => pred(t.store, t.date));
        const omzet = sum(tx, trxTotal);
        const hpp   = sum(tx, trxHpp);
        const waste = sum(WASTE.filter(w => pred(w.store, w.date)), wasteValue);
        const laba  = omzet - hpp - waste;

        return { count: tx.length, omzet, hpp, waste, laba, margin: omzet ? (laba / omzet) * 100 : 0 };
    }

    const inScope = (s, d) => (fStore === 'semua' || s === fStore) && dateOk(d);

    // ---------- RENDER ----------
    function renderAll() {
        const total = calc(inScope);

        // ---- ringkasan ----
        $('rpOmzet').textContent = rp(total.omzet);
        $('rpHpp').textContent   = rp(total.hpp);
        $('rpWaste').textContent = rp(total.waste);
        $('rpLaba').textContent  = rp(total.laba);
        $('rpLabaLabel').textContent = 'Estimasi Laba (margin ' + pct(total.margin) + ')';

        const cabang = STORES[STORE_IDS[0]].cabang;
        $('rpMeta').textContent = `Cabang ${cabang} · ${visibleStores().length} store · ${total.count} transaksi`;

        // ---- perbandingan store ----
        const stores = visibleStores().map(id => ({ id, ...calc((s, d) => s === id && dateOk(d)) }));

        $('rpStores').innerHTML = stores.map(r => `
            <tr>
                <td><strong>${STORES[r.id].name}</strong></td>
                <td ${R}>${r.count}</td>
                <td ${R}>${rp(r.omzet)}</td>
                <td ${R}>${rp(r.hpp)}</td>
                <td ${R} class="danger-text">${rp(r.waste)}</td>
                <td ${R}><strong>${rp(r.laba)}</strong></td>
                <td ${R}>${pct(r.margin)}</td>
            </tr>`).join('') + (stores.length > 1 ? `
            <tr>
                <td><strong>Total</strong></td>
                <td ${R}><strong>${total.count}</strong></td>
                <td ${R}><strong>${rp(total.omzet)}</strong></td>
                <td ${R}><strong>${rp(total.hpp)}</strong></td>
                <td ${R} class="danger-text"><strong>${rp(total.waste)}</strong></td>
                <td ${R}><strong>${rp(total.laba)}</strong></td>
                <td ${R}><strong>${pct(total.margin)}</strong></td>
            </tr>` : '');

        // ---- laba rugi per hari ----
        const days = SALE_DATES.filter(dateOk).map(date => ({
            date, ...calc((s, d) => d === date && (fStore === 'semua' || s === fStore)),
        }));

        $('rpDaily').innerHTML = days.length ? days.map(r => `
            <tr>
                <td class="col-nowrap"><strong>${fmtDate(r.date)}</strong></td>
                <td ${R}>${r.count}</td>
                <td ${R}>${rp(r.omzet)}</td>
                <td ${R}>${rp(r.hpp)}</td>
                <td ${R} class="danger-text">${rp(r.waste)}</td>
                <td ${R}><strong>${rp(r.laba)}</strong></td>
                <td ${R}>${pct(r.margin)}</td>
            </tr>`).join('')
            : '<tr><td colspan="7"><div class="page-empty">Belum ada penjualan</div></td></tr>';

        // ---- mutasi stok per bahan ----
        const mv = {};
        const bump = (key, field, v) => {
            mv[key] = mv[key] || { masuk: 0, keluar: 0, waste: 0 };
            mv[key][field] += v;
        };

        MASUK.filter(m => inScope(m.store, m.date)).forEach(m => bump(m.item, 'masuk', m.amount));

        DONE.filter(t => inScope(t.store, t.date)).forEach(t => {
            t.items.forEach(i => {
                Object.entries(MENUS[i.menu].recipe).forEach(([item, amt]) => bump(item, 'keluar', amt * i.qty));
            });
        });

        WASTE.filter(w => inScope(w.store, w.date)).forEach(w => {
            w.lines.forEach(l => bump(l.item, 'waste', Math.abs(l.amount)));
        });

        const keys = Object.keys(mv).sort((a, b) => ITEMS[a].name.localeCompare(ITEMS[b].name));
        const cell = (k, v) => v ? `${nf(v)} ${ITEMS[k].unit}` : '-';

        // stok saat ini = jumlah stok store yang dipilih (tidak ikut filter tanggal)
        const stockNow = k => sum(visibleStores(), id => STOCKS[id]?.[k] ?? 0);

        $('rpStock').innerHTML = keys.length ? keys.map(k => `
            <tr>
                <td><strong>${ITEMS[k].name}</strong></td>
                <td ${R}>${cell(k, mv[k].masuk)}</td>
                <td ${R}>${cell(k, mv[k].keluar)}</td>
                <td ${R} ${mv[k].waste ? 'class="danger-text"' : ''}>${cell(k, mv[k].waste)}</td>
                <td ${R}>${fmtStock(k, stockNow(k))}</td>
            </tr>`).join('')
            : '<tr><td colspan="5"><div class="page-empty">Belum ada mutasi stok</div></td></tr>';

        // ---- penjualan per menu ----
        const sold = {};

        DONE.filter(t => inScope(t.store, t.date)).forEach(t => {
            t.items.forEach(i => { sold[i.menu] = (sold[i.menu] || 0) + i.qty; });
        });

        const menuRows = Object.entries(sold).sort((a, b) => b[1] - a[1]);

        $('rpMenu').innerHTML = menuRows.length ? menuRows.map(([id, qty]) => {
            const omzet = MENUS[id].price * qty;
            const hpp   = recipeCost(id) * qty;

            return `
            <tr>
                <td><strong>${MENUS[id].name}</strong></td>
                <td ${R}>${qty} porsi</td>
                <td ${R}>${rp(omzet)}</td>
                <td ${R}>${rp(hpp)}</td>
                <td ${R}><strong>${rp(omzet - hpp)}</strong></td>
            </tr>`;
        }).join('')
            : '<tr><td colspan="5"><div class="page-empty">Belum ada penjualan</div></td></tr>';
    }

    // ---------- UNDUH CSV ----------
    function downloadCsv() {
        const lines = [['Tanggal', 'Store', 'Transaksi', 'Omzet', 'HPP', 'Waste', 'Laba'].join(',')];

        SALE_DATES.filter(dateOk).forEach(date => {
            visibleStores().forEach(id => {
                const r = calc((s, d) => s === id && d === date);

                if (r.count || r.waste) {
                    lines.push([date, '"' + STORES[id].name + '"', r.count,
                        Math.round(r.omzet), Math.round(r.hpp), Math.round(r.waste), Math.round(r.laba)].join(','));
                }
            });
        });

        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');

        link.href = URL.createObjectURL(blob);
        link.download = 'laporan-seluruh-cabang-' + (fStore === 'semua' ? 'semua-store' : fStore) + '-' +
            (fDate === 'semua' ? 'semua-tanggal' : fDate) + '.csv';
        link.click();

        URL.revokeObjectURL(link.href);
    }

    // ---------- EVENT ----------
    $('rpStore').innerHTML = '<option value="semua">Semua Store</option>' +
        STORE_IDS.map(id => `<option value="${id}">${STORES[id].name}</option>`).join('');

    $('rpDate').innerHTML =
        `<option value="semua">Semua Tanggal (${fmtDate(DATES[DATES.length - 1])} – ${fmtDate(DATES[0])})</option>` +
        DATES.map(d => `<option value="${d}">${fmtDate(d)}</option>`).join('');

    $('rpStore').addEventListener('change', e => { fStore = e.target.value; renderAll(); });
    $('rpDate').addEventListener('change',  e => { fDate  = e.target.value; renderAll(); });
    $('btnCsv').addEventListener('click', downloadCsv);

    renderAll();

})();
</script>

@endsection