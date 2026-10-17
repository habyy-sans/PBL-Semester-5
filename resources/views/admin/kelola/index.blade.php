@extends('layouts.app')

@section('title', 'Kelola Pengguna')
@section('description', 'Kelola akun pengguna dan role.')

@section('content')

<section class="page active">

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">▣</div>
            <div class="stat-content">
                <span class="stat-label">Total Pengguna</span>
                <strong id="stTotal">0</strong>
                <small id="stTotalSub">-</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">↻</div>
            <div class="stat-content">
                <span class="stat-label">Pengguna Aktif</span>
                <strong id="stActive">0</strong>
                <small class="positive">Bisa login</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">△</div>
            <div class="stat-content">
                <span class="stat-label">Pengguna Nonaktif</span>
                <strong id="stInactive">0</strong>
                <small class="danger-text">Tidak bisa login</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">≡</div>
            <div class="stat-content">
                <span class="stat-label">Store Tanpa Manager</span>
                <strong id="stNoManager">0</strong>
                <small class="warning" id="stNoManagerSub">-</small>
            </div>
        </div>

    </div>

    <div id="flashRoot"></div>

    <div class="page-card section-gap">

        <div class="table-toolbar">
            <input type="text" id="usSearch" class="search-input" placeholder="Cari nama / email...">
            <select id="usRole" class="select"></select>
            <select id="usStore" class="select"></select>
            <select id="usStatus" class="select">
                <option value="semua">Semua Status</option>
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
            </select>
            <span class="table-meta" id="usMeta"></span>
            <button type="button" class="primary-button" id="btnAdd">+ Tambah Pengguna</button>
        </div>

        <div class="table-wrapper">
            <table class="table-fixed">
                <colgroup>
                    <col style="width: 28%">
                    <col style="width: 16%">
                    <col style="width: 16%">
                    <col style="width: 10%">
                    <col style="width: 16%">
                    <col style="width: 14%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Role</th>
                        <th>Store</th>
                        <th>Status</th>
                        <th>Login Terakhir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="usBody"></tbody>
            </table>
        </div>

    </div>

</section>

<div id="modalRoot"></div>

<script>
(() => {

    // ---------- DATA (nanti dari backend) ----------
    const STORES = @json(config('demo.stores'));
    const ROLES  = @json(collect(config('roles.roles'))->map(fn ($r) => $r['label']));
    const USERS  = @json(config('demo_users.list'));

    // role yang terikat ke 1 store (DC dan Admin melihat semua store)
    const STORE_ROLES = ['kasir', 'ic', 'manager'];
    const needsStore  = role => STORE_ROLES.includes(role);

    const STORE_IDS = Object.keys(STORES);
    const ROLE_KEYS = Object.keys(ROLES);

    const $ = id => document.getElementById(id);

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ---------- STATE ----------
    let users   = USERS.map(u => ({ ...u }));
    let nextId  = Math.max(...users.map(u => u.id)) + 1;

    let keyword = '';
    let fRole   = 'semua';
    let fStore  = 'semua';
    let fStatus = 'semua';

    let form   = null;     // data form yang sedang dibuka
    let errors = {};

    // ---------- ATURAN ----------
    const storeName = id => id ? STORES[id].name : 'Semua Store';

    const activeAdmins = () => users.filter(u => u.role === 'admin' && u.status === 'aktif');

    // manager aktif lain di store yang sama (1 store = 1 manager aktif)
    const otherManager = (store, exceptId) => users.find(u =>
        u.role === 'manager' && u.status === 'aktif' && u.store === store && u.id !== exceptId);

    // ---------- RENDER ----------
    function renderStats() {
        const active   = users.filter(u => u.status === 'aktif').length;
        const noManager = STORE_IDS.filter(id => !otherManager(id, 0));

        $('stTotal').textContent    = users.length;
        $('stTotalSub').textContent = ROLE_KEYS.map(r => users.filter(u => u.role === r).length + ' ' + r).join(' · ');
        $('stActive').textContent   = active;
        $('stInactive').textContent = users.length - active;
        $('stNoManager').textContent    = noManager.length;
        $('stNoManagerSub').textContent = noManager.length ? noManager.map(id => STORES[id].name).join(', ') : 'Semua store punya Manager';
    }

    function renderFilters() {
        $('usRole').innerHTML = '<option value="semua">Semua Role</option>' +
            ROLE_KEYS.map(r => `<option value="${r}" ${r === fRole ? 'selected' : ''}>${esc(ROLES[r])}</option>`).join('');

        $('usStore').innerHTML = '<option value="semua">Semua Store</option>' +
            STORE_IDS.map(id => `<option value="${id}" ${id === fStore ? 'selected' : ''}>${esc(STORES[id].name)}</option>`).join('');
    }

    function renderTable() {
        renderStats();

        const q = keyword.toLowerCase();

        const list = users.filter(u =>
            (fRole   === 'semua' || u.role === fRole) &&
            (fStore  === 'semua' || u.store === fStore) &&
            (fStatus === 'semua' || u.status === fStatus) &&
            (u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)));

        $('usMeta').textContent = list.length + ' pengguna';

        if (!list.length) {
            $('usBody').innerHTML = '<tr><td colspan="6"><div class="page-empty">Pengguna tidak ditemukan</div></td></tr>';
            return;
        }

        $('usBody').innerHTML = list.map(u => `
            <tr>
                <td>
                    <strong>${esc(u.name)}</strong><br>
                    <span style="color:var(--muted)">${esc(u.email)}</span>
                </td>
                <td><span class="status info">${esc(ROLES[u.role])}</span></td>
                <td>${u.store ? esc(storeName(u.store)) : '<span style="color:var(--muted)">Semua Store</span>'}</td>
                <td>${u.status === 'aktif'
                    ? '<span class="status success">Aktif</span>'
                    : '<span class="status danger">Nonaktif</span>'}</td>
                <td class="col-nowrap">${esc(u.last_login)}</td>
                <td>
                    <div class="receive-actions">
                        <button type="button" class="outline-button btn-sm" data-edit="${u.id}">Edit</button>
                        <button type="button" class="outline-button btn-sm" data-toggle="${u.id}">${u.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'}</button>
                    </div>
                </td>
            </tr>`).join('');
    }

    // ---------- NOTIFIKASI ----------
    function flash(msg, isError = false) {
        $('flashRoot').innerHTML = isError
            ? `<div class="info-note error" style="margin-bottom:13px">${msg}</div>`
            : `<div class="flash-success">${msg}</div>`;

        setTimeout(() => $('flashRoot').innerHTML = '', 4500);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ---------- MODAL ----------
    const openModal  = html => $('modalRoot').innerHTML = `<div class="modal-backdrop" id="modalBg">${html}</div>`;
    const closeModal = () => { $('modalRoot').innerHTML = ''; form = null; errors = {}; };

    const fieldError = key => errors[key] ? `<div class="form-error">${errors[key]}</div>` : '';
    const errClass   = key => errors[key] ? ' has-error' : '';

    function showForm() {
        const editing = form.id !== null;

        openModal(`
            <div class="modal-box wide">
                <h4>${editing ? 'Edit Pengguna' : 'Tambah Pengguna'}</h4>
                <p>${editing ? 'Ubah data akun. Kosongkan password bila tidak ingin mengubahnya.' : 'Buat akun baru. Pengguna login memakai email dan password ini.'}</p>

                <div class="waste-form">

                    <div class="form-field">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-input${errClass('name')}" data-f="name" value="${esc(form.name)}" placeholder="Contoh: Dewi Lestari">
                        ${fieldError('name')}
                    </div>

                    <div class="form-field">
                        <label class="form-label">Email (untuk login)</label>
                        <input type="email" class="form-input${errClass('email')}" data-f="email" value="${esc(form.email)}" placeholder="nama@kopsync.id">
                        ${fieldError('email')}
                    </div>

                    <div class="form-field">
                        <label class="form-label">Role</label>
                        <select class="form-input" data-f="role" id="fRoleSel">
                            ${ROLE_KEYS.map(r => `<option value="${r}" ${r === form.role ? 'selected' : ''}>${esc(ROLES[r])}</option>`).join('')}
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label">Store</label>
                        ${needsStore(form.role) ? `
                        <select class="form-input${errClass('store')}" data-f="store">
                            <option value="">Pilih store...</option>
                            ${STORE_IDS.map(id => `<option value="${id}" ${id === form.store ? 'selected' : ''}>${esc(STORES[id].name)}</option>`).join('')}
                        </select>
                        ${fieldError('store')}
                        <div class="form-hint">${form.role === 'manager' ? '1 Manager hanya untuk 1 store, dan 1 store hanya punya 1 Manager aktif.' : 'Terikat ke 1 store.'}</div>`
                        : `
                        <input type="text" class="form-input" value="Semua Store" readonly>
                        <div class="form-hint">${esc(ROLES[form.role])} dapat melihat semua store.</div>`}
                    </div>

                    <div class="form-field">
                        <label class="form-label">${editing ? 'Password Baru (opsional)' : 'Password Sementara'}</label>
                        <input type="password" class="form-input${errClass('password')}" data-f="password" value="${esc(form.password)}" placeholder="Minimal 8 karakter" autocomplete="new-password">
                        ${fieldError('password')}
                    </div>

                    <div class="form-field">
                        <label class="form-label">Status</label>
                        <div class="segmented">
                            <button type="button" data-status="aktif" class="${form.status === 'aktif' ? 'active' : ''}">Aktif</button>
                            <button type="button" data-status="nonaktif" class="${form.status === 'nonaktif' ? 'active' : ''}">Nonaktif</button>
                        </div>
                    </div>

                </div>

                <div class="modal-actions">
                    <button type="button" class="outline-button" data-close>Batal</button>
                    <button type="button" class="primary-button" id="btnSave">${editing ? 'Simpan Perubahan' : 'Tambah Pengguna'}</button>
                </div>
            </div>`);
    }

    function openForm(id = null) {
        const u = id === null ? null : users.find(x => x.id === id);

        form = u
            ? { id: u.id, name: u.name, email: u.email, role: u.role, store: u.store ?? '', status: u.status, password: '' }
            : { id: null, name: '', email: '', role: 'kasir', store: '', status: 'aktif', password: '' };

        errors = {};
        showForm();
    }

    // ---------- VALIDASI & SIMPAN ----------
    function validate() {
        const e = {};
        const name  = form.name.trim();
        const email = form.email.trim().toLowerCase();

        if (name.length < 3) e.name = 'Nama minimal 3 karakter.';

        if (!email) e.email = 'Email wajib diisi.';
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) e.email = 'Format email tidak valid.';
        else if (users.some(u => u.email.toLowerCase() === email && u.id !== form.id)) e.email = 'Email sudah dipakai pengguna lain.';

        if (needsStore(form.role)) {
            if (!form.store) e.store = 'Store wajib dipilih.';
            else if (form.role === 'manager' && form.status === 'aktif') {
                const other = otherManager(form.store, form.id);
                if (other) e.store = `${esc(storeName(form.store))} sudah punya Manager aktif (${esc(other.name)}).`;
            }
        }

        if (form.id === null && !form.password) e.password = 'Password wajib diisi.';
        else if (form.password && form.password.length < 8) e.password = 'Password minimal 8 karakter.';

        // minimal harus ada 1 admin aktif
        if (form.id !== null) {
            const old = users.find(u => u.id === form.id);
            const stillActiveAdmin = form.role === 'admin' && form.status === 'aktif';
            if (old.role === 'admin' && old.status === 'aktif' && !stillActiveAdmin && activeAdmins().length === 1) {
                e.name = 'Ini satu-satunya Admin aktif, jadi role dan statusnya tidak bisa diubah.';
            }
        }

        return e;
    }

    function saveUser() {
        errors = validate();

        if (Object.keys(errors).length) {
            showForm();
            return;
        }

        const data = {
            name: form.name.trim(),
            email: form.email.trim().toLowerCase(),
            role: form.role,
            store: needsStore(form.role) ? form.store : null,
            status: form.status,
        };

        let msg;

        if (form.id === null) {
            users.push({ id: nextId++, ...data, last_login: '-' });
            msg = `Pengguna ${esc(data.name)} berhasil ditambahkan.`;
        } else {
            Object.assign(users.find(u => u.id === form.id), data);
            msg = `Data ${esc(data.name)} berhasil diperbarui${form.password ? ' dan password diubah' : ''}.`;
        }

        closeModal();
        renderTable();
        flash(msg);
    }

    // ---------- AKTIF / NONAKTIF ----------
    function askToggle(id) {
        const u = users.find(x => x.id === id);

        if (u.status === 'aktif') {
            if (u.role === 'admin' && activeAdmins().length === 1) {
                return flash('Tidak bisa menonaktifkan satu-satunya Admin aktif.', true);
            }

            openModal(`
                <div class="modal-box">
                    <h4>Nonaktifkan Pengguna</h4>
                    <p>${esc(u.name)} (${esc(ROLES[u.role])}) tidak akan bisa login sampai diaktifkan kembali. Data dan riwayat aktivitasnya tetap tersimpan.</p>
                    <div class="modal-actions">
                        <button type="button" class="outline-button" data-close>Batal</button>
                        <button type="button" class="primary-button" data-confirm-off="${u.id}">Nonaktifkan</button>
                    </div>
                </div>`);
            return;
        }

        // mengaktifkan manager: cek aturan 1 store = 1 manager aktif
        if (u.role === 'manager') {
            const other = otherManager(u.store, u.id);
            if (other) {
                return flash(`${esc(storeName(u.store))} sudah punya Manager aktif (${esc(other.name)}). Nonaktifkan dulu Manager tersebut.`, true);
            }
        }

        u.status = 'aktif';
        renderTable();
        flash(`${esc(u.name)} diaktifkan kembali.`);
    }

    function confirmOff(id) {
        const u = users.find(x => x.id === id);
        u.status = 'nonaktif';
        closeModal();
        renderTable();
        flash(`${esc(u.name)} dinonaktifkan.`);
    }

    // ---------- EVENT ----------
    document.addEventListener('click', e => {
        const t = e.target;

        if (t.closest('[data-edit]'))        return openForm(Number(t.closest('[data-edit]').dataset.edit));
        if (t.closest('[data-toggle]'))      return askToggle(Number(t.closest('[data-toggle]').dataset.toggle));
        if (t.closest('[data-confirm-off]')) return confirmOff(Number(t.closest('[data-confirm-off]').dataset.confirmOff));
        if (t.closest('[data-status]'))      { form.status = t.closest('[data-status]').dataset.status; delete errors.store; return showForm(); }
        if (t.closest('[data-close]') || t.id === 'modalBg') return closeModal();
        if (t.id === 'btnAdd')               return openForm();
        if (t.id === 'btnSave')              return saveUser();
    });

    document.addEventListener('input', e => {
        const t = e.target;

        if (t.id === 'usSearch') { keyword = t.value; renderTable(); }

        if (t.dataset.f && form && t.tagName === 'INPUT') {
            form[t.dataset.f] = t.value;
        }
    });

    document.addEventListener('change', e => {
        const t = e.target;

        if (t.id === 'usRole')   { fRole   = t.value; renderTable(); }
        if (t.id === 'usStore')  { fStore  = t.value; renderTable(); }
        if (t.id === 'usStatus') { fStatus = t.value; renderTable(); }

        if (t.dataset.f && form && t.tagName === 'SELECT') {
            form[t.dataset.f] = t.value;

            if (t.dataset.f === 'role') {
                if (!needsStore(form.role)) form.store = '';
                delete errors.store;
                showForm();                    // tampilan field store berubah mengikuti role
            }
        }
    });

    renderFilters();
    renderTable();

})();
</script>

@endsection