import Chart from 'chart.js/auto';

/* =========================================================
   HELPER
========================================================= */

function formatRupiah(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

const font = (size) => ({ family: 'Poppins', size });

const axisTicks = { color: '#aa917e', font: font(8) };

const gridColor = 'rgba(120, 90, 60, 0.08)';

function lineDataset(label, data, color, background) {
    return {
        label,
        data,
        borderColor: color,
        backgroundColor: background,
        borderWidth: 2,
        tension: 0.35,
        fill: true,
        pointRadius: 3,
        pointHoverRadius: 5,
        pointBackgroundColor: color,
        pointBorderColor: '#ffffff',
        pointBorderWidth: 1,
    };
}

const $ = (id) => document.getElementById(id);

// Rp 4,82 jt  /  Rp 465 rb
function formatSingkat(value) {
    if (value >= 1000000) {
        return 'Rp ' + (value / 1000000).toFixed(2).replace('.', ',') + ' jt';
    }

    return 'Rp ' + Math.round(value / 1000).toLocaleString('id-ID') + ' rb';
}


/* =========================================================
   DATA DEMO (dikirim dari blade, nanti dari backend)
========================================================= */

const DATA = window.KOPSYNC ?? {};

const ITEMS  = DATA.items ?? {};
const MENUS  = DATA.menus ?? {};
const TRX    = DATA.transactions ?? [];
const MUT    = DATA.mutations ?? [];
const SHIP   = DATA.shipments ?? [];

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const DAYS   = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const pad = (n) => String(n).padStart(2, '0');

const parseDate = (s) => {
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d);
};

const toKey = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

const addDays = (d, n) => {
    const copy = new Date(d);
    copy.setDate(copy.getDate() + n);
    return copy;
};

const fmtDate = (s) => {
    const d = parseDate(s);
    return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
};

const toMinutes = (time) => {
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
};

// "Hari ini" = tanggal transaksi terakhir; fallback: summary dari backend / hari ini
const DONE        = TRX.filter((t) => t.status === 'selesai');
const LATEST_DATE = TRX.map((t) => t.date).sort().pop() ?? DATA.summary?.date ?? toKey(new Date());
const PREV_DATE   = LATEST_DATE ? toKey(addDays(parseDate(LATEST_DATE), -1)) : null;

const todayDone = DONE.filter((t) => t.date === LATEST_DATE);
const LAST_TIME = todayDone.reduce((max, t) => Math.max(max, toMinutes(t.time)), 0);

const trxTotal = (t) => t.items.reduce((s, i) => s + MENUS[i.menu].price * i.qty, 0);

const recipeCost = (menuId) => Object.entries(MENUS[menuId].recipe)
    .reduce((c, [item, amt]) => c + amt * (ITEMS[item].price / ITEMS[item].size), 0);

const trxHpp = (t) => t.items.reduce((s, i) => s + i.qty * recipeCost(i.menu), 0);

const sum = (list, fn) => list.reduce((s, x) => s + fn(x), 0);

// nilai rupiah dari mutasi waste pada satu tanggal
function wasteOnDate(date) {
    const records = MUT.filter((m) => m.type === 'waste' && m.date === date);

    const value = sum(records, (m) => sum(m.lines, (l) =>
        (Math.abs(l.amount) / ITEMS[l.item].size) * ITEMS[l.item].price));

    return { count: records.length, value };
}

// stok di bawah / sama dengan batas minimum
const lowItems = () => Object.entries(ITEMS)
    .filter(([, i]) => i.stock <= i.min)
    .map(([key, i]) => ({ key, ...i }));


/* =========================================================
   STAT CARDS, PROFIT, ALERT
========================================================= */

function renderSummary() {
    if (!LATEST_DATE) {
        return;
    }

    // Ringkasan dari backend (ReportService) lebih akurat; fallback hitung lokal.
    const s = DATA.summary ?? null;

    // ---- omzet, HPP, waste hari ini ----
    const omzet = s ? s.omzet : sum(todayDone, trxTotal);
    const hpp   = s ? s.hpp : sum(todayDone, trxHpp);
    const waste = s ? s.waste : wasteOnDate(LATEST_DATE).value;
    const wasteCount = s ? s.waste_count : wasteOnDate(LATEST_DATE).count;
    const laba  = s ? s.laba : omzet - hpp - waste;

    const delta = $('statOmzetDelta');

    if (s && s.delta_pct !== null && s.delta_pct !== undefined) {
        const pct = s.delta_pct;
        delta.textContent = (pct >= 0 ? '+' : '') + pct.toFixed(1).replace('.', ',') + '% dari kemarin';
        delta.className = pct >= 0 ? 'positive' : 'danger-text';
    } else {
        // dibanding kemarin pada jam yang sama (supaya adil, hari ini belum selesai)
        const prevOmzet = sum(
            DONE.filter((t) => t.date === PREV_DATE && toMinutes(t.time) <= LAST_TIME),
            trxTotal
        );

        if (prevOmzet > 0) {
            const pct = ((omzet - prevOmzet) / prevOmzet) * 100;

            delta.textContent = (pct >= 0 ? '+' : '') + pct.toFixed(1).replace('.', ',') + '% dari kemarin (jam sama)';
            delta.className = pct >= 0 ? 'positive' : 'danger-text';
        } else {
            delta.textContent = 'Belum ada pembanding kemarin';
            delta.className = '';
        }
    }

    $('statOmzet').textContent = formatSingkat(omzet);
    $('statWaste').textContent = formatSingkat(waste);
    $('statWasteSub').textContent = wasteCount + ' laporan hari ini';

    if ($('statSalesCount')) {
        $('statSalesCount').textContent = todayDone.length + ' Transaksi';
    }

    // ---- stok menipis ----
    const low   = lowItems();
    const habis = low.filter((i) => i.stock <= 0).length;

    $('statLow').textContent = low.length + ' Item';
    $('statLowSub').textContent = habis > 0 ? habis + ' item habis' : 'Belum ada yang habis';

    // Hitung score gerai (100 - penalti stok menipis & waste)
    if ($('storeScore')) {
        const penalty = (low.length * 4) + (wasteCount * 3);
        const score = Math.max(75, 100 - penalty);
        $('storeScore').textContent = score;
        if ($('storeScoreDesc')) {
            $('storeScoreDesc').textContent = low.length > 0
                ? `${low.length} bahan perlu perhatian`
                : 'Operasional gerai prima & terjaga';
        }
    }

    // ---- pesanan DC (surat jalan yang belum diterima IC) ----
    const pending = SHIP.filter((s) => s.status !== 'diterima');
    const sent    = pending.filter((s) => s.status === 'dikirim').length;

    $('statDC').textContent = pending.length + ' Pesanan';
    $('statDCSub').textContent = sent + ' sedang dikirim';

    // ---- laba rugi ----
    $('profitOmzet').textContent = formatRupiah(omzet);
    $('profitHpp').textContent   = formatRupiah(hpp);
    $('profitWaste').textContent = formatRupiah(waste);
    $('profitLaba').textContent  = formatRupiah(laba);

    const label = $('dataDate');

    label.textContent = (label.dataset.store ? label.dataset.store + ' · ' : '') + 'Data per ' + fmtDate(s ? s.date : LATEST_DATE);
}

function renderAlerts() {
    const alerts = [];
    const low    = lowItems();

    // 1. waste terbaru
    const lastWaste = MUT
        .filter((m) => m.type === 'waste')
        .sort((a, b) => (b.date + b.time).localeCompare(a.date + a.time))[0];

    if (lastWaste) {
        const reason = lastWaste.ref.replace('Waste · ', '');
        const lines  = lastWaste.lines.slice(0, 2)
            .map((l) => `${ITEMS[l.item].name} ${Math.abs(l.amount).toLocaleString('id-ID')} ${ITEMS[l.item].unit}`)
            .join(', ');

        alerts.push({ color: 'red', title: 'Waste — ' + reason, text: lines });
    }

    // 2. stok habis
    low.filter((i) => i.stock <= 0).forEach((i) => {
        alerts.push({ color: 'red', title: i.name, text: 'Stok habis' });
    });

    // 3. draft restock otomatis (kebutuhan = stok minimum - stok aktual)
    if (low.length) {
        alerts.push({
            color: 'brown',
            title: 'Draft Restock ' + (DATA.draftNo ?? '#RS-024'),
            text: low.length + ' item menunggu approval Manager',
        });
    }

    // 4. pengiriman dari DC yang belum diterima
    SHIP.filter((s) => s.status !== 'diterima').forEach((s) => {
        alerts.push({
            color: 'green',
            title: 'Pengiriman ' + s.no,
            text: s.status === 'dikirim' ? 'Dalam perjalanan' : 'Menunggu konfirmasi IC',
        });
    });

    // 5. stok di bawah minimum (yang paling kritis dulu)
    low.filter((i) => i.stock > 0)
        .sort((a, b) => a.stock / a.min - b.stock / b.min)
        .forEach((i) => {
            alerts.push({ color: 'orange', title: i.name, text: 'Stok di bawah minimum' });
        });

    $('alertCount').textContent = alerts.length;

    const actionUrl = (a) => {
        if (a.title.includes('Restock') || a.title.includes('Stok')) return '/manager/restock';
        if (a.title.includes('Waste')) return '/manager/report';
        return '/manager/alert';
    };

    $('alertList').innerHTML = alerts.slice(0, 3).map((a) => {
        const badgeClass = a.color === 'red' ? 'danger' : a.color === 'orange' ? 'warning' : 'info';
        const iconChar = a.color === 'red' ? '!' : a.color === 'orange' ? '▲' : '✓';
        const url = actionUrl(a);

        return `
        <div class="dash-alert-item">
            <div class="dash-alert-left">
                <div class="dash-alert-badge-icon ${badgeClass}">${iconChar}</div>
                <div class="dash-alert-info">
                    <strong class="dash-alert-title" title="${a.title}">${a.title}</strong>
                    <span class="dash-alert-sub" title="${a.text}">${a.text}</span>
                </div>
            </div>
            <a href="${url}" class="dash-alert-action-btn">Solve</a>
        </div>`;
    }).join('') || '<div style="text-align:center; padding:16px; font-size:12px; color:var(--pos-muted);">Semua kondisi stok dan operasional aman.</div>';
}


/* =========================================================
   STOCK CHART
========================================================= */

const stockCanvas = $('stockChart');

let stockChart = null;

// satuan grafik = kemasan (jumlah satuan terkecil dibagi isi kemasan)
function buildStockData(period) {
    const days   = Number(period);
    const bucket = days === 7 ? 1 : 2;
    const count  = days / bucket;
    const end    = parseDate(LATEST_DATE);

    const labels = [];
    const masuk  = [];
    const keluar = [];
    const waste  = [];

    for (let b = 0; b < count; b++) {
        let m = 0;
        let k = 0;
        let w = 0;
        let lastDay = null;

        for (let j = 0; j < bucket; j++) {
            const offset = days - 1 - (b * bucket + j);
            const day    = addDays(end, -offset);

            lastDay = day;

            MUT.filter((x) => x.date === toKey(day)).forEach((x) => {
                x.lines.forEach((l) => {
                    const pack = Math.abs(l.amount) / ITEMS[l.item].size;

                    if (x.type === 'masuk') m += pack;
                    if (x.type === 'keluar') k += pack;
                    if (x.type === 'waste') w += pack;
                });
            });
        }

        labels.push(bucket === 1 ? `${DAYS[lastDay.getDay()]} ${lastDay.getDate()}` : String(lastDay.getDate()));

        masuk.push(Math.round(m * 10) / 10);
        keluar.push(Math.round(k * 10) / 10);
        waste.push(Math.round(w * 10) / 10);
    }

    return { labels, masuk, keluar, waste };
}

function createStockChart(period = '7') {
    if (!stockCanvas || !LATEST_DATE) {
        return;
    }

    const data = buildStockData(period);

    if (stockChart) {
        stockChart.destroy();
    }

    stockChart = new Chart(stockCanvas, {
        type: 'line',

        data: {
            labels: data.labels,
            datasets: [
                lineDataset('Stok Masuk', data.masuk, '#8f4713', 'rgba(143, 71, 19, 0.08)'),
                lineDataset('Stok Keluar', data.keluar, '#d99a4e', 'rgba(217, 154, 78, 0.05)'),
                lineDataset('Waste', data.waste, '#c94c4c', 'rgba(201, 76, 76, 0.04)'),
            ],
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                mode: 'index',
                intersect: false,
            },

            plugins: {
                legend: { display: false },

                tooltip: {
                    backgroundColor: '#5d2d0d',
                    titleFont: font(10),
                    bodyFont: font(9),
                    padding: 10,
                    cornerRadius: 8,
                },
            },

            scales: {
                x: {
                    grid: { display: false },
                    ticks: axisTicks,
                },

                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: axisTicks,
                    title: {
                        display: true,
                        text: 'Jumlah (kemasan)',
                        color: '#aa917e',
                        font: font(8),
                    },
                },
            },
        },
    });
}

const stockPeriod = $('stockPeriod');

if (stockPeriod) {
    stockPeriod.addEventListener('change', (event) => {
        createStockChart(event.target.value);
    });
}


/* =========================================================
   OMZET CHART
========================================================= */

const omzetCanvas = $('omzetChart');

let omzetChart = null;

// Harian dihitung dari transaksi demo (kumulatif per 2 jam, sampai jam transaksi terakhir).
// Bulanan & tahunan BELUM ada datanya, jadi masih angka dummy (total per minggu / per bulan).
const omzetDummy = {
    monthly: {
        labels: ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'],
        values: [4200000, 4900000, 5400000, 5900000],
    },
    yearly: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        values: [
            17500000, 18200000, 19400000, 20100000, 21000000, 21800000,
            22600000, 23000000, 23900000, 24500000, null, null,
        ],
    },
};

function buildOmzetData(period) {
    if (period !== 'daily') {
        const dummy = omzetDummy[period];
        const labels = dummy.labels;
        const coffee = dummy.values.map((v) => (v === null ? null : Math.round(v * 0.55)));
        const tea    = dummy.values.map((v) => (v === null ? null : Math.round(v * 0.28)));
        const snack  = dummy.values.map((v) => (v === null ? null : Math.round(v * 0.17)));

        return { labels, coffee, tea, snack };
    }

    const slots = [8, 10, 12, 14, 16, 18, 20, 22];
    const used  = [];

    for (const hour of slots) {
        used.push(hour);

        if (hour * 60 >= LAST_TIME) {
            break;
        }
    }

    const calcCat = (hour, catType) => {
        return Math.round(sum(
            todayDone.filter((t) => toMinutes(t.time) <= hour * 60),
            (t) => t.items.reduce((acc, i) => {
                const m = MENUS[i.menu];
                if (!m) return acc;
                const c = (m.category || '').toLowerCase();
                if (catType === 'coffee' && c.includes('coffee') && !c.includes('non')) return acc + m.price * i.qty;
                if (catType === 'tea' && (c.includes('tea') || c.includes('non') || c.includes('beverage'))) return acc + m.price * i.qty;
                if (catType === 'snack' && (c.includes('snack') || c.includes('makan') || c.includes('food'))) return acc + m.price * i.qty;
                return acc;
            }, 0)
        ));
    };

    return {
        labels: used.map((h) => pad(h) + '.00'),
        coffee: used.map((h) => calcCat(h, 'coffee')),
        tea: used.map((h) => calcCat(h, 'tea')),
        snack: used.map((h) => calcCat(h, 'snack')),
    };
}

function createOmzetChart(period = 'daily') {
    if (!omzetCanvas || !LATEST_DATE) {
        return;
    }

    const data = buildOmzetData(period);

    if (omzetChart) {
        omzetChart.destroy();
    }

    omzetChart = new Chart(omzetCanvas, {
        type: 'line',

        data: {
            labels: data.labels,
            datasets: [
                lineDataset('Coffee', data.coffee, '#532508', 'rgba(83, 37, 8, 0.08)'),
                lineDataset('Non-Coffee / Tea', data.tea, '#d99a4e', 'rgba(217, 154, 78, 0.06)'),
                lineDataset('Snack / Makanan', data.snack, '#758f3c', 'rgba(117, 143, 60, 0.06)'),
            ],
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                mode: 'index',
                intersect: false,
            },

            plugins: {
                legend: { display: false },

                tooltip: {
                    backgroundColor: '#372314',
                    titleFont: font(10),
                    bodyFont: font(9),
                    padding: 10,
                    cornerRadius: 8,

                    callbacks: {
                        label: (context) => ` ${context.dataset.label}: ${formatRupiah(context.raw || 0)}`,
                    },
                },
            },

            scales: {
                x: {
                    grid: { display: false },
                    ticks: axisTicks,
                },

                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },

                    ticks: {
                        ...axisTicks,

                        callback: (value) => {
                            if (value >= 1000000) {
                                return 'Rp ' + (value / 1000000).toFixed(1) + ' jt';
                            }

                            return 'Rp ' + (value / 1000).toFixed(0) + ' rb';
                        },
                    },
                },
            },
        },
    });
}


/* =========================================================
   OMZET PERIOD BUTTON
========================================================= */

const periodButtons = document.querySelectorAll('.period-btn');

periodButtons.forEach((button) => {
    button.addEventListener('click', () => {
        periodButtons.forEach((btn) => btn.classList.remove('active'));

        button.classList.add('active');

        createOmzetChart(button.dataset.period);
    });
});


/* =========================================================
   RADAR CHART (ITEMS PERFORMANCE)
========================================================= */

let radarChart = null;

function createRadarChart() {
    const canvas = $('radarChart');
    if (!canvas) return;

    const menuCounts = {};
    Object.keys(MENUS).forEach((id) => menuCounts[id] = 0);

    TRX.forEach((t) => {
        if (t.status === 'selesai') {
            t.items.forEach((i) => {
                menuCounts[i.menu] = (menuCounts[i.menu] || 0) + i.qty;
            });
        }
    });

    const sortedMenuIds = Object.keys(MENUS).sort((a, b) => menuCounts[b] - menuCounts[a]).slice(0, 6);
    const labels = sortedMenuIds.map((id) => MENUS[id]?.name || 'Menu #' + id);
    const values = sortedMenuIds.map((id) => Math.max(menuCounts[id] || 0, 3));

    if (radarChart) radarChart.destroy();

    radarChart = new Chart(canvas, {
        type: 'radar',
        data: {
            labels,
            datasets: [{
                label: 'Kuantitas Terjual',
                data: values,
                borderColor: '#7c3a12',
                backgroundColor: 'rgba(124, 58, 18, 0.18)',
                pointBackgroundColor: '#532508',
                pointBorderColor: '#ffffff',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#372314',
                    titleFont: font(10),
                    bodyFont: font(9),
                    padding: 8,
                    cornerRadius: 8,
                },
            },
            scales: {
                r: {
                    angleLines: { color: 'rgba(120, 90, 60, 0.15)' },
                    grid: { color: 'rgba(120, 90, 60, 0.1)' },
                    pointLabels: {
                        font: font(9),
                        color: '#6e5544',
                    },
                    ticks: { display: false },
                },
            },
        },
    });
}


/* =========================================================
   RECENT TRANSACTIONS TABLE
========================================================= */

function renderRecentTransactions(catFilter = 'All') {
    const tbody = $('recentTrxBody');
    if (!tbody) return;

    let filtered = TRX.slice(0, 15);

    if (catFilter !== 'All') {
        filtered = filtered.filter((t) => t.items.some((i) => {
            const m = MENUS[i.menu];
            if (!m) return false;
            const c = (m.category || '').toLowerCase();
            if (catFilter === 'Coffee') return c.includes('coffee') && !c.includes('non');
            if (catFilter === 'Tea') return c.includes('tea') || c.includes('non');
            if (catFilter === 'Snack') return c.includes('snack') || c.includes('makan');
            return true;
        }));
    }

    if (!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--pos-muted);">Belum ada transaksi di kategori ini.</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.slice(0, 5).map((t) => {
        const total = trxTotal(t);
        const itemNames = t.items.map((i) => MENUS[i.menu]?.name || 'Item').join(', ');
        const cashierName = t.cashier || 'Kasir';
        const initial = cashierName.charAt(0).toUpperCase();

        return `
        <tr>
            <td><input type="checkbox" aria-label="Pilih ${t.no}"></td>
            <td>
                <div class="dash-customer-cell">
                    <div class="dash-customer-avatar">${initial}</div>
                    <div class="dash-customer-info">
                        <span class="dash-customer-name">${t.no}</span>
                        <span class="dash-customer-meta">Kasir: ${cashierName}</span>
                    </div>
                </div>
            </td>
            <td>
                <span style="font-weight:600;">${t.time}</span>
                <span style="display:block; font-size:10px; color:var(--pos-muted);">${t.date}</span>
            </td>
            <td>
                <span style="display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; max-width:220px;" title="${itemNames}">${itemNames}</span>
            </td>
            <td style="text-align: right;">
                <span class="dash-trx-value">${formatRupiah(total)}</span>
            </td>
            <td style="text-align: right; color:var(--pos-muted); cursor:pointer;">
                <span>⋮</span>
            </td>
        </tr>`;
    }).join('');
}

const trxFilterTabs = document.querySelectorAll('#trxFilterTabs button');
trxFilterTabs.forEach((btn) => {
    btn.addEventListener('click', () => {
        trxFilterTabs.forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        renderRecentTransactions(btn.dataset.trxCat);
    });
});


/* =========================================================
   INIT
========================================================= */

renderSummary();
renderAlerts();
createStockChart('7');
createOmzetChart('daily');
createRadarChart();
renderRecentTransactions('All');