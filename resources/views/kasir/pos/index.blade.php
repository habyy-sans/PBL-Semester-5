@extends('layouts.app')

@section('title', 'Kasir POS')
@section('description', 'Kelola transaksi penjualan dan pencatatan kasir.')

@section('content')

@php
    $user = auth()->user();
    $cashierName = $user->nama_lengkap ?? 'Kasir';
    $cashierInitial = strtoupper(mb_substr($cashierName, 0, 1));
    $todayDateStr = \Carbon\Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y');
@endphp

<div class="pos-wrapper">

    <!-- TOP BAR (Sesuai Template Image 1) -->
    <header class="pos-topbar">
        <div class="pos-brand-section">
            <div class="pos-brand-logo">
                <div class="pos-brand-logo-icon">☕</div>
                <div class="pos-brand-text">
                    <h1>KOPSYNC</h1>
                    <span>{{ $branchName ?? 'Gerai Pusat' }}</span>
                </div>
            </div>
            <div class="pos-topbar-divider"></div>
            <div class="pos-date-badge">{{ $todayDateStr }}</div>
        </div>

        <div class="pos-topbar-actions">
            <div class="pos-stat-pill">
                <span>Total :</span>
                <b>{{ $todayOrdersCount ?? 0 }} Pesanan Hari Ini</b>
            </div>

            <a href="{{ route('kasir.history') }}" class="pos-report-link" title="Lihat Riwayat & Laporan">
                <span>Riwayat</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </a>

            <div class="pos-user-profile">
                <div class="pos-user-avatar">{{ $cashierInitial }}</div>
                <div class="pos-user-meta">
                    <strong>{{ $cashierName }}</strong>
                    <span>Kasir</span>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN TWO-COLUMN LAYOUT -->
    <div class="pos-layout-modern">

        <!-- LEFT COLUMN: SEARCH + CATEGORIES + PRODUCTS -->
        <section class="pos-catalog-column">

            <!-- SEARCH BAR WITH SHORTCUT -->
            <div class="pos-search-bar">
                <span class="pos-search-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <input
                    type="text"
                    id="posSearch"
                    class="pos-search-input"
                    placeholder="Cari kopi, minuman, atau menu makanan..."
                    autocomplete="off"
                >
                <span class="pos-search-shortcut">⌘ K</span>
            </div>

            <!-- CATEGORY CARDS (Sesuai Template Image 1) -->
            <div class="pos-category-row" id="posCategoryCards">
                <!-- Rendered dynamically via JS -->
            </div>

            <!-- PRODUCTS GRID -->
            <div class="pos-products-grid" id="productGrid">
                <!-- Rendered dynamically via JS -->
            </div>

        </section>

        <!-- RIGHT COLUMN: PURCHASE RECEIPT / CART PANEL (Sesuai Template Image 1) -->
        <aside class="pos-receipt-panel">

            <!-- RECEIPT HEADER -->
            <div class="pos-receipt-header">
                <button type="button" class="pos-receipt-back-btn" id="btnResetTrx" title="Reset / Bersihkan Pesanan">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                </button>
                <div class="pos-receipt-title-box">
                    <h3 class="pos-receipt-title">Purchase Receipt</h3>
                    <span class="pos-receipt-id" id="activeReceiptNo">{{ $activeTrxNo ?? '#TRX-0001' }}</span>
                </div>
                <button type="button" class="pos-receipt-menu-btn" id="btnReceiptOptions" title="Opsi Pesanan">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- ORDER TYPE SEGMENTED BUTTONS -->
            <div class="pos-order-types" id="orderTypeBox">
                <button type="button" class="pos-order-type-btn active" data-type="Dine In">Dine In</button>
                <button type="button" class="pos-order-type-btn" data-type="Take Away">Take Away</button>
                <button type="button" class="pos-order-type-btn" data-type="Online">Order Online</button>
            </div>

            <!-- CUSTOMER NAME & TABLE INPUTS -->
            <div class="pos-meta-inputs">
                <div class="pos-meta-field">
                    <label class="pos-meta-label" for="customerName">Customer name</label>
                    <input
                        type="text"
                        id="customerName"
                        class="pos-meta-input"
                        placeholder="Nama pelanggan..."
                        value="Pelanggan"
                    >
                </div>
                <div class="pos-meta-field">
                    <label class="pos-meta-label" for="tableNumber">Table</label>
                    <select id="tableNumber" class="pos-meta-select">
                        <option value="Meja 01">Meja 01 - Indoor</option>
                        <option value="Meja 02">Meja 02 - Indoor</option>
                        <option value="Meja 03">Meja 03 - Indoor</option>
                        <option value="Meja 04">Meja 04 - Indoor</option>
                        <option value="Meja 05">Meja 05 - Outdoor</option>
                        <option value="Meja 06">Meja 06 - Outdoor</option>
                        <option value="Bawa Pulang">Take Away / Kasir</option>
                    </select>
                </div>
            </div>

            <!-- ORDER LIST SECTION -->
            <div class="pos-order-list-section">
                <div class="pos-order-list-heading">Order list</div>
                <div class="pos-order-items-scroll" id="cartListBody">
                    <!-- Rendered dynamically via JS -->
                </div>
            </div>

            <!-- PAYMENT DETAILS -->
            <div class="pos-payment-summary" id="paymentSummary">
                <div class="pos-summary-row">
                    <span>Subtotal</span>
                    <strong id="summarySubtotal">Rp 0</strong>
                </div>
                <div class="pos-summary-row">
                    <span>Pajak (PB1 0%)</span>
                    <strong id="summaryTax">Rp 0</strong>
                </div>
                <div class="pos-summary-row total">
                    <span>Total</span>
                    <strong id="summaryTotal">Rp 0</strong>
                </div>
            </div>

            <!-- PLACE ORDER ACTION BUTTON -->
            <button type="button" class="pos-place-order-btn" id="btnPlaceOrder" disabled>
                <div class="pos-place-order-circle">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </div>
                <span id="placeOrderText">Place Order Rp 0</span>
                <span class="pos-place-order-chevrons">❯❯❯</span>
            </button>

        </aside>

    </div>

</div>

<!-- MODAL ROOT -->
<div id="modalRoot"></div>

<script>
(() => {
    // DATA DARI DATABASE VIA POSCONTROLLER
    const ITEMS = @json($items);
    const MENUS = @json($menus);

    const MENU_IMAGES = {
        'Espresso': 'espresso.jpg',
        'Americano': 'americano.jpg',
        'Caffe Latte': 'caffe latte.jpg',
        'Cappuccino': 'cappucino.jpg',
        'Caramel Macchiato': 'caramel-macchiato.jpg',
        'Vanilla Latte': 'vanilla-latte.jpg',
        'Hazelnut Latte': 'hazelnut-latte.jpg',
        'Mocha': 'mocca.jpg',
        'Oat Milk Latte': 'oat-milk-latte.jpg',
        'Es Kopi Susu': 'eskopi-susu.jpg',
        'Chocolate': 'chocolate.jpg',
        'Matcha': 'matcha.jpg',
        'Croissant Butter': 'croissant.jpg',
        'French Fries': 'french-fries.jpg',
        'Roti Bakar Cokelat': 'roti-bakar-coklat.jpg',
        'Roti Bakar Keju': 'roti-bakar-keju.jpg',
        'Pisang Goreng Keju': 'pisang-goreng-keju.jpg',
        'Nasi Goreng Kampung': 'nasi-goreng-kampung.jpg',
        'Mie Goreng Spesial': 'mie-goreng-special.jpg',
        'Chicken Katsu Rice': 'chicken-katsu.jpg'
    };

    // STOK BERJALAN
    const stock = {};
    Object.keys(ITEMS).forEach(k => stock[k] = ITEMS[k].stock);

    const RAW_CATEGORIES = [...new Set(Object.values(MENUS).map(m => m.category))];
    const CATEGORIES = ['Semua', ...RAW_CATEGORIES];

    let cart = {};          // { menuId: qty }
    let category = 'Semua';
    let keyword = '';
    let method = 'tunai';
    let cash = 0;
    let orderType = 'Dine In';

    const $ = id => document.getElementById(id);
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

    // HITUNGAN STOK & KAPASITAS PORSI
    const maxPortions = id => {
        const recipeEntries = Object.entries(MENUS[id].recipe);
        if (!recipeEntries.length) return 999;
        return Math.min(...recipeEntries.map(([item, amt]) => Math.floor(stock[item] / amt)));
    };

    const usage = () => {
        const u = {};
        Object.entries(cart).forEach(([id, qty]) => {
            Object.entries(MENUS[id].recipe).forEach(([item, amt]) => {
                u[item] = (u[item] || 0) + amt * qty;
            });
        });
        return u;
    };

    const canAdd = id => {
        const u = usage();
        return Object.entries(MENUS[id].recipe)
            .every(([item, amt]) => stock[item] - (u[item] || 0) >= amt);
    };

    const hpp = id => Object.entries(MENUS[id].recipe)
        .reduce((s, [item, amt]) => s + amt * (ITEMS[item].price / ITEMS[item].size), 0);

    const total = () => Object.entries(cart)
        .reduce((s, [id, qty]) => s + MENUS[id].price * qty, 0);

    // WATERMARK ICONS
    const WATERMARKS = {
        Coffee: `<svg viewBox="0 0 24 24" fill="currentColor" class="pos-cat-watermark"><path d="M18.5 3H6c-1.1 0-2 .9-2 2v5.71c0 3.83 2.95 7.18 6.78 7.29 3.96.12 7.22-3.06 7.22-7v-1h.5c1.93 0 3.5-1.57 3.5-3.5S20.43 3 18.5 3zm0 4H18V5h.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5zM4 19h16v2H4z"/></svg>`,
        Tea: `<svg viewBox="0 0 24 24" fill="currentColor" class="pos-cat-watermark"><path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 5h-2V5h2v3zM2 19h20v2H2z"/></svg>`,
        Snack: `<svg viewBox="0 0 24 24" fill="currentColor" class="pos-cat-watermark"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>`,
        Default: `<svg viewBox="0 0 24 24" fill="currentColor" class="pos-cat-watermark"><circle cx="12" cy="12" r="10"/></svg>`
    };

    // RENDER CATEGORY CARDS
    function renderCategoryCards() {
        const catBox = $('posCategoryCards');
        if (!catBox) return;

        catBox.innerHTML = CATEGORIES.map(c => {
            const count = c === 'Semua'
                ? Object.keys(MENUS).length
                : Object.values(MENUS).filter(m => m.category === c).length;

            const isLow = c !== 'Semua' && Object.entries(MENUS).some(([id, m]) => {
                if (m.category !== c) return false;
                return maxPortions(id) <= 3;
            });

            const statusText = isLow ? 'Need to re-stock' : 'Available';
            const statusClass = isLow ? 'warning' : '';
            const isActive = c === category;

            const watermark = c.toLowerCase().includes('coffee') ? WATERMARKS.Coffee
                : (c.toLowerCase().includes('tea') || c.toLowerCase().includes('non')) ? WATERMARKS.Tea
                : WATERMARKS.Snack;

            return `
            <div class="pos-cat-card ${isActive ? 'active' : ''}" data-cat="${c}">
                <div class="pos-cat-header">
                    <span class="pos-cat-status-pill ${statusClass}">${statusText}</span>
                </div>
                <div class="pos-cat-body">
                    <h4 class="pos-cat-title">${c}</h4>
                    <span class="pos-cat-items">${count} menu</span>
                </div>
                ${watermark}
            </div>`;
        }).join('');
    }

    // RENDER PRODUCT GRID
    function renderGrid() {
        const list = Object.entries(MENUS).filter(([, m]) =>
            (category === 'Semua' || m.category === category) &&
            m.name.toLowerCase().includes(keyword.toLowerCase()));

        if (!list.length) {
            $('productGrid').innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 48px; color: var(--pos-muted); font-size: 14px;">Tidak ada menu yang sesuai pencarian.</div>';
            return;
        }

        $('productGrid').innerHTML = list.map(([id, m]) => {
            const left = maxPortions(id);
            const habis = left <= 0;
            const badge = habis
                ? '<span class="pos-stock-tag out">Habis</span>'
                : left <= 5 ? `<span class="pos-stock-tag low">Sisa ${left}</span>` : '';

            const imgName = MENU_IMAGES[m.name];
            const imgSrc = imgName ? `/menu/${encodeURIComponent(imgName)}` : '';

            return `
            <div class="pos-product-item ${habis ? 'disabled' : ''}" data-add="${id}">
                <div class="pos-product-thumb">
                    ${imgSrc ? `<img src="${imgSrc}" alt="${m.name}" loading="lazy">` : '<span style="font-size:24px;">☕</span>'}
                </div>
                <div class="pos-product-info">
                    <h5 class="pos-product-name" title="${m.name}">${m.name}</h5>
                    <div class="pos-product-price">${rp(m.price)}</div>
                </div>
                <div class="pos-product-footer">
                    ${badge}
                    <button type="button" class="pos-recipe-btn" data-sop="${id}">SOP</button>
                    <div class="pos-add-circle">+</div>
                </div>
            </div>`;
        }).join('');
    }

    // RENDER CART / PURCHASE RECEIPT
    function renderCart() {
        const ids = Object.keys(cart);
        const t = total();

        $('summarySubtotal').textContent = rp(t);
        $('summaryTotal').textContent = rp(t);
        $('placeOrderText').textContent = ids.length ? `Place Order ${rp(t)}` : 'Place Order Rp 0';
        $('btnPlaceOrder').disabled = !ids.length;

        if (!ids.length) {
            $('cartListBody').innerHTML = `
                <div class="pos-cart-empty-state">
                    <div style="font-size: 28px; margin-bottom: 8px;">🛒</div>
                    <strong>Pesanan masih kosong</strong>
                    <p style="font-size: 11px; margin-top: 4px;">Pilih menu dari katalog untuk memulai transaksi.</p>
                </div>`;
            return;
        }

        $('cartListBody').innerHTML = ids.map(id => {
            const m = MENUS[id];
            const imgName = MENU_IMAGES[m.name];
            const imgSrc = imgName ? `/menu/${encodeURIComponent(imgName)}` : '';
            const itemTotal = m.price * cart[id];

            return `
            <div class="pos-receipt-item">
                <div class="pos-receipt-item-thumb">
                    ${imgSrc ? `<img src="${imgSrc}" alt="${esc(m.name)}">` : '☕'}
                </div>
                <div class="pos-receipt-item-details">
                    <h6 class="pos-receipt-item-name" title="${esc(m.name)}">${esc(m.name)}</h6>
                    <div class="pos-receipt-item-sub">${rp(m.price)} × ${cart[id]}</div>
                    <span class="pos-receipt-item-tag">${esc(m.variant || 'Regular')}</span>
                </div>
                <div class="pos-receipt-item-right">
                    <span class="pos-receipt-item-price">${rp(itemTotal)}</span>
                    <div class="pos-qty-pill">
                        <button type="button" data-dec="${id}">−</button>
                        <span>${cart[id]}</span>
                        <button type="button" data-inc="${id}">+</button>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function refresh() {
        renderCategoryCards();
        renderGrid();
        renderCart();
    }

    // CART ACTIONS
    function addToCart(id) {
        if (!canAdd(id)) {
            flash('Bahan baku tidak cukup untuk menambah ' + MENUS[id].name);
            return;
        }
        cart[id] = (cart[id] || 0) + 1;
        renderCart();
    }

    function changeQty(id, delta) {
        if (delta > 0 && !canAdd(id)) {
            flash('Stok bahan baku tidak cukup.');
            return;
        }
        cart[id] = (cart[id] || 0) + delta;
        if (cart[id] <= 0) delete cart[id];
        renderCart();
    }

    function resetCart() {
        if (!Object.keys(cart).length) return;
        if (confirm('Bersihkan seluruh isi pesanan saat ini?')) {
            cart = {};
            cash = 0;
            method = 'tunai';
            refresh();
        }
    }

    // TOAST NOTIF
    function flash(msg) {
        const old = document.querySelector('.pos-toast');
        if (old) old.remove();
        const el = document.createElement('div');
        el.className = 'pos-toast';
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 2500);
    }

    // MODAL
    let qrisPollingInterval = null;

    const openModal = html => $('modalRoot').innerHTML = `<div class="modal-backdrop" id="modalBg">${html}</div>`;
    const closeModal = () => {
        if (qrisPollingInterval) {
            clearInterval(qrisPollingInterval);
            qrisPollingInterval = null;
        }
        $('modalRoot').innerHTML = '';
    };

    function showSop(id) {
        const m = MENUS[id];
        const rows = Object.entries(m.recipe).map(([item, amt]) => `
            <div class="summary-line">
                <span>${ITEMS[item]?.name ?? 'Bahan #' + item}</span>
                <span><b>${amt}</b> ${ITEMS[item]?.unit ?? 'satuan'}</span>
            </div>`).join('');
        const cost = hpp(id);

        openModal(`
            <div class="modal-box">
                <h4>Takaran SOP · ${m.name}</h4>
                <p>Formula racikan per 1 porsi. Bahan akan otomatis terpotong saat pesanan dibayar.</p>
                <div class="summary-box">
                    <div class="summary-title">RESEP BAHAN BAKU</div>
                    ${rows}
                </div>
                <div class="summary-box">
                    <div class="summary-line"><span>Harga Jual</span><span>${rp(m.price)}</span></div>
                    <div class="summary-line"><span>Estimasi HPP</span><span>${rp(cost)}</span></div>
                    <div class="summary-line"><span>Estimasi Margin</span><span><b>${rp(m.price - cost)}</b></span></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="primary-button" data-close>Tutup</button>
                </div>
            </div>`);
    }

    // PAYMENT MODAL (POP UP PEMBAYARAN)
    function openPaymentModal() {
        const t = total();
        if (!t) return;

        const custName = $('customerName').value || 'Pelanggan';
        const table = $('tableNumber').value;

        const lines = Object.keys(cart).map(id => `
            <div class="summary-line">
                <span>${MENUS[id].name} × ${cart[id]}</span>
                <span>${rp(MENUS[id].price * cart[id])}</span>
            </div>`).join('');

        const change = cash - t;
        const canSubmit = method !== 'tunai' || cash >= t;

        openModal(`
            <div class="modal-box wide">
                <h4>Konfirmasi Pembayaran</h4>
                <p>Tipe: <b>${orderType}</b> · Meja: <b>${table}</b> · Pelanggan: <b>${custName}</b></p>

                <div class="summary-box">
                    ${lines}
                    <div class="summary-line" style="border-top:1px dashed var(--pos-border); padding-top:8px; margin-top:8px;">
                        <span><b>Total Tagihan</b></span>
                        <span style="font-size:16px; color:var(--pos-brown-dark);"><b>${rp(t)}</b></span>
                    </div>
                </div>

                <div class="form-field" style="margin-top:14px;">
                    <label class="form-label">Metode Pembayaran</label>
                    <div class="pos-method-selector" id="modalMethodBox">
                        <button type="button" class="pos-method-btn ${method === 'tunai' ? 'active' : ''}" data-pay-method="tunai">Tunai</button>
                        <button type="button" class="pos-method-btn ${method === 'qris' ? 'active' : ''}" data-pay-method="qris">QRIS</button>
                        <button type="button" class="pos-method-btn ${method === 'debit' ? 'active' : ''}" data-pay-method="debit">Debit Card</button>
                    </div>
                </div>

                ${method === 'tunai' ? `
                <div class="form-field" id="cashFieldBox">
                    <label class="form-label">Nominal Uang Tunai Diterima</label>
                    <input type="number" id="cashInput" class="form-input" min="0" value="${cash || ''}" placeholder="0" autofocus>
                    <div class="quick-cash" style="margin-top:8px; display:flex; gap:8px;">
                        <button type="button" class="outline-button" data-set-cash="${t}">Uang Pas</button>
                        <button type="button" class="outline-button" data-set-cash="50000">50.000</button>
                        <button type="button" class="outline-button" data-set-cash="100000">100.000</button>
                    </div>
                    <div class="form-hint" id="modalChangeHint" style="margin-top:8px; font-size:12px;">
                        ${cash <= 0 ? 'Ketik uang yang diterima pelanggan.' : change < 0 ? `<span style="color:var(--pos-red-text)">Kurang ${rp(-change)}</span>` : `Kembalian: <b>${rp(change)}</b>`}
                    </div>
                </div>` : `
                <div class="info-note" style="margin-top:12px; background:var(--pos-cream-bg); border:1px solid var(--pos-border); padding:10px 14px; border-radius:10px; font-size:12px;">
                    Pembayaran ${method.toUpperCase()} akan diverifikasi secara otomatis (simulasi).
                </div>`}

                <div class="modal-actions" style="margin-top:20px;">
                    <button type="button" class="outline-button" data-close>Batal</button>
                    <button type="button" class="primary-button" id="btnConfirmPay" ${canSubmit ? '' : 'disabled'}>
                        Proses Pembayaran ${rp(t)}
                    </button>
                </div>
            </div>`);
    }

    // HASIL TRANSAKSI SUKSES
    function handleSuccessfulPayment(json, u) {
        if (qrisPollingInterval) {
            clearInterval(qrisPollingInterval);
            qrisPollingInterval = null;
        }

        Object.entries(u).forEach(([item, amt]) => stock[item] -= amt);
        const cutsHtml = (json.cuts || []).map(c => `
            <div class="summary-line">
                <span>${esc(c.name)}</span>
                <span style="color:var(--pos-brown-dark); font-weight:600;">-${c.amount} ${esc(c.unit)}</span>
            </div>`).join('');

        cart = {};
        cash = 0;
        method = 'tunai';
        refresh();

        const statPillB = document.querySelector('.pos-stat-pill b');
        if (statPillB) {
            const countMatch = statPillB.textContent.match(/\d+/);
            if (countMatch) {
                const newCount = parseInt(countMatch[0], 10) + 1;
                statPillB.textContent = `${newCount} Pesanan Hari Ini`;
            }
        }

        // Next TRX no
        const currentNo = json.no || '#TRX-0000';
        const numPart = parseInt(currentNo.replace(/\D/g, ''), 10) || 0;
        $('activeReceiptNo').textContent = `#TRX-${String(numPart + 1).padStart(4, '0')}`;

        openModal(`
            <div class="modal-box wide">
                <h4 style="color:var(--pos-brown-dark);">✔ Transaksi Sukses & Selesai</h4>
                <p style="font-size:13px; margin:4px 0 16px 0;">
                    No: <b>${esc(json.no)}</b> · Total: <b>${rp(json.total)}</b>
                    ${json.change ? ` · Kembalian: <b>${rp(json.change)}</b>` : ''}
                </p>
                <div class="summary-box">
                    <div class="summary-title">PENGURANGAN STOK BAHAN BAKU REAL-TIME</div>
                    ${cutsHtml || '<div style="font-size:12px; color:var(--pos-muted);">Semua bahan baku resep otomatis terpotong.</div>'}
                </div>
                <div class="modal-actions" style="margin-top:18px;">
                    <button type="button" class="primary-button" data-close>Transaksi Baru</button>
                </div>
            </div>`);
    }

    // MODAL QRIS DINAMIS DENGAN POLLING & SIMULATOR
    function openQrisModal(json, u) {
        openModal(`
            <div class="modal-box wide" style="text-align: center;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h4 style="margin:0; color:var(--pos-brown-dark);">Pembayaran Direct QRIS</h4>
                    <span style="background:${json.is_mock ? '#fef3c7' : '#dcfce7'}; color:${json.is_mock ? '#b45309' : '#15803d'}; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600;">
                        ${json.is_mock ? 'Mode Simulasi' : 'Midtrans Sandbox'}
                    </span>
                </div>

                <p style="font-size:13px; color:var(--pos-muted); margin:0 0 12px 0;">
                    Silakan scan QRIS di bawah ini dengan GoPay, BCA, Livin, ShopeePay, atau aplikasi perbankan lainnya.
                </p>

                <div style="background:#fff; padding:12px; border-radius:12px; display:inline-block; border:1px solid var(--pos-border); box-shadow:0 4px 14px rgba(0,0,0,0.06); margin-bottom:12px;">
                    <img src="${json.qr_url}" alt="QRIS Code" style="width:200px; height:200px; display:block; object-fit:contain; border-radius:8px;">
                </div>

                <div style="font-size:22px; font-weight:700; color:var(--pos-brown-dark); margin-bottom:4px;">
                    ${rp(json.total)}
                </div>
                <div style="font-size:12px; color:var(--pos-muted); margin-bottom:14px;">
                    Order: <b>${json.no}</b> · Ref: <code>${json.order_id}</code>
                </div>

                <div id="qrisStatusNotice" style="padding:10px 14px; background:var(--pos-cream-bg); border-radius:8px; font-size:12px; display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:14px; border:1px solid var(--pos-border);">
                    <span class="pos-spin-loader"></span>
                    <span>Menunggu konfirmasi pembayaran pelanggan...</span>
                </div>

                <!-- Alat Pengujian Sandbox (Gratis) -->
                <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:12px; text-align:left; font-size:12px; margin-bottom:16px;">
                    <div style="font-weight:600; color:#334155; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                        <span>🛠 Alat Pengujian Sandbox:</span>
                        <span style="font-size:11px; color:#64748b;">(Gratis)</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; gap:8px;">
                            <button type="button" class="outline-button" style="flex:1; padding:6px 8px; font-size:11px;" id="btnCopyQr" data-qr="${json.qr_string}">
                                📋 Salin String QR
                            </button>
                            <a href="https://simulator.sandbox.midtrans.com/openapi/qris/index" target="_blank" rel="noopener noreferrer" class="outline-button" style="flex:1; padding:6px 8px; font-size:11px; text-align:center; text-decoration:none; color:inherit; display:flex; align-items:center; justify-content:center;">
                                🌐 Simulator Midtrans ↗
                            </a>
                        </div>
                        <button type="button" class="primary-button" style="width:100%; padding:8px; font-size:12px; background:#0284c7;" id="btnSimulatePay" data-trx-id="${json.transaksi_id}">
                            ⚡ Bayar Sekarang (Simulasi Instan)
                        </button>
                    </div>
                </div>

                <div class="modal-actions" style="justify-content:center;">
                    <button type="button" class="outline-button" id="btnCancelPendingTrx" data-trx-id="${json.transaksi_id}" style="color:var(--pos-red-text); border-color:rgba(220,38,38,0.3);">
                        Batalkan Transaksi
                    </button>
                </div>
            </div>
        `);

        // Polling status berkala setiap 2.5 detik
        if (qrisPollingInterval) clearInterval(qrisPollingInterval);
        qrisPollingInterval = setInterval(() => {
            fetch(`/kasir/pos/status/${json.transaksi_id}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'berhasil') {
                    clearInterval(qrisPollingInterval);
                    qrisPollingInterval = null;
                    handleSuccessfulPayment({
                        no: res.no,
                        total: res.total,
                        cuts: res.cuts || []
                    }, u);
                } else if (res.status === 'batal') {
                    clearInterval(qrisPollingInterval);
                    qrisPollingInterval = null;
                    closeModal();
                    flash('Transaksi QRIS telah dibatalkan.');
                }
            })
            .catch(() => {});
        }, 2500);
    }

    // PROCESS PAYMENT SERVER CALL
    function processPay() {
        const t = total();
        const u = usage();
        const confirmBtn = $('btnConfirmPay');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Memproses...';
        }

        fetch('/kasir/pos', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                items: Object.entries(cart).map(([menu, qty]) => ({ menu: Number(menu), qty })),
                method,
                cash: method === 'tunai' ? cash : null,
            }),
        })
        .then(async r => {
            const json = await r.json();
            if (!r.ok) throw new Error(json.message || 'Gagal memproses transaksi.');
            return json;
        })
        .then(json => {
            if (json.type === 'qris') {
                return openQrisModal(json, u);
            }

            handleSuccessfulPayment(json, u);
        })
        .catch(err => {
            flash(err.message || 'Gagal memproses transaksi.');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = `Proses Pembayaran ${rp(t)}`;
            }
        });
    }

    // EVENTS DELEGATION
    document.addEventListener('click', e => {
        const t = e.target;

        // Kategori
        const catEl = t.closest('[data-cat]');
        if (catEl) {
            category = catEl.dataset.cat;
            renderCategoryCards();
            renderGrid();
            return;
        }

        // Tipe Pesanan (Dine In / Take Away / Online)
        const typeEl = t.closest('[data-type]');
        if (typeEl) {
            orderType = typeEl.dataset.type;
            document.querySelectorAll('#orderTypeBox button').forEach(b => b.classList.remove('active'));
            typeEl.classList.add('active');
            return;
        }

        // Tambah ke cart dari grid
        const addEl = t.closest('[data-add]');
        if (addEl && !t.closest('[data-sop]')) {
            const id = addEl.dataset.add;
            if (!addEl.classList.contains('disabled')) addToCart(id);
            return;
        }

        // Resep SOP
        const sopEl = t.closest('[data-sop]');
        if (sopEl) {
            e.stopPropagation();
            showSop(sopEl.dataset.sop);
            return;
        }

        // Stepper Qty
        if (t.closest('[data-inc]')) return changeQty(t.closest('[data-inc]').dataset.inc, 1);
        if (t.closest('[data-dec]')) return changeQty(t.closest('[data-dec]').dataset.dec, -1);

        // Reset Trx
        if (t.closest('#btnResetTrx')) return resetCart();

        // Place Order / Buka Pembayaran
        if (t.closest('#btnPlaceOrder')) return openPaymentModal();

        // Modal method switch
        const payMethodEl = t.closest('[data-pay-method]');
        if (payMethodEl) {
            method = payMethodEl.dataset.payMethod;
            openPaymentModal();
            return;
        }

        // Quick cash in modal
        const setCashEl = t.closest('[data-set-cash]');
        if (setCashEl) {
            cash = Number(setCashEl.dataset.setCash);
            const input = $('cashInput');
            if (input) input.value = cash;
            const change = cash - total();
            $('modalChangeHint').innerHTML = change < 0 ? `<span style="color:var(--pos-red-text)">Kurang ${rp(-change)}</span>` : `Kembalian: <b>${rp(change)}</b>`;
            $('btnConfirmPay').disabled = change < 0;
            return;
        }

        // Confirm Pay
        if (t.id === 'btnConfirmPay') return processPay();

        // Salin String QR
        const copyQrBtn = t.closest('#btnCopyQr');
        if (copyQrBtn) {
            const qrStr = copyQrBtn.dataset.qr || '';
            navigator.clipboard.writeText(qrStr).then(() => {
                flash('String QR berhasil disalin ke clipboard!');
            }).catch(() => {
                prompt('Salin string QR berikut:', qrStr);
            });
            return;
        }

        // Simulasi Pembayaran Cepat (Testing Sandbox)
        const simPayBtn = t.closest('#btnSimulatePay');
        if (simPayBtn) {
            const trxId = simPayBtn.dataset.trxId;
            simPayBtn.disabled = true;
            simPayBtn.textContent = 'Memverifikasi Pembayaran...';

            fetch(`/kasir/pos/simulate/${trxId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
            .then(r => r.json())
            .then(res => {
                flash('Pembayaran QRIS berhasil disimulasikan!');
            })
            .catch(err => {
                flash('Gagal simulasi: ' + err.message);
                simPayBtn.disabled = false;
                simPayBtn.textContent = '⚡ Bayar Sekarang (Simulasi Instan)';
            });
            return;
        }

        // Batal Transaksi Pending QRIS
        const cancelTrxBtn = t.closest('#btnCancelPendingTrx');
        if (cancelTrxBtn) {
            const trxId = cancelTrxBtn.dataset.trxId;
            if (confirm('Batalkan transaksi QRIS ini?')) {
                fetch(`/kasir/pos/cancel/${trxId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                }).finally(() => {
                    closeModal();
                    flash('Transaksi QRIS dibatalkan.');
                });
            }
            return;
        }

        // Close Modal
        if (t.closest('[data-close]') || t.id === 'modalBg') return closeModal();
    });

    // SEARCH INPUT
    document.addEventListener('input', e => {
        if (e.target.id === 'posSearch') {
            keyword = e.target.value;
            renderGrid();
        }
        if (e.target.id === 'cashInput') {
            cash = Number(e.target.value) || 0;
            const change = cash - total();
            const hint = $('modalChangeHint');
            if (hint) {
                hint.innerHTML = cash <= 0 ? 'Ketik uang yang diterima pelanggan.' : change < 0 ? `<span style="color:var(--pos-red-text)">Kurang ${rp(-change)}</span>` : `Kembalian: <b>${rp(change)}</b>`;
            }
            const btn = $('btnConfirmPay');
            if (btn) btn.disabled = (method === 'tunai' && cash < total());
        }
    });

    // KEYBOARD SHORTCUT ⌘K or CTRL+K
    window.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            const s = $('posSearch');
            if (s) s.focus();
        }
    });

    refresh();

})();
</script>

<style>
.pos-spin-loader {
    width: 14px;
    height: 14px;
    border: 2px solid var(--pos-border);
    border-top-color: var(--pos-brown-dark);
    border-radius: 50%;
    animation: posSpinAnim 0.8s linear infinite;
    display: inline-block;
}
@keyframes posSpinAnim {
    to { transform: rotate(360deg); }
}
</style>

@endsection
