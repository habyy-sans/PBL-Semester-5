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


/* =========================================================
   STOCK CHART
========================================================= */

const stockCanvas = document.getElementById('stockChart');

let stockChart = null;

const stockData = {
    '7': {
        labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
        masuk: [38, 45, 32, 50, 42, 55, 48],
        keluar: [28, 34, 27, 39, 35, 44, 40],
    },
    '30': {
        labels: ['1', '3', '5', '7', '9', '11', '13', '15', '17', '19', '21', '23', '25', '27', '29'],
        masuk: [35, 40, 32, 48, 44, 51, 46, 55, 49, 58, 52, 60, 56, 63, 59],
        keluar: [25, 30, 28, 36, 34, 40, 37, 43, 39, 46, 43, 48, 44, 50, 47],
    },
};

function createStockChart(period = '7') {
    if (!stockCanvas) {
        return;
    }

    const data = stockData[period] ?? stockData['7'];

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
                        text: 'Jumlah',
                        color: '#aa917e',
                        font: font(8),
                    },
                },
            },
        },
    });
}

createStockChart('7');

const stockPeriod = document.getElementById('stockPeriod');

if (stockPeriod) {
    stockPeriod.addEventListener('change', (event) => {
        createStockChart(event.target.value);
    });
}


/* =========================================================
   OMZET CHART
========================================================= */

const omzetCanvas = document.getElementById('omzetChart');

let omzetChart = null;

const omzetData = {
    daily: {
        labels: ['08.00', '10.00', '12.00', '14.00', '16.00', '18.00', '20.00', '22.00'],
        values: [450000, 720000, 1100000, 1450000, 1900000, 2700000, 3800000, 4820000],
    },
    monthly: {
        labels: ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'],
        values: [8200000, 10800000, 13200000, 15800000],
    },
    yearly: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        values: [
            28000000, 31000000, 34000000, 36000000, 39000000, 42000000,
            45000000, 47000000, 49000000, 51000000, 54000000, 56000000,
        ],
    },
};

function createOmzetChart(period = 'daily') {
    if (!omzetCanvas) {
        return;
    }

    const data = omzetData[period] ?? omzetData.daily;

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

createOmzetChart('daily');


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