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

    // ---- stok menipis ----
    const low   = lowItems();
    const habis = low.filter((i) => i.stock <= 0).length;

    $('statLow').textContent = low.length + ' Item';
    $('statLowSub').textContent = habis > 0 ? habis + ' item habis' : 'Belum ada yang habis';

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

    $('alertList').innerHTML = alerts.slice(0, 6).map((a) => `
        <div class="alert-item">
            <span class="alert-dot ${a.color}"></span>
            <div>
                <strong>${a.title}</strong>
                <p>${a.text}</p>
            </div>
        </div>`).join('');
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

        return {
            labels: dummy.labels,
            values: dummy.values.map((v) => (v === null ? null : Math.round(v))),
        };
    }

    const slots = [8, 10, 12, 14, 16, 18, 20, 22];
    const used  = [];

    for (const hour of slots) {
        used.push(hour);

        if (hour * 60 >= LAST_TIME) {
            break;
        }
    }

    return {
        labels: used.map((h) => pad(h) + '.00'),
        values: used.map((h) => Math.round(
            sum(todayDone.filter((t) => toMinutes(t.time) <= h * 60), trxTotal)
        )),
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
                lineDataset('Omzet', data.values, '#8f4713', 'rgba(143, 71, 19, 0.10)'),
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

                    callbacks: {
                        label: (context) => ' Omzet: ' + formatRupiah(context.raw),
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
   INIT
========================================================= */

renderSummary();
renderAlerts();
createStockChart('7');
createOmzetChart('daily');