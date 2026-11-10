const ICONS = {
    pesanan_masuk: '↻',
    surat_jalan: '▱',
    restock: '↻',
    waste: '△',
    stok_menipis: '△',
    approval: '✓',
};

const DANGER_TYPES = ['waste', 'stok_menipis', 'restock'];

export default function notificationBell(config) {
    return {
        urls: config.urls,
        pollMs: config.pollMs ?? 30000,

        unread: config.unread ?? 0,
        latestId: 0,

        open: false,
        loading: false,
        loadingMore: false,
        loadError: false,
        actionError: '',
        busyId: null,
        busyAll: false,

        items: [],
        hasMore: false,
        pagesLoaded: 0,

        now: Date.now(),
        pollTimer: null,
        scrollTimer: null,
        redirecting: false,
        visibilityHandler: null,

        /* ---------- siklus hidup ---------- */

        init() {
            this.poll();
            this.startTimer();

            // Polling hanya berjalan saat tab terlihat.
            this.visibilityHandler = () => {
                if (document.visibilityState === 'visible') {
                    this.poll();
                    this.startTimer();
                } else {
                    this.stopTimer();
                }
            };
            document.addEventListener('visibilitychange', this.visibilityHandler);
        },

        destroy() {
            this.stopTimer();
            clearTimeout(this.scrollTimer);
            document.removeEventListener('visibilitychange', this.visibilityHandler);
        },

        startTimer() {
            if (this.pollTimer) return;
            this.pollTimer = setInterval(() => this.poll(), this.pollMs);
        },

        stopTimer() {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        },

        /* ---------- tampilan ---------- */

        get badgeText() {
            return this.unread > 99 ? '99+' : String(this.unread);
        },

        icon(type) {
            return ICONS[type] ?? '•';
        },

        isDanger(type) {
            return DANGER_TYPES.includes(type);
        },

        timeAgo(iso) {
            if (!iso) return '';

            const seconds = Math.max(0, Math.floor((this.now - new Date(iso).getTime()) / 1000));
            if (seconds < 60) return 'Baru saja';

            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return minutes + ' menit lalu';

            const hours = Math.floor(minutes / 60);
            if (hours < 24) return hours + ' jam lalu';

            const days = Math.floor(hours / 24);
            if (days < 7) return days + ' hari lalu';

            return new Date(iso).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        },

        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.now = Date.now();
                this.actionError = '';
                this.loadList();
            }
        },

        /* ---------- request ---------- */

        async request(url, method = 'GET') {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            });

            // Sesi habis: hentikan polling lalu muat ulang (otomatis ke halaman login).
            if (response.status === 401 || response.status === 419) {
                if (!this.redirecting) {
                    this.redirecting = true;
                    this.stopTimer();
                    window.location.reload();
                }
                throw new Error('session');
            }

            if (!response.ok) {
                const error = new Error('http');
                error.status = response.status;
                throw error;
            }

            return response.json();
        },

        /* ---------- polling ---------- */

        async poll() {
            if (document.visibilityState !== 'visible' || this.redirecting) return;

            try {
                const data = await this.request(this.urls.count);
                const changed = data.unread_count !== this.unread || data.latest_id !== this.latestId;

                this.now = Date.now();
                this.unread = data.unread_count;
                this.latestId = data.latest_id ?? 0;

                // Jika dropdown sedang terbuka dan ada perubahan, segarkan daftarnya.
                if (changed && this.open) {
                    await this.loadList();
                }
            } catch (e) {
                // Gagal sesaat (jaringan putus, dsb.): coba lagi di polling berikutnya.
            }
        },

        /* ---------- daftar ---------- */

        mergeItems(fresh) {
            const byId = new Map(this.items.map((item) => [item.id, item]));
            fresh.forEach((item) => byId.set(item.id, item));
            this.items = [...byId.values()].sort((a, b) => b.id - a.id);
        },

        async loadList() {
            if (this.loading) return;

            this.loading = this.items.length === 0;
            this.loadError = false;

            try {
                const data = await this.request(this.urls.list);

                this.unread = data.unread_count;
                this.mergeItems(data.items);

                // Jangan menimpa status "masih ada halaman lama" jika user sudah memuat halaman berikutnya.
                if (this.pagesLoaded <= 1) {
                    this.hasMore = data.has_more;
                    this.pagesLoaded = 1;
                }
            } catch (e) {
                if (e.message !== 'session') this.loadError = true;
            } finally {
                this.loading = false;
            }
        },

        async loadMore() {
            if (!this.hasMore || this.loadingMore || this.items.length === 0) return;

            this.loadingMore = true;

            try {
                const lastId = this.items[this.items.length - 1].id;
                const data = await this.request(this.urls.list + '?before=' + lastId);

                this.mergeItems(data.items);
                this.hasMore = data.has_more;
                this.pagesLoaded += 1;
            } catch (e) {
                // Dibiarkan: user bisa scroll lagi untuk mencoba ulang.
            } finally {
                this.loadingMore = false;
            }
        },

        /* ---------- scroll (auto-hide scrollbar + muat lebih banyak) ---------- */

        onScroll(el) {
            el.classList.add('is-scrolling');

            clearTimeout(this.scrollTimer);
            this.scrollTimer = setTimeout(() => el.classList.remove('is-scrolling'), 900);

            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 80) {
                this.loadMore();
            }
        },

        /* ---------- aksi ---------- */

        async openItem(item) {
            if (this.busyId !== null) return;

            this.busyId = item.id;
            this.actionError = '';

            try {
                // Badge baru dikurangi setelah server berhasil menyimpan status baca.
                const data = await this.request(this.urls.list + '/' + item.id + '/read', 'POST');

                item.is_read = true;
                this.unread = data.unread_count;

                window.location.href = data.redirect_url;
            } catch (e) {
                this.busyId = null;

                if (e.message !== 'session') {
                    this.actionError = 'Notifikasi tidak dapat dibuka. Daftar akan disegarkan.';
                    this.loadList();
                }
            }
        },

        async markAllRead() {
            if (this.unread === 0 || this.busyAll) return;

            this.busyAll = true;
            this.actionError = '';

            try {
                const data = await this.request(this.urls.readAll, 'POST');

                this.items.forEach((item) => {
                    item.is_read = true;
                });
                this.unread = data.unread_count;
            } catch (e) {
                if (e.message !== 'session') {
                    this.actionError = 'Gagal menandai semua notifikasi. Coba lagi.';
                }
            } finally {
                this.busyAll = false;
            }
        },
    };
}