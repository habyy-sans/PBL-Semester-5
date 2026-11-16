// Scrollbar auto-hide: scrollbar hanya terlihat saat di-scroll.
// Memasang class .is-scrolling pada elemen yang sedang di-scroll,
// dilepas 0,9 detik setelah berhenti (aturan CSS ada di ui-refinement.css).
const timers = new WeakMap();

document.addEventListener(
    'scroll',
    (event) => {
        const el = event.target === document ? document.documentElement : event.target;
        if (!(el instanceof Element)) return;

        el.classList.add('is-scrolling');

        clearTimeout(timers.get(el));
        timers.set(el, setTimeout(() => el.classList.remove('is-scrolling'), 900));
    },
    true // capture: event scroll tidak "bubble", jadi harus ditangkap di fase capture
);
