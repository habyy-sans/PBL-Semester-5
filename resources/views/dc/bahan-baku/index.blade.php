
@extends('layouts.app')

@section('title', 'Kelola Bahan Baku')
@section('description', 'Kelola data bahan baku dan persediaan DC.')

@php
    $bahanData = $bahanList->map(function ($bahan) {
        $stok = $bahan->stokDc->first();

        return [
            'id' => $bahan->bahan_id,
            'nama' => $bahan->nama_bahan,
            'kategori' => $bahan->kategori,
            'satuan' => $bahan->satuan,
            'kemasan' => $bahan->nama_kemasan,
            'isi' => (float) $bahan->isi_per_kemasan,
            'harga' => (float) $bahan->harga_beli,
            'stok' => $stok ? (float) $stok->jumlah_stok : null,
            'minimum' => $stok ? (float) $stok->stok_minimum : null,
        ];
    })->values();
@endphp

@section('content')
<style>
    /* =========================================================
       HALAMAN KELOLA BAHAN BAKU — CSS LOKAL
       ========================================================= */

    .bahan-baku-page {
        --bb-brown: #934510;
        --bb-brown-dark: #71350d;
        --bb-border: #ead8c3;
        width: 100%;
        max-width: none;
        min-width: 0;
        padding: 12px 16px 32px;
        color: #3f2b20;
    }

    .bahan-baku-page,
    .bahan-baku-page * {
        box-sizing: border-box;
    }

    /* =========================================================
       SUMMARY CARDS — ELEGANT REFINEMENT ONLY
       Tidak menyentuh container daftar maupun modal/form.
       ========================================================= */
    .bahan-baku-page .bb-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 22px;
        margin: 22px 0 28px;
        align-items: stretch;
    }

    .bahan-baku-page .bb-summary-card {
        position: relative;
        isolation: isolate;
        min-width: 0;
        min-height: 164px;
        padding: 25px 27px;
        overflow: hidden;
        border: 1px solid rgba(234, 216, 195, .9);
        border-radius: 20px;
        background: linear-gradient(145deg, #ffffff 0%, #fffdf9 100%);
        box-shadow:
            0 2px 5px rgba(63, 43, 32, .025),
            0 12px 28px rgba(63, 43, 32, .045);
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }

    .bahan-baku-page .bb-summary-card::before {
        content: "";
        position: absolute;
        z-index: -1;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #934510, #d7a36d);
        opacity: .9;
    }

    .bahan-baku-page .bb-summary-card:nth-child(2)::before {
        background: linear-gradient(90deg, #c2410c, #fdba74);
    }

    .bahan-baku-page .bb-summary-card:nth-child(3)::before {
        background: linear-gradient(90deg, #15803d, #86efac);
    }

    .bahan-baku-page .bb-summary-card:hover {
        transform: translateY(-3px);
        border-color: #e4c6a5;
        box-shadow:
            0 4px 8px rgba(63, 43, 32, .035),
            0 18px 34px rgba(63, 43, 32, .075);
    }

    .bahan-baku-page .bb-summary-card > div {
        min-height: 112px;
        gap: 18px;
    }

    .bahan-baku-page .bb-summary-card > div > div {
        min-width: 0;
    }

    .bahan-baku-page .bb-summary-card > div > div > p:first-child {
        margin: 0;
        color: #8b7563;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.5;
        letter-spacing: .01em;
    }

    .bahan-baku-page .bb-summary-card > div > div > p:nth-child(2) {
        margin-top: 8px;
        font-size: clamp(32px, 2.2vw, 40px);
        font-weight: 750;
        line-height: 1;
        letter-spacing: -.045em;
        font-variant-numeric: tabular-nums;
    }

    .bahan-baku-page .bb-summary-card > div > div > p:nth-child(3) {
        margin-top: 9px;
        color: #a08d7d;
        font-size: 12px;
        line-height: 1.55;
    }

    .bahan-baku-page .bb-summary-card > div > span {
        display: inline-flex;
        flex: 0 0 44px;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        padding: 0 !important;
        border: 1px solid #f0e3d2;
        border-radius: 14px !important;
        background: #fbf3e7 !important;
        color: #934510;
        font-size: 19px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.85);
    }

    .bahan-baku-page .bb-summary-card:nth-child(2) > div > span {
        border-color: #ffead8;
        background: #fff4e9 !important;
        color: #c2410c;
    }

    .bahan-baku-page .bb-summary-card:nth-child(3) > div > span {
        border-color: #d9f3e2;
        background: #effbf2 !important;
        color: #15803d;
    }

    @media (max-width: 1100px) {
        .bahan-baku-page .bb-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
    }

    @media (max-width: 640px) {
        .bahan-baku-page .bb-summary-grid {
            grid-template-columns: minmax(0, 1fr);
            gap: 12px;
            margin-top: 14px;
            margin-bottom: 20px;
        }

        .bahan-baku-page .bb-summary-card {
            min-height: 0;
            padding: 21px;
            border-radius: 16px;
        }

        .bahan-baku-page .bb-summary-card > div {
            min-height: 0;
        }

        .bahan-baku-page .bb-summary-card > div > div > p:nth-child(2) {
            font-size: 34px;
        }
    }

    /* =========================================================
       DAFTAR BAHAN BAKU — DESAIN CONTAINER
       Fokus hanya pada daftar, filter, tabel, dan pagination.
       CSS modal/form tambah, edit, dan tambah stok tetap terpisah.
       ========================================================= */

    .bahan-baku-page .bb-list-card {
        width: 100%;
        max-width: none;
        min-width: 0;
        margin: 28px 0 0;
        overflow: hidden;
        border: 1px solid #ead8c3;
        border-radius: 18px;
        background: #fffdf9;
        box-shadow: 0 8px 26px rgba(74, 44, 27, .055);
    }

    /* Header daftar + tombol reset */
    .bahan-baku-page .bb-list-card > div:first-child {
        padding: 24px 26px 22px !important;
        border-bottom: 1px solid #f0e5d8 !important;
        background: linear-gradient(180deg, #fffdf9 0%, #ffffff 100%);
    }

    .bahan-baku-page .bb-list-card h3 {
        margin: 0;
        color: #3f2b20;
        font-size: 20px;
        font-weight: 700;
        line-height: 1.35;
        letter-spacing: -.02em;
    }

    .bahan-baku-page .bb-list-card > div:first-child p {
        color: #8a7462;
        font-size: 13px;
        line-height: 1.6;
    }

    .bahan-baku-page .bb-list-card > div:first-child > div:first-child button,
    .bahan-baku-page .bb-list-card button[ @click="resetFilters()" ] {
        min-height: 38px;
        padding: 9px 14px !important;
        border: 1px solid #e7d6c3 !important;
        border-radius: 10px !important;
        background: #fff !important;
        color: #765239 !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        white-space: nowrap;
        transition: background .18s ease, border-color .18s ease;
    }

    .bahan-baku-page .bb-list-card button:hover {
        border-color: #c99a70;
    }

    /* Filter: pencarian penuh di baris pertama, dua filter di baris kedua */
    .bahan-baku-page .bb-list-card > div:first-child > div:last-child {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px !important;
        margin-top: 22px !important;
        align-items: end;
    }

    .bahan-baku-page .bb-list-card > div:first-child > div:last-child > div {
        min-width: 0;
    }

    .bahan-baku-page .bb-list-card > div:first-child > div:last-child > div:first-child {
        grid-column: 1 / -1;
    }

    .bahan-baku-page .bb-list-card label[for="searchBahan"],
    .bahan-baku-page .bb-list-card label[for="filterKategori"],
    .bahan-baku-page .bb-list-card label[for="filterStatus"],
    .bahan-baku-page .bb-list-card label[for="sortBahan"],
    .bahan-baku-page .bb-list-card label[for="perPage"] {
        display: block;
        margin-bottom: 7px !important;
        color: #765b45;
        font-size: 12px !important;
        font-weight: 600;
        line-height: 1.4;
    }

    .bahan-baku-page .bb-list-card .relative {
        min-width: 0;
    }

    .bahan-baku-page .bb-list-card .bb-input,
    .bahan-baku-page .bb-list-card .bb-select {
        width: 100%;
        min-width: 0;
        min-height: 44px;
        padding: 10px 13px !important;
        border: 1px solid #e6d8c9 !important;
        border-radius: 11px !important;
        background-color: #fff !important;
        color: #493426 !important;
        font-family: inherit;
        font-size: 13px !important;
        line-height: 1.4;
        box-shadow: none !important;
    }

    .bahan-baku-page .bb-list-card .bb-input {
        padding-left: 38px !important;
    }

    .bahan-baku-page .bb-list-card .bb-input::placeholder {
        color: #b19b89;
    }

    .bahan-baku-page .bb-list-card .bb-input:focus,
    .bahan-baku-page .bb-list-card .bb-select:focus {
        outline: none;
        border-color: #a45b26 !important;
        box-shadow: 0 0 0 3px rgba(147, 69, 16, .10) !important;
    }

    /* Baris jumlah hasil dan pengurutan */
    .bahan-baku-page .bb-list-card > div:nth-child(2) {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 16px 26px !important;
        border-bottom: 1px solid #eee2d5 !important;
        background: #fcf8f2;
    }

    .bahan-baku-page .bb-list-card > div:nth-child(2) p {
        margin: 0;
        color: #8a7462;
        font-size: 13px;
        line-height: 1.6;
    }

    .bahan-baku-page .bb-list-card > div:nth-child(2) p strong {
        color: #3f2b20;
        font-weight: 700;
    }

    .bahan-baku-page .bb-list-card > div:nth-child(2) > div {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .bahan-baku-page .bb-list-card #sortBahan {
        width: 190px;
        min-width: 150px;
    }

    /* Tabel */
    .bahan-baku-page .bb-table-scroll {
        width: 100%;
        min-width: 0;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        -webkit-overflow-scrolling: touch;
        background: #fffdf9;
    }

    .bahan-baku-page .bb-table {
        width: 100%;
        min-width: 1120px;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: auto;
        text-align: left;
    }

    .bahan-baku-page .bb-table thead th {
        padding: 16px 16px;
        border-bottom: 1px solid #ead8c3;
        background: #f7eee2;
        color: #76583f;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.5;
        letter-spacing: .035em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .bahan-baku-page .bb-table tbody td {
        padding: 18px 16px;
        border-bottom: 1px solid #f0e7dd;
        color: #634a37;
        font-size: 12px;
        line-height: 1.55;
        vertical-align: middle;
        white-space: nowrap;
    }

    .bahan-baku-page .bb-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .bahan-baku-page .bb-table tbody tr {
        transition: background .15s ease;
    }

    .bahan-baku-page .bb-table tbody tr:hover {
        background: #fdf8f0;
    }

    .bahan-baku-page .bb-table th:first-child,
    .bahan-baku-page .bb-table td:first-child {
        padding-left: 24px;
        width: 64px;
    }

    .bahan-baku-page .bb-table th:last-child,
    .bahan-baku-page .bb-table td:last-child {
        min-width: 168px;
        padding-right: 24px;
        white-space: nowrap;
    }

    .bahan-baku-page .bb-table td:last-child > div {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Tombol aksi tabel */
    .bahan-baku-page .bb-table .bb-action-button {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 9px 13px !important;
        border-radius: 10px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        white-space: nowrap;
        box-shadow: none !important;
    }

    /* Pagination */
    .bahan-baku-page .bb-list-card > div:last-child {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 26px !important;
        border-top: 1px solid #eee2d5 !important;
        background: #fffdf9;
    }

    .bahan-baku-page .bb-list-card > div:last-child > div {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .bahan-baku-page .bb-list-card > div:last-child button {
        min-height: 38px;
        padding: 8px 13px !important;
        border: 1px solid #e7d8c8 !important;
        border-radius: 10px !important;
        background: #fff !important;
        color: #765239 !important;
        font-size: 12px !important;
        font-weight: 600;
    }

    .bahan-baku-page .bb-list-card > div:last-child button:hover:not(:disabled) {
        background: #faf2e8 !important;
        border-color: #c99a70 !important;
    }

    .bahan-baku-page .bb-list-card > div:last-child button:disabled {
        opacity: .42;
        cursor: not-allowed;
    }

    .bahan-baku-page .bb-list-card #perPage {
        width: 82px;
        min-height: 38px;
    }

    /* Tablet */
    @media (max-width: 1100px) {
        .bahan-baku-page .bb-list-card > div:first-child > div:last-child {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .bahan-baku-page .bb-list-card > div:first-child > div:last-child > div:first-child {
            grid-column: 1 / -1;
        }
    }

    /* Ponsel */
    @media (max-width: 640px) {
        .bahan-baku-page .bb-list-card {
            margin-top: 20px;
            border-radius: 14px;
        }

        .bahan-baku-page .bb-list-card > div:first-child {
            padding: 18px 16px !important;
        }

        .bahan-baku-page .bb-list-card h3 {
            font-size: 18px;
        }

        .bahan-baku-page .bb-list-card > div:first-child > div:last-child {
            grid-template-columns: minmax(0, 1fr);
            gap: 13px !important;
            margin-top: 18px !important;
        }

        .bahan-baku-page .bb-list-card > div:first-child > div:last-child > div:first-child {
            grid-column: auto;
        }

        .bahan-baku-page .bb-list-card > div:nth-child(2),
        .bahan-baku-page .bb-list-card > div:last-child {
            padding: 15px 16px !important;
        }

        .bahan-baku-page .bb-list-card > div:nth-child(2) > div {
            width: 100%;
        }

        .bahan-baku-page .bb-list-card #sortBahan {
            flex: 1;
            width: auto;
        }

        .bahan-baku-page .bb-list-card > div:last-child > div {
            flex-wrap: wrap;
        }

        .bahan-baku-page .bb-list-card > div:last-child > div:last-child {
            width: 100%;
            justify-content: space-between;
        }

        .bahan-baku-page .bb-table {
            min-width: 1080px;
        }
    }

    /* Tombol */
    .bahan-baku-page button {
        font-family: inherit;
        line-height: 1.35;
        text-decoration: none !important;
    }

    .bahan-baku-page .bb-action-button {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 36px;
        padding: 8px 12px !important;
        border-radius: 9px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        white-space: nowrap;
        text-decoration: none !important;
        box-shadow: none;
    }

    .bahan-baku-page .bb-action-edit {
        color: #69452d !important;
        background: #fff !important;
        border: 1px solid #e5d5c4 !important;
    }

    .bahan-baku-page .bb-action-edit:hover {
        background: #faf3e9 !important;
        border-color: #b87948 !important;
    }

    .bahan-baku-page .bb-action-stock,
    .bahan-baku-page .bb-primary-button {
        color: #fff !important;
        background: var(--bb-brown) !important;
        border: 1px solid var(--bb-brown) !important;
    }

    .bahan-baku-page .bb-action-stock:hover,
    .bahan-baku-page .bb-primary-button:hover {
        color: #fff !important;
        background: var(--bb-brown-dark) !important;
    }

    /* Input halaman dan filter */
    .bahan-baku-page .bb-input,
    .bahan-baku-page .bb-select {
        display: block;
        width: 100%;
        min-width: 0;
        min-height: 42px;
        padding: 10px 12px !important;
        color: #3f2b20 !important;
        background-color: #fff !important;
        border: 1px solid #e5d8ca !important;
        border-radius: 10px !important;
        font-size: 13px !important;
        line-height: 1.4;
        box-shadow: none;
    }

    .bahan-baku-page .bb-input:focus,
    .bahan-baku-page .bb-select:focus {
        outline: none;
        border-color: #a45b26 !important;
        box-shadow: 0 0 0 3px rgba(147, 69, 16, .12) !important;
    }

    /* =========================================================
       MODAL: ukuran dan area scroll
       ========================================================= */

    .bahan-baku-page .bb-modal-panel {
        width: min(760px, calc(100vw - 32px));
        max-width: 760px;
        max-height: calc(100dvh - 32px);
        margin: auto;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 24px 70px rgba(35, 24, 17, .24);
    }

    /* Header tidak sticky supaya tidak menimpa label saat scroll */
    .bahan-baku-page .bb-modal-heading {
        position: relative;
        top: auto;
        z-index: 1;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding: 24px 28px 20px !important;
        border-bottom: 1px solid #f0e5d8;
        background: #fff;
    }

    .bahan-baku-page .bb-modal-heading > div {
        min-width: 0;
    }

    .bahan-baku-page .bb-modal-heading h3 {
        line-height: 1.3;
    }

    /* Struktur form modal */
    .bahan-baku-page .bb-modal-panel > form {
        display: flex;
        flex-direction: column;
        gap: 22px !important;
        padding: 24px 28px 28px !important;
    }

    .bahan-baku-page .bb-modal-panel form > .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 24px !important;
        row-gap: 22px !important;
        min-width: 0;
    }

    .bahan-baku-page .bb-modal-panel form label {
        display: block;
        min-width: 0;
        line-height: 1.5;
    }

    .bahan-baku-page .bb-modal-panel form label > span:first-child {
        display: block;
        margin-bottom: 8px;
    }

    .bahan-baku-page .bb-modal-panel form input,
    .bahan-baku-page .bb-modal-panel form select {
        display: block;
        width: 100%;
        min-width: 0;
        min-height: 46px;
        margin-top: 0;
        padding: 11px 14px !important;
        border: 1px solid #e5d8ca !important;
        border-radius: 10px !important;
        background: #fff !important;
        color: #3f2b20 !important;
        font-family: inherit;
        font-size: 14px !important;
        line-height: 1.5;
        box-shadow: none;
    }

    .bahan-baku-page .bb-modal-panel form input:focus,
    .bahan-baku-page .bb-modal-panel form select:focus {
        outline: none;
        border-color: #a45b26 !important;
        box-shadow: 0 0 0 3px rgba(147, 69, 16, .12) !important;
    }

    /* Label full-width tetap melebar pada desktop */
    .bahan-baku-page .bb-modal-panel form > .grid > label.sm\:col-span-2 {
        grid-column: 1 / -1;
    }

    /* Blok stok awal dan ringkasan stok */
    .bahan-baku-page .bb-modal-panel form > .rounded-xl.border {
        min-width: 0;
        padding: 20px !important;
    }

    .bahan-baku-page .bb-modal-panel form > .rounded-xl.bg-stone-50 {
        min-width: 0;
        padding: 18px !important;
        line-height: 1.6;
    }

    .bahan-baku-page .bb-modal-panel form > .rounded-xl.border-blue-100 {
        padding: 14px 16px !important;
        line-height: 1.65;
    }

    .bahan-baku-page .bb-modal-panel form .grid label span {
        overflow-wrap: anywhere;
    }

    /* Footer tombol modal */
    .bahan-baku-page .bb-modal-panel form > .bb-modal-footer,
    .bahan-baku-page .bb-modal-panel form > div:last-child {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 12px !important;
        margin-top: 2px;
        padding-top: 20px !important;
        border-top: 1px solid #f0e5d8;
    }

    .bahan-baku-page .bb-modal-footer button,
    .bahan-baku-page .bb-modal-panel form > div:last-child button {
        min-height: 42px;
        padding: 10px 18px !important;
        border-radius: 9px !important;
        white-space: nowrap;
    }

    /* Responsif tablet */
    @media (max-width: 1100px) {
    }

    /* Responsif ponsel */
    @media (max-width: 640px) {
        .bahan-baku-page {
            padding: 4px 0 20px;
        }

        .bahan-baku-page .bb-modal-panel {
            width: calc(100vw - 24px);
            max-height: calc(100dvh - 24px);
            border-radius: 15px;
        }

        .bahan-baku-page .bb-modal-heading {
            gap: 12px;
            padding: 20px 18px 16px !important;
        }

        .bahan-baku-page .bb-modal-panel > form {
            gap: 18px !important;
            padding: 18px !important;
        }

        .bahan-baku-page .bb-modal-panel form > .grid {
            grid-template-columns: minmax(0, 1fr);
            gap: 18px !important;
        }

        .bahan-baku-page .bb-modal-panel form > .grid > label.sm\:col-span-2 {
            grid-column: auto;
        }

        .bahan-baku-page .bb-modal-panel form > .rounded-xl.border {
            padding: 15px !important;
        }

        .bahan-baku-page .bb-modal-panel form > div:last-child {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .bahan-baku-page .bb-modal-panel form > div:last-child button {
            width: 100%;
        }
    }

    
/* ===== Perapian khusus modal Tambah Stok ===== */

/* Panel modal */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) {
    width: calc(100% - 32px) !important;
    max-width: 620px !important;
    max-height: calc(100vh - 40px) !important;
    margin: auto;
    overflow-x: hidden;
    overflow-y: auto;
    border-radius: 20px !important;
}

/* Header agar konsisten dengan modal lain */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > div:first-child {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 24px 28px 18px !important;
    border-bottom: 1px solid #f0e5d8;
    background: #fff;
}

/* Tombol tutup */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > div:first-child > button {
    flex-shrink: 0;
    margin: -4px -8px 0 0;
}

/* Isi form */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form {
    display: flex;
    flex-direction: column;
    gap: 22px !important;
    padding: 24px 28px 26px !important;
}

/* Kartu informasi bahan dan stok */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > div:first-child {
    margin: 0;
    padding: 20px 22px !important;
    border: 1px solid #eee6dd;
    border-radius: 14px;
    background: #faf9f7;
}

/* Nama bahan */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > div:first-child > p:nth-child(2) {
    margin-top: 6px;
    font-size: 18px;
    line-height: 1.4;
}

/* ID bahan */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > div:first-child > p:nth-child(3) {
    margin-top: 5px;
}

/* Informasi stok saat ini dan minimum */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > div:first-child > div {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-top: 12px;
}

/* Label input jumlah */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > label {
    display: block;
    margin: 0;
    line-height: 1.5;
}

/* Input jumlah stok */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > label input {
    display: block;
    width: 100%;
    min-width: 0;
    min-height: 48px !important;
    margin-top: 8px;
    padding: 12px 14px !important;
    border: 1px solid #e5d8ca !important;
    border-radius: 11px !important;
    background: #fff !important;
    font-size: 14px !important;
    line-height: 1.5;
}

/* Keterangan di bawah input */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > label > p {
    margin-top: 8px;
    line-height: 1.5;
}

/* Footer tombol */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > div:last-child {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin: 0;
    padding-top: 20px;
    border-top: 1px solid #f0e5d8;
}

/* Ukuran tombol */
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) .bb-modal-footer button,
.bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > div:last-child button {
    min-height: 44px;
    padding: 10px 18px !important;
    border-radius: 10px !important;
    font-size: 14px !important;
}

/* Layar kecil */
@media (max-width: 640px) {
    .bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) {
        width: calc(100% - 20px) !important;
        max-height: calc(100vh - 24px) !important;
    }

    .bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > div:first-child {
        padding: 20px 18px 16px !important;
    }

    .bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form {
        gap: 18px !important;
        padding: 18px !important;
    }

    .bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > div:first-child {
        padding: 16px !important;
    }

    .bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > div:first-child > div {
        gap: 12px;
    }

    .bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > div:last-child {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .bahan-baku-page .bb-modal-panel:has(form[action*="tambah-stok"]) > form > div:last-child button {
        width: 100%;
    }
}

</style>

<div
    class="bahan-baku-page space-y-6 pb-8"
    x-data="bahanBakuPage(@js($bahanData))"
    x-cloak
>
    {{-- Notifikasi --}}
    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <strong>Berhasil.</strong> {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Data belum berhasil diproses.</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Header halaman --}}
    <div class="flex flex-col gap-5 pb-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-medium text-amber-800">MASTER DATA DC</p>
            <h2 class="mt-1 text-2xl font-bold text-stone-800">
                Kelola Bahan Baku
            </h2>
            <p class="mt-1 text-sm text-stone-500">
                Kelola informasi bahan, harga pasok, dan ketersediaan stok DC.
            </p>
        </div>

        <button
            type="button"
            @click="openCreate()"
            class="bb-primary-button inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition"
            span class="text-lg">+</span>
            Tambah Bahan Baku
        </button>
    </div>

    {{-- Kartu ringkasan --}}
    <div class="bb-summary-grid">
        <div class="bb-summary-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-stone-500">Jenis Bahan Baku</p>
                    <p class="mt-2 text-3xl font-bold text-stone-800"
                       x-text="items.length"></p>
                    <p class="mt-1 text-xs text-stone-400">Terdaftar di sistem</p>
                </div>
                <span class="rounded-xl bg-amber-50 p-3 text-xl">▦</span>
            </div>
        </div>

        <div class="bb-summary-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-stone-500">Stok Perlu Diperiksa</p>
                    <p class="mt-2 text-3xl font-bold text-orange-700"
                       x-text="items.filter(i => i.stok === null || i.stok <= i.minimum).length"></p>
                    <p class="mt-1 text-xs text-stone-400">
                        Stok kosong atau mencapai batas minimum
                    </p>
                </div>
                <span class="rounded-xl bg-orange-50 p-3 text-xl">!</span>
            </div>
        </div>

        <div class="bb-summary-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-stone-500">Stok Dalam Kondisi Aman</p>
                    <p class="mt-2 text-3xl font-bold text-green-700"
                       x-text="items.filter(i => i.stok !== null && i.stok > i.minimum).length"></p>
                    <p class="mt-1 text-xs text-stone-400">
                        Stok di atas batas minimum
                    </p>
                </div>
                <span class="rounded-xl bg-green-50 p-3 text-xl">✓</span>
            </div>
        </div>
    </div>

    {{-- Tabel dan kontrol --}}
    <div class="bb-list-card">
        <div class="border-b border-stone-100 px-6 py-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-stone-800">Daftar Bahan Baku</h3>
                    <p class="mt-1 text-sm text-stone-500">
                        Cari, filter, dan periksa stok bahan baku DC.
                    </p>
                </div>

                <button
                    type="button"
                    @click="resetFilters()"
                    class="self-start rounded-lg border border-stone-200 px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-50 xl:self-auto"
                >
                    Reset filter
                </button>
            </div>

            <div class="bb-filter-grid mt-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
                <div class="bb-search-filter">
                    <label for="searchBahan" class="mb-1.5 block text-xs font-semibold text-stone-600">
                        Pencarian
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">⌕</span>
                        <input
                            id="searchBahan"
                            type="search"
                            x-model="search"
                            placeholder="Cari ID, nama bahan, atau kategori..."
                            class="bb-input !bg-stone-50 pl-9 pr-3"
                        >
                    </div>
                </div>

                <div>
                    <label for="filterKategori" class="mb-1.5 block text-xs font-semibold text-stone-600">
                        Kategori
                    </label>
                    <select
                        id="filterKategori"
                        x-model="category"
                        class="bb-select !bg-stone-50"
                    >
                        <option value="">Semua kategori</option>
                        <template x-for="kategori in categories" :key="kategori">
                            <option :value="kategori" x-text="kategori"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label for="filterStatus" class="mb-1.5 block text-xs font-semibold text-stone-600">
                        Status stok
                    </label>
                    <select
                        id="filterStatus"
                        x-model="stockStatus"
                        class="bb-select !bg-stone-50"
                    >
                        <option value="">Semua status</option>
                        <option value="aman">Aman</option>
                        <option value="minimum">Perlu restock</option>
                        <option value="kosong">Belum ada stok</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Informasi hasil dan jumlah baris --}}
        <div class="flex flex-col gap-4 border-b border-stone-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-stone-500">
                Menampilkan
                <strong class="text-stone-800" x-text="filteredItems.length ? startIndex + 1 : 0"></strong>
                –
                <strong class="text-stone-800" x-text="Math.min(startIndex + perPage, filteredItems.length)"></strong>
                dari
                <strong class="text-stone-800" x-text="filteredItems.length"></strong>
                bahan
            </p>

            <div class="flex items-center gap-2">
                <label for="sortBahan" class="text-xs text-stone-500">Urutkan:</label>
                <select
                    id="sortBahan"
                    x-model="sortBy"
                    class="bb-select"
                >
                    <option value="id-asc">ID terkecil</option>
                    <option value="id-desc">ID terbesar</option>
                    <option value="nama-asc">Nama A–Z</option>
                    <option value="nama-desc">Nama Z–A</option>
                    <option value="stok-asc">Stok terendah</option>
                    <option value="stok-desc">Stok tertinggi</option>
                    <option value="harga-asc">Harga terendah</option>
                    <option value="harga-desc">Harga tertinggi</option>
                </select>
            </div>
        </div>

        {{-- Tabel responsif --}}
        <div class="bb-table-scroll">
            <table class="bb-table">
                <thead class="bg-amber-50 text-xs uppercase tracking-wide text-stone-600">
                    <tr>
                        <th class="px-5 py-4 font-semibold">No.</th>
                        <th class="px-4 py-4 font-semibold">ID Bahan</th>
                        <th class="px-4 py-4 font-semibold">Nama Bahan</th>
                        <th class="px-4 py-4 font-semibold">Kategori</th>
                        <th class="px-4 py-4 font-semibold">Kemasan</th>
                        <th class="px-4 py-4 font-semibold">Harga Pasok</th>
                        <th class="px-4 py-4 font-semibold">Stok DC</th>
                        <th class="px-4 py-4 font-semibold">Minimum</th>
                        <th class="px-4 py-4 font-semibold">Status</th>
                        <th class="px-4 py-4 font-semibold">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-stone-100">
                    <template x-for="(bahan, index) in paginatedItems" :key="bahan.id">
                        <tr class="transition hover:bg-amber-50/40">
                            <td class="px-5 py-4 text-stone-500"
                                x-text="startIndex + index + 1"></td>

                            <td class="px-4 py-4">
                                <span class="rounded-md bg-stone-100 px-2 py-1 font-mono text-xs font-semibold text-stone-700"
                                      x-text="'BB-' + String(bahan.id).padStart(4, '0')"></span>
                            </td>

                            <td class="px-4 py-4">
                                <p class="font-semibold text-stone-800" x-text="bahan.nama"></p>
                                <p class="mt-1 text-xs text-stone-400"
                                   x-text="'Satuan: ' + bahan.satuan"></p>
                            </td>

                            <td class="px-4 py-4">
                                <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs text-stone-600"
                                      x-text="bahan.kategori || 'Belum dikategorikan'"></span>
                            </td>

                            <td class="px-4 py-4 text-stone-600">
                                <span x-text="bahan.kemasan"></span>
                                <p class="mt-1 text-xs text-stone-400"
                                   x-text="formatNumber(bahan.isi) + ' ' + bahan.satuan"></p>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 font-medium text-stone-700"
                                x-text="formatRupiah(bahan.harga)"></td>

                            <td class="whitespace-nowrap px-4 py-4 font-semibold text-stone-800">
                                <span x-text="bahan.stok === null ? '—' : formatNumber(bahan.stok)"></span>
                                <span class="text-xs font-normal text-stone-400" x-text="bahan.stok === null ? '' : bahan.satuan"></span>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 text-stone-600">
                                <span x-text="bahan.minimum === null ? '—' : formatNumber(bahan.minimum)"></span>
                                <span class="text-xs text-stone-400" x-text="bahan.minimum === null ? '' : bahan.satuan"></span>
                            </td>

                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="statusClass(bahan)"
                                    x-text="statusLabel(bahan)"
                                ></span>
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="openEdit(bahan)"
                                        title="Edit informasi bahan"
                                        class="bb-action-button bb-action-edit"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        @click="openAddStock(bahan)"
                                        title="Tambah stok DC"
                                        class="bb-action-button bb-action-stock"
                                    >
                                        + Stok
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filteredItems.length === 0">
                        <td colspan="10" class="px-5 py-14 text-center">
                            <p class="font-semibold text-stone-700">Bahan baku tidak ditemukan</p>
                            <p class="mt-1 text-sm text-stone-400">
                                Coba ubah kata pencarian atau filter.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="flex flex-col gap-4 border-t border-stone-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <label for="perPage" class="text-xs text-stone-500">Baris per halaman</label>
                <select
                    id="perPage"
                    x-model.number="perPage"
                    @change="currentPage = 1"
                    class="bb-select"
                >
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>

            <div class="flex items-center justify-between gap-3 sm:justify-end">
                <button
                    type="button"
                    @click="currentPage = Math.max(1, currentPage - 1)"
                    :disabled="currentPage <= 1"
                    class="rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-700 hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Sebelumnya
                </button>

                <span class="whitespace-nowrap text-sm text-stone-500">
                    Halaman <strong x-text="currentPage"></strong> /
                    <strong x-text="totalPages"></strong>
                </span>

                <button
                    type="button"
                    @click="currentPage = Math.min(totalPages, currentPage + 1)"
                    :disabled="currentPage >= totalPages"
                    class="rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-700 hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Berikutnya
                </button>
            </div>
        </div>
    </div>

    {{-- Modal tambah bahan baku --}}
    <div
        x-show="modal === 'create'"
        x-transition.opacity
        @keydown.escape.window="closeModal()"
        @click.self="closeModal()"
        class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-stone-950/50 p-4 backdrop-blur-sm"
        style="display: none;"
    >
        <div
            x-show="modal === 'create'"
            x-transition
            class="bb-modal-panel my-auto"
        >
            <div class="bb-modal-heading flex items-start justify-between border-b border-stone-100 px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">Master Data DC</p>
                    <h3 class="mt-1 text-xl font-bold text-stone-800">Tambah Bahan Baku</h3>
                    <p class="mt-1 text-sm text-stone-500">Masukkan informasi bahan dan stok awal.</p>
                </div>
                <button type="button" @click="closeModal()" class="rounded-lg px-3 py-1 text-2xl text-stone-400 hover:bg-stone-100" aria-label="Tutup">×</button>
            </div>

            <form method="POST" action="{{ route('dc.bahan-baku.store') }}" class="space-y-5 p-6">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Nama bahan *</span>
                        <input name="nama_bahan" required maxlength="150" value="{{ old('nama_bahan') }}"
                               placeholder="Contoh: Arabica Beans"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Kategori *</span>
                        <input name="kategori" required maxlength="100" value="{{ old('kategori') }}"
                               placeholder="Contoh: Bahan Baku"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Satuan stok/resep *</span>
                        <input name="satuan" required maxlength="30" value="{{ old('satuan') }}"
                               placeholder="Contoh: gram, ml, pcs"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Nama kemasan *</span>
                        <input name="nama_kemasan" required maxlength="50" value="{{ old('nama_kemasan') }}"
                               placeholder="Contoh: Kantong"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Isi per kemasan *</span>
                        <input type="number" name="isi_per_kemasan" required min="0.001" step="0.001" value="{{ old('isi_per_kemasan') }}"
                               placeholder="1000"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Harga pasok ke cabang (Rp) *</span>
                        <input type="number" name="harga_beli" required min="0" step="0.01" value="{{ old('harga_beli') }}"
                               placeholder="180000"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>
                </div>

                <div class="rounded-xl border border-amber-100 bg-amber-50/70 p-4">
                    <h4 class="font-semibold text-stone-800">Persediaan awal DC</h4>
                    <p class="mt-1 text-xs text-stone-500">Jumlah menggunakan satuan dasar bahan, bukan jumlah kemasan.</p>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-semibold text-stone-700">Stok awal *</span>
                            <input type="number" name="stok_awal" required min="0" step="0.001" value="{{ old('stok_awal', 0) }}"
                                   class="w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-semibold text-stone-700">Stok minimum *</span>
                            <input type="number" name="stok_minimum" required min="0" step="0.001" value="{{ old('stok_minimum', 0) }}"
                                   class="w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                        </label>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
                    <button type="button" @click="closeModal()" class="rounded-xl border border-stone-200 px-5 py-3 text-sm font-semibold text-stone-600 hover:bg-stone-50">Batal</button>
                    <button type="submit" class="bb-primary-button rounded-xl px-5 py-3 text-sm font-semibold">Simpan Bahan Baku</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal edit informasi --}}
    <div
        x-show="modal === 'edit'"
        x-transition.opacity
        @keydown.escape.window="closeModal()"
        @click.self="closeModal()"
        class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-stone-950/50 p-4 backdrop-blur-sm"
        style="display: none;"
    >
        <div x-show="modal === 'edit'" x-transition class="bb-modal-panel my-auto">
            <div class="bb-modal-heading flex items-start justify-between border-b border-stone-100 px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">Ubah Master Data</p>
                    <h3 class="mt-1 text-xl font-bold text-stone-800">Edit Informasi Bahan</h3>
                    <p class="mt-1 text-sm text-stone-500">
                        ID: <span class="font-mono font-semibold" x-text="selected ? 'BB-' + String(selected.id).padStart(4, '0') : '—'"></span>
                    </p>
                </div>
                <button type="button" @click="closeModal()" class="rounded-lg px-3 py-1 text-2xl text-stone-400 hover:bg-stone-100" aria-label="Tutup">×</button>
            </div>

            <form method="POST" :action="updateUrl" class="space-y-5 p-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Nama bahan *</span>
                        <input name="nama_bahan" x-model="form.nama_bahan" required maxlength="150"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Kategori *</span>
                        <input name="kategori" x-model="form.kategori" required maxlength="100"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Satuan *</span>
                        <input name="satuan" x-model="form.satuan" required maxlength="30"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Nama kemasan *</span>
                        <input name="nama_kemasan" x-model="form.nama_kemasan" required maxlength="50"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Isi per kemasan *</span>
                        <input type="number" name="isi_per_kemasan" x-model="form.isi_per_kemasan" required min="0.001" step="0.001"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-semibold text-stone-700">Harga pasok ke cabang (Rp) *</span>
                        <input type="number" name="harga_beli" x-model="form.harga_beli" required min="0" step="0.01"
                               class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100">
                    </label>
                </div>

                <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
                    <strong>Informasi:</strong> Form ini hanya mengubah data bahan baku. Stok DC dan stok minimum tidak diubah dari sini.
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
                    <button type="button" @click="closeModal()" class="rounded-xl border border-stone-200 px-5 py-3 text-sm font-semibold text-stone-600 hover:bg-stone-50">Batal</button>
                    <button type="submit" class="bb-primary-button rounded-xl px-5 py-3 text-sm font-semibold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal tambah stok --}}
    <div
        x-show="modal === 'stock'"
        x-transition.opacity
        @keydown.escape.window="closeModal()"
        @click.self="closeModal()"
        class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-stone-950/50 p-4 backdrop-blur-sm"
        style="display: none;"
    >
        <div x-show="modal === 'stock'" x-transition class="bb-modal-panel my-auto">
            <div class="flex items-start justify-between border-b border-stone-100 px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">Persediaan DC</p>
                    <h3 class="mt-1 text-xl font-bold text-stone-800">Tambah Stok</h3>
                    <p class="mt-1 text-sm text-stone-500">Catat stok masuk untuk bahan yang dipilih.</p>
                </div>
                <button type="button" @click="closeModal()" class="rounded-lg px-3 py-1 text-2xl text-stone-400 hover:bg-stone-100" aria-label="Tutup">×</button>
            </div>

            <form method="POST" :action="stockUrl" class="space-y-5 p-6">
                @csrf

                <div class="rounded-xl bg-stone-50 p-4">
                    <p class="text-xs text-stone-500">Bahan baku</p>
                    <p class="mt-1 font-semibold text-stone-800" x-text="selected ? selected.nama : '—'"></p>
                    <p class="mt-1 font-mono text-xs text-stone-500"
                       x-text="selected ? 'BB-' + String(selected.id).padStart(4, '0') : ''"></p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-stone-500">Stok saat ini</p>
                            <p class="mt-1 font-semibold text-stone-800"
                               x-text="selected && selected.stok !== null ? formatNumber(selected.stok) + ' ' + selected.satuan : 'Belum tersedia'"></p>
                        </div>
                        <div>
                            <p class="text-xs text-stone-500">Stok minimum</p>
                            <p class="mt-1 font-semibold text-stone-800"
                               x-text="selected && selected.minimum !== null ? formatNumber(selected.minimum) + ' ' + selected.satuan : '—'"></p>
                        </div>
                    </div>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-semibold text-stone-700">
                        Jumlah tambahan (<span x-text="selected ? selected.satuan : 'satuan dasar'"></span>) *
                    </span>
                    <input
                        type="number"
                        name="jumlah"
                        required
                        min="0.001"
                        step="0.001"
                        x-model="stockAmount"
                        placeholder="Masukkan jumlah stok masuk"
                        class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100"
                    >
                    <p class="mt-1.5 text-xs text-stone-400">
                        Masukkan jumlah dalam satuan dasar, misalnya gram atau ml.
                    </p>
                </label>

                <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
                    <button type="button" @click="closeModal()" class="rounded-xl border border-stone-200 px-5 py-3 text-sm font-semibold text-stone-600 hover:bg-stone-50">Batal</button>
                    <button type="submit" class="bb-primary-button rounded-xl px-5 py-3 text-sm font-semibold">Simpan Stok Masuk</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


@push('scripts')
<script>
    function bahanBakuPage(initialItems) {
        return {
            items: initialItems || [],
            search: '',
            category: '',
            stockStatus: '',
            sortBy: 'id-asc',
            currentPage: 1,
            perPage: 10,

            modal: null,
            selected: null,
            updateUrl: '',
            stockUrl: '',
            stockAmount: '',

            form: {
                nama_bahan: '',
                kategori: '',
                satuan: '',
                nama_kemasan: '',
                isi_per_kemasan: '',
                harga_beli: ''
            },

            init() {
                this.$watch('search', () => this.currentPage = 1);
                this.$watch('category', () => this.currentPage = 1);
                this.$watch('stockStatus', () => this.currentPage = 1);
                this.$watch('sortBy', () => this.currentPage = 1);
                this.$watch('perPage', () => this.currentPage = 1);
            },

            get categories() {
                return [...new Set(
                    this.items
                        .map(item => item.kategori)
                        .filter(value => value && value.trim() !== '')
                )].sort((a, b) => a.localeCompare(b, 'id'));
            },

            get filteredItems() {
                const term = this.search.trim().toLocaleLowerCase('id');

                let result = this.items.filter(item => {
                    const matchesSearch = !term || [
                        item.id,
                        'BB-' + String(item.id).padStart(4, '0'),
                        item.nama,
                        item.kategori,
                        item.kemasan,
                        item.satuan
                    ].some(value => String(value ?? '').toLocaleLowerCase('id').includes(term));

                    const matchesCategory =
                        !this.category || item.kategori === this.category;

                    let matchesStatus = true;

                    if (this.stockStatus === 'kosong') {
                        matchesStatus = item.stok === null;
                    } else if (this.stockStatus === 'minimum') {
                        matchesStatus =
                            item.stok !== null &&
                            item.minimum !== null &&
                            item.stok <= item.minimum;
                    } else if (this.stockStatus === 'aman') {
                        matchesStatus =
                            item.stok !== null &&
                            item.minimum !== null &&
                            item.stok > item.minimum;
                    }

                    return matchesSearch && matchesCategory && matchesStatus;
                });

                const [field, direction] = this.sortBy.split('-');
                const multiplier = direction === 'desc' ? -1 : 1;

                result.sort((a, b) => {
                    let comparison = 0;

                    if (field === 'nama') {
                        comparison = a.nama.localeCompare(b.nama, 'id');
                    } else if (field === 'stok') {
                        const aValue = a.stok === null ? -Infinity : a.stok;
                        const bValue = b.stok === null ? -Infinity : b.stok;
                        comparison = aValue - bValue;
                    } else if (field === 'harga') {
                        comparison = a.harga - b.harga;
                    } else {
                        comparison = Number(a.id) - Number(b.id);
                    }

                    return comparison * multiplier;
                });

                return result;
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.filteredItems.length / this.perPage));
            },

            get startIndex() {
                return (this.currentPage - 1) * this.perPage;
            },

            get paginatedItems() {
                return this.filteredItems.slice(
                    this.startIndex,
                    this.startIndex + Number(this.perPage)
                );
            },

            formatNumber(value) {
                return new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 3
                }).format(Number(value));
            },

            formatRupiah(value) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                }).format(Number(value));
            },

            statusLabel(item) {
                if (item.stok === null) return 'Belum ada stok';
                if (item.minimum !== null && item.stok <= item.minimum) {
                    return 'Perlu restock';
                }
                if (item.minimum === null) return 'Minimum belum ada';
                return 'Aman';
            },

            statusClass(item) {
                if (item.stok === null) {
                    return 'bg-stone-100 text-stone-600';
                }
                if (item.minimum !== null && item.stok <= item.minimum) {
                    return 'bg-orange-100 text-orange-800';
                }
                if (item.minimum === null) {
                    return 'bg-stone-100 text-stone-600';
                }
                return 'bg-green-100 text-green-800';
            },

            openCreate() {
                this.modal = 'create';
            },

            openEdit(item) {
                this.selected = item;

                this.form = {
                    nama_bahan: item.nama,
                    kategori: item.kategori || '',
                    satuan: item.satuan,
                    nama_kemasan: item.kemasan,
                    isi_per_kemasan: item.isi,
                    harga_beli: item.harga
                };

                this.updateUrl = @js(route('dc.bahan-baku.update', '__ID__'))
                    .replace('__ID__', encodeURIComponent(item.id));

                this.modal = 'edit';
            },

            openAddStock(item) {
                this.selected = item;
                this.stockAmount = '';

                this.stockUrl = @js(route('dc.bahan-baku.add-stock', '__ID__'))
                    .replace('__ID__', encodeURIComponent(item.id));

                this.modal = 'stock';
            },

            closeModal() {
                this.modal = null;
                this.selected = null;
                this.stockAmount = '';
            },

            resetFilters() {
                this.search = '';
                this.category = '';
                this.stockStatus = '';
                this.sortBy = 'id-asc';
                this.currentPage = 1;
            }
        };
    }
</script>
@endpush
