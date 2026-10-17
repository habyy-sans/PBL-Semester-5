@extends('layouts.app')

@section('title', 'Kasir')
@section('description', 'Kelola transaksi penjualan pelanggan.')

@section('content')

<section class="page active">

    <div class="page-card">

        <div class="table-toolbar">
            <input type="text" id="posSearch" class="search-input" placeholder="Cari menu...">
            <span class="table-meta" id="posMeta"></span>
            <button class="primary-button" id="btnNewTrx" type="button">+ Transaksi Baru</button>
        </div>

        <div class="pos-tabs" id="posTabs"></div>

        <div class="pos-layout">

            <div class="product-grid" id="productGrid"></div>

            <div class="cart-card">
                <h4>Pesanan</h4>
                <div id="cartBody"></div>
            </div>

        </div>

    </div>

</section>

<div id="modalRoot"></div>

<script>
(() => {

    // ---------- DATA (nanti dari backend) ----------
    const ITEMS = @json(config('demo.items'));
    const MENUS = @json(config('demo.menus'));

    const stock = {};                          // stok berjalan (satuan terkecil)
    Object.keys(ITEMS).forEach(k => stock[k] = ITEMS[k].stock);

    const CATEGORIES = ['Semua', ...new Set(Object.values(MENUS).map(m => m.category))];

    let cart     = {};                         // { menuId: qty }
    let category = 'Semua';
    let keyword  = '';
    let method   = 'tunai';
    let cash     = 0;

    const $  = id => document.getElementById(id);
    const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');

    // ---------- HITUNGAN STOK ----------
    // jumlah porsi maksimal dari stok yang ada
    const maxPortions = id => Math.min(...Object.entries(MENUS[id].recipe)
        .map(([item, amt]) => Math.floor(stock[item] / amt)));

    // pemakaian bahan oleh isi keranjang
    const usage = () => {
        const u = {};
        Object.entries(cart).forEach(([id, qty]) => {
            Object.entries(MENUS[id].recipe).forEach(([item, amt]) => {
                u[item] = (u[item] || 0) + amt * qty;
            });
        });
        return u;
    };

    // masih cukup bahan untuk +1 porsi?
    const canAdd = id => {
        const u = usage();
        return Object.entries(MENUS[id].recipe)
            .every(([item, amt]) => stock[item] - (u[item] || 0) >= amt);
    };

    const hpp = id => Object.entries(MENUS[id].recipe)
        .reduce((s, [item, amt]) => s + amt * (ITEMS[item].price / ITEMS[item].size), 0);

    const total = () => Object.entries(cart)
        .reduce((s, [id, qty]) => s + MENUS[id].price * qty, 0);

    // ---------- RENDER MENU ----------
    function renderTabs() {
        $('posTabs').innerHTML = CATEGORIES.map(c =>
            `<button type="button" class="pos-tab ${c === category ? 'active' : ''}" data-cat="${c}">${c}</button>`
        ).join('');
    }

    function renderGrid() {
        const list = Object.entries(MENUS).filter(([, m]) =>
            (category === 'Semua' || m.category === category) &&
            m.name.toLowerCase().includes(keyword.toLowerCase()));

        $('posMeta').textContent = list.length + ' menu';

        if (!list.length) {
            $('productGrid').innerHTML = '<div class="page-empty" style="grid-column:1/-1">Menu tidak ditemukan</div>';
            return;
        }

        $('productGrid').innerHTML = list.map(([id, m]) => {
            const left  = maxPortions(id);
            const habis = left <= 0;
            const badge = habis
                ? '<em class="pos-badge out">Habis</em>'
                : left <= 5 ? `<em class="pos-badge low">Sisa ${left} porsi</em>` : '';
            return `
            <div class="product-card ${habis ? 'disabled' : ''}" data-add="${id}">
                <div class="product-image">${m.emoji}</div>
                <strong>${m.name}</strong>
                <span>${rp(m.price)}</span>
                <div class="pos-card-foot">
                    ${badge}
                    <button type="button" class="pos-sop-link" data-sop="${id}">SOP</button>
                </div>
            </div>`;
        }).join('');
    }

    // ---------- RENDER KERANJANG ----------
    const canPay = () => Object.keys(cart).length && (method !== 'tunai' || cash >= total());

    const cashHint = change => cash <= 0 ? 'Masukkan jumlah uang pelanggan.'
        : change < 0 ? `<span style="color:var(--red)">Kurang ${rp(-change)}</span>`
        : `Kembalian: <b>${rp(change)}</b>`;

    function renderCart() {
        const ids = Object.keys(cart);

        if (!ids.length) {
            $('cartBody').innerHTML = '<div class="cart-empty">Belum ada produk</div>';
            return;
        }

        const t      = total();
        const change = cash - t;

        $('cartBody').innerHTML = `
            <div class="cart-lines">
                ${ids.map(id => `
                <div class="cart-line">
                    <div class="cart-line-info">
                        <strong>${MENUS[id].name}</strong>
                        <small>${rp(MENUS[id].price)}</small>
                    </div>
                    <div class="qty-control">
                        <button type="button" data-dec="${id}">−</button>
                        <b>${cart[id]}</b>
                        <button type="button" data-inc="${id}">+</button>
                    </div>
                    <div class="cart-line-total">${rp(MENUS[id].price * cart[id])}</div>
                </div>`).join('')}
            </div>

            <div class="cart-total">
                <span>Total</span>
                <strong>${rp(t)}</strong>
            </div>

            <div class="form-field">
                <label class="form-label">Metode Pembayaran</label>
                <div class="segmented" id="methodBox">
                    <button type="button" data-method="tunai" class="${method === 'tunai' ? 'active' : ''}">Tunai</button>
                    <button type="button" data-method="qris"  class="${method === 'qris'  ? 'active' : ''}">QRIS</button>
                    <button type="button" data-method="debit" class="${method === 'debit' ? 'active' : ''}">Debit</button>
                </div>
            </div>

            ${method === 'tunai' ? `
            <div class="form-field">
                <label class="form-label">Uang Diterima</label>
                <input type="number" id="cashInput" class="form-input" min="0" value="${cash || ''}" placeholder="0">
                <div class="quick-cash">
                    <button type="button" data-cash="${t}">Uang pas</button>
                    <button type="button" data-cash="50000">50.000</button>
                    <button type="button" data-cash="100000">100.000</button>
                </div>
                <div class="form-hint" id="changeHint">${cashHint(change)}</div>
            </div>` : `
            <div class="info-note">Pembayaran ${method.toUpperCase()} dianggap berhasil (simulasi).</div>`}

            <button type="button" class="primary-button pay-button" id="btnPay" ${canPay() ? '' : 'disabled'}>Bayar ${rp(t)}</button>
        `;
    }

    function refresh() { renderGrid(); renderCart(); }

    // ---------- AKSI KERANJANG ----------
    function addToCart(id) {
        if (!canAdd(id)) { flash('Bahan tidak cukup untuk menambah ' + MENUS[id].name); return; }
        cart[id] = (cart[id] || 0) + 1;
        refresh();
    }

    function changeQty(id, delta) {
        if (delta > 0 && !canAdd(id)) { flash('Stok bahan tidak cukup'); return; }
        cart[id] = (cart[id] || 0) + delta;
        if (cart[id] <= 0) delete cart[id];
        refresh();
    }

    function resetTrx() { cart = {}; cash = 0; method = 'tunai'; refresh(); }

    // ---------- MODAL ----------
    const openModal  = html => $('modalRoot').innerHTML = `<div class="modal-backdrop" id="modalBg">${html}</div>`;
    const closeModal = () => $('modalRoot').innerHTML = '';

    function flash(msg) {
        const old = document.querySelector('.pos-toast');
        if (old) old.remove();
        const el = document.createElement('div');
        el.className = 'pos-toast';
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 2200);
    }

    function showSop(id) {
        const m = MENUS[id];
        const rows = Object.entries(m.recipe).map(([item, amt]) => `
            <div class="summary-line">
                <span>${ITEMS[item].name}</span>
                <span><b>${amt}</b> ${ITEMS[item].unit}</span>
            </div>`).join('');
        const cost = hpp(id);

        openModal(`
            <div class="modal-box">
                <h4>${m.emoji} SOP · ${m.name}</h4>
                <p>Takaran bahan untuk 1 porsi. Bahan ini otomatis berkurang saat menu terjual.</p>
                <div class="summary-box">
                    <div class="summary-title">RESEP (SATUAN TERKECIL)</div>
                    ${rows}
                </div>
                <div class="summary-box">
                    <div class="summary-line"><span>Harga jual</span><span>${rp(m.price)}</span></div>
                    <div class="summary-line"><span>Estimasi HPP</span><span>${rp(cost)}</span></div>
                    <div class="summary-line"><span>Estimasi margin</span><span><b>${rp(m.price - cost)}</b></span></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="primary-button" data-close>Tutup</button>
                </div>
            </div>`);
    }

    function confirmPay() {
        const t = total();
        const lines = Object.keys(cart).map(id =>
            `<div class="summary-line"><span>${MENUS[id].name} × ${cart[id]}</span><span>${rp(MENUS[id].price * cart[id])}</span></div>`
        ).join('');

        openModal(`
            <div class="modal-box">
                <h4>Konfirmasi Pembayaran</h4>
                <div class="summary-box">
                    ${lines}
                    <div class="summary-line"><span><b>Total</b></span><span><b>${rp(t)}</b></span></div>
                    <div class="summary-line"><span>Metode</span><span>${method.toUpperCase()}</span></div>
                    ${method === 'tunai' ? `<div class="summary-line"><span>Kembalian</span><span>${rp(cash - t)}</span></div>` : ''}
                </div>
                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>Batal</button>
                    <button type="button" class="primary-button" id="btnConfirm">Proses Transaksi</button>
                </div>
            </div>`);
    }

    function processPay() {
        const t = total();
        const u = usage();
        const no = '#TRX-' + String(Date.now()).slice(-6);
        const change = method === 'tunai' ? cash - t : 0;

        // potong stok sesuai SOP (di backend: dilakukan di server dalam 1 DB transaction)
        Object.entries(u).forEach(([item, amt]) => stock[item] -= amt);

        const cut = Object.entries(u).map(([item, amt]) =>
            `<div class="summary-line"><span>${ITEMS[item].name}</span><span>−${amt} ${ITEMS[item].unit}</span></div>`
        ).join('');

        cart = {}; cash = 0; method = 'tunai';
        refresh();

        openModal(`
            <div class="modal-box wide">
                <h4>✔ Transaksi Berhasil</h4>
                <p>${no} · ${rp(t)}${change ? ' · Kembalian ' + rp(change) : ''}</p>
                <div class="summary-box">
                    <div class="summary-title">STOK BAHAN TERPOTONG OTOMATIS</div>
                    ${cut}
                </div>
                <div class="modal-actions">
                    <button type="button" class="primary-button" data-close>Selesai</button>
                </div>
            </div>`);
    }

    // ---------- EVENT ----------
    document.addEventListener('click', e => {
        const t = e.target;

        if (t.closest('[data-sop]'))    { e.stopPropagation(); return showSop(t.closest('[data-sop]').dataset.sop); }
        if (t.closest('[data-add]'))    { const id = t.closest('[data-add]').dataset.add; if (!t.closest('.disabled')) addToCart(id); return; }
        if (t.closest('[data-inc]'))    return changeQty(t.closest('[data-inc]').dataset.inc, 1);
        if (t.closest('[data-dec]'))    return changeQty(t.closest('[data-dec]').dataset.dec, -1);
        if (t.closest('[data-cat]'))    { category = t.closest('[data-cat]').dataset.cat; renderTabs(); return renderGrid(); }
        if (t.closest('[data-method]')) { method = t.closest('[data-method]').dataset.method; return renderCart(); }
        if (t.closest('[data-cash]'))   { cash = Number(t.closest('[data-cash]').dataset.cash); return renderCart(); }
        if (t.closest('[data-close]') || t.id === 'modalBg') return closeModal();
        if (t.id === 'btnPay')          return confirmPay();
        if (t.id === 'btnConfirm')      return processPay();
        if (t.id === 'btnNewTrx')       return resetTrx();
    });

    document.addEventListener('input', e => {
        if (e.target.id === 'posSearch') { keyword = e.target.value; renderGrid(); }
        if (e.target.id === 'cashInput') {
            cash = Number(e.target.value) || 0;
            // update hint + tombol tanpa render ulang (supaya fokus input tidak hilang)
            $('changeHint').innerHTML = cashHint(cash - total());
            $('btnPay').disabled = !canPay();
        }
    });

    renderTabs();
    refresh();

})();
</script>

@endsection