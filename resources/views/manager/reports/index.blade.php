@extends('layouts.app')

@section('title', 'Laporan Cabang')
@section('description', 'Laporan mutasi stok dan estimasi laba rugi.')

@section('content')

@php
    $branchKey  = config('demo_manager.branch');
    $branchName = config("demo.branches.$branchKey.name", 'Cabang');
    $storeName  = config("demo.branches.$branchKey.stores." . config('demo_manager.store'), 'Store');
@endphp

<section class="page active">

    <div class="table-toolbar">
        <select id="rpDate" class="select"></select>
        <span class="table-meta" id="rpMeta" data-store="{{ $storeName }} · {{ $branchName }}"></span>
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


    <!-- LABA RUGI PER HARI -->
    <div class="dashboard-card section-gap">

        <div class="card-header">
            <div>
                <h3>Estimasi Laba Rugi</h3>
                <p>Omzet dikurangi HPP dan kerugian waste, per hari</p>
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
    const ITEMS = @json(config('demo.items'));
    const MENUS = @json(config('demo.menus'));
    const MUT   = @json(config('demo.mutations'));
    const TRX   = @json(config('demo_transactions.list'));

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const R      = 'style="text-align:right"';

    const $  = id => document.getElementById(id);
    const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    const nf = n => Math.round(n).toLocaleString('id-ID');

    const fmtDate = d => {
        const [y, m, day] = d.split('-');
        return `${Number(day)} ${MONTHS[Number(m) - 1]} ${y}`;
    };

    // 3430 ml (isi 700) => "4 Botol 630 ml"
    const fmtStock = (key, amount) => {
        const i = ITEMS[key];
        if (i.size === 1) return `${nf(amount)} ${i.pack}`;
        const p = Math.floor(amount / i.size);
        const r = amount % i.size;
        return `${p} ${i.pack}` + (r ? ` ${nf(r)} ${i.unit}` : '');
    };

    const DONE  = TRX.filter(t => t.status === 'selesai');
    const DATES = [...new Set([...DONE.map(t => t.date), ...MUT.map(m => m.date)])].sort().reverse();

    let fDate = 'semua';

    const inRange = d => fDate === 'semua' || d === fDate;

    // ---------- HITUNGAN ----------
    const recipeCost = id => Object.entries(MENUS[id].recipe)
        .reduce((c, [item, amt]) => c + amt * (ITEMS[item].price / ITEMS[item].size), 0);

    const trxTotal = t => t.items.reduce((s, i) => s + MENUS[i.menu].price * i.qty, 0);
    const trxHpp   = t => t.items.reduce((s, i) => s + i.qty * recipeCost(i.menu), 0);

    const lineValue = l => (Math.abs(l.amount) / ITEMS[l.item].size) * ITEMS[l.item].price;

    const wasteOn = date => MUT
        .filter(m => m.type === 'waste' && m.date === date)
        .reduce((s, m) => s + m.lines.reduce((a, l) => a + lineValue(l), 0), 0);

    // satu baris laba rugi untuk satu tanggal
    function dayRow(date) {
        const list  = DONE.filter(t => t.date === date);
        const omzet = list.reduce((s, t) => s + trxTotal(t), 0);
        const hpp   = list.reduce((s, t) => s + trxHpp(t), 0);
        const waste = wasteOn(date);
        const laba  = omzet - hpp - waste;

        return { date, count: list.length, omzet, hpp, waste, laba, margin: omzet ? (laba / omzet) * 100 : 0 };
    }

    const pct = n => n.toFixed(1).replace('.', ',') + '%';

    // ---------- RENDER ----------
    function renderAll() {
        const dates = DATES.filter(inRange);
        const rows  = dates.map(dayRow);

        const total = rows.reduce((a, r) => ({
            count: a.count + r.count, omzet: a.omzet + r.omzet, hpp: a.hpp + r.hpp,
            waste: a.waste + r.waste, laba: a.laba + r.laba,
        }), { count: 0, omzet: 0, hpp: 0, waste: 0, laba: 0 });

        const margin = total.omzet ? (total.laba / total.omzet) * 100 : 0;

        // ---- ringkasan ----
        $('rpOmzet').textContent = rp(total.omzet);
        $('rpHpp').textContent   = rp(total.hpp);
        $('rpWaste').textContent = rp(total.waste);
        $('rpLaba').textContent  = rp(total.laba);
        $('rpLabaLabel').textContent = 'Estimasi Laba (margin ' + pct(margin) + ')';

        const label = $('rpMeta');
        label.textContent = label.dataset.store + ' · ' + total.count + ' transaksi';

        // ---- laba rugi per hari ----
        $('rpDaily').innerHTML = rows.map(r => `
            <tr>
                <td class="col-nowrap"><strong>${fmtDate(r.date)}</strong></td>
                <td ${R}>${r.count}</td>
                <td ${R}>${rp(r.omzet)}</td>
                <td ${R}>${rp(r.hpp)}</td>
                <td ${R} class="danger-text">${rp(r.waste)}</td>
                <td ${R}><strong>${rp(r.laba)}</strong></td>
                <td ${R}>${pct(r.margin)}</td>
            </tr>`).join('') + (rows.length > 1 ? `
            <tr>
                <td><strong>Total</strong></td>
                <td ${R}><strong>${total.count}</strong></td>
                <td ${R}><strong>${rp(total.omzet)}</strong></td>
                <td ${R}><strong>${rp(total.hpp)}</strong></td>
                <td ${R} class="danger-text"><strong>${rp(total.waste)}</strong></td>
                <td ${R}><strong>${rp(total.laba)}</strong></td>
                <td ${R}><strong>${pct(margin)}</strong></td>
            </tr>` : '');

        // ---- mutasi stok per bahan ----
        const mv = {};

        MUT.filter(m => inRange(m.date)).forEach(m => {
            m.lines.forEach(l => {
                mv[l.item] = mv[l.item] || { masuk: 0, keluar: 0, waste: 0 };
                if (m.type === 'masuk')  mv[l.item].masuk  += Math.abs(l.amount);
                if (m.type === 'keluar') mv[l.item].keluar += Math.abs(l.amount);
                if (m.type === 'waste')  mv[l.item].waste  += Math.abs(l.amount);
            });
        });

        const keys = Object.keys(mv).sort((a, b) => ITEMS[a].name.localeCompare(ITEMS[b].name));

        const cell = (key, v) => v ? `${nf(v)} ${ITEMS[key].unit}` : '-';

        $('rpStock').innerHTML = keys.length ? keys.map(k => `
            <tr>
                <td><strong>${ITEMS[k].name}</strong></td>
                <td ${R}>${cell(k, mv[k].masuk)}</td>
                <td ${R}>${cell(k, mv[k].keluar)}</td>
                <td ${R} ${mv[k].waste ? 'class="danger-text"' : ''}>${cell(k, mv[k].waste)}</td>
                <td ${R}>${fmtStock(k, ITEMS[k].stock)}</td>
            </tr>`).join('')
            : '<tr><td colspan="5"><div class="page-empty">Belum ada mutasi stok</div></td></tr>';

        // ---- penjualan per menu ----
        const sold = {};

        DONE.filter(t => inRange(t.date)).forEach(t => {
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
        const rows = DATES.filter(inRange).map(dayRow);

        const lines = [
            ['Tanggal', 'Transaksi', 'Omzet', 'HPP', 'Waste', 'Laba'].join(','),
            ...rows.map(r => [r.date, r.count, Math.round(r.omzet), Math.round(r.hpp), Math.round(r.waste), Math.round(r.laba)].join(',')),
        ];

        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');

        link.href = URL.createObjectURL(blob);
        link.download = 'laporan-cabang-' + (fDate === 'semua' ? 'semua-tanggal' : fDate) + '.csv';
        link.click();

        URL.revokeObjectURL(link.href);
    }

    // ---------- EVENT ----------
    $('rpDate').innerHTML =
        `<option value="semua">Semua Tanggal (${fmtDate(DATES[DATES.length - 1])} – ${fmtDate(DATES[0])})</option>` +
        DATES.map(d => `<option value="${d}">${fmtDate(d)}</option>`).join('');

    $('rpDate').addEventListener('change', e => { fDate = e.target.value; renderAll(); });
    $('btnCsv').addEventListener('click', downloadCsv);

    renderAll();

})();
</script>

@endsection