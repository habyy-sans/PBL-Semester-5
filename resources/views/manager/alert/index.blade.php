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
                <option value="selesai">Sudah Dievaluasi</option>
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

    const RESTOCK_URL = "{{ route('manager.restock') }}";
    const DRAFT_NO    = '#RS-024';
    const MONTHS      = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

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

    const wasteDone = {};                // { idMutasi: true } = sudah dievaluasi

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

    // alasan saja ("Salah Buat", "Rusak / Pecah"), tanpa nama menu
    const wasteReason = m => m.ref.replace('Waste · ', '').split(' · ')[0];

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
                done: false,
                key: i.key,
            });
        });

        MUT.filter(m => m.type === 'waste').forEach(m => {
            list.push({
                id: 'w-' + m.id,
                type: 'waste',
                sort: m.date + ' ' + m.time,
                title: 'Waste — ' + wasteReason(m),
                text: `${wasteLines(m)} · kerugian ${rp(wasteValue(m))}`,
                when: fmtDate(m.date) + ' · ' + m.time,
                done: !!wasteDone[m.id],
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

        $('stDraft').textContent    = (LOW.length ? 1 : 0) + ' Draft';
        $('stDraftSub').textContent = LOW.length ? DRAFT_NO + ' menunggu approval' : 'Tidak ada draft';
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
                    ? '<span class="status success">Sudah Dievaluasi</span>'
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
        const qty  = suggest(i);

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

                <div class="info-note">Item ini termasuk dalam draft ${DRAFT_NO} (${LOW.length} item). Jumlah dapat disesuaikan dan draft disetujui di halaman Restock.</div>

                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>Tutup</button>
                    <button type="button" class="primary-button" data-go-restock>Buka di Restock</button>
                </div>
            </div>`);
    }

    // ---- detail alert waste ----
    function showWaste(id) {
        const m = MUT.find(x => x.id === Number(id));
        if (!m) return;

        const done = !!wasteDone[m.id];

        const lines = m.lines.map(l => `
            <div class="summary-line">
                <span>${ITEMS[l.item].name}</span>
                <span>${Math.abs(l.amount).toLocaleString('id-ID')} ${ITEMS[l.item].unit} · ${rp(lineValue(l))}</span>
            </div>`).join('');

        openModal(`
            <div class="modal-box wide">
                <h4>Waste · ${wasteReason(m)}</h4>
                <p>${fmtDate(m.date)} · ${m.time} · dicatat oleh Kepala Shift</p>

                <div class="summary-box">
                    <div class="summary-title">BARANG TERBUANG</div>
                    ${lines}
                    <div class="summary-line bad"><span><b>Total kerugian</b></span><span><b>${rp(wasteValue(m))}</b></span></div>
                </div>

                <div class="info-note">${done
                    ? 'Alert ini sudah dievaluasi.'
                    : 'Stok cabang sudah berkurang otomatis. Evaluasi dampaknya terhadap persediaan dan kerugian cabang.'}</div>

                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>Tutup</button>
                    ${done ? '' : `<button type="button" class="primary-button" data-evaluate="${m.id}">Tandai Dievaluasi</button>`}
                </div>
            </div>`);
    }

    // ---------- EVENT ----------
    document.addEventListener('click', e => {
        const t = e.target;

        const d = t.closest('[data-detail]');
        if (d) {
            const id = d.dataset.detail;
            return id.startsWith('r-') ? showRestock(id.slice(2)) : showWaste(id.slice(2));
        }

        if (t.closest('[data-go-restock]')) { window.location.href = RESTOCK_URL; return; }

        if (t.closest('[data-evaluate]')) {
            wasteDone[t.closest('[data-evaluate]').dataset.evaluate] = true;
            closeModal();
            renderTable();
            return flash('Alert waste ditandai sudah dievaluasi.');
        }

        if (t.closest('[data-close]') || t.id === 'modalBg') return closeModal();
    });

    document.addEventListener('input', e => {
        if (e.target.id === 'alertSearch') { keyword = e.target.value; renderTable(); }
    });

    document.addEventListener('change', e => {
        if (e.target.id === 'alertType')   { fType   = e.target.value; renderTable(); }
        if (e.target.id === 'alertStatus') { fStatus = e.target.value; renderTable(); }
    });

    renderTable();

})();
</script>

@endsection