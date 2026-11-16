@props(['name', 'size' => '1.2em'])

{{--
    Icon dari Iconify — set "Lucide" (https://icon-sets.iconify.design/lucide/).
    Satu warna (hitam / ikut warna teks lewat currentColor), hanya garis tepi.
    Pakai: <x-icon name="bell" />   (nama = nama icon Lucide di Iconify)
    Daftar icon yang dipakai ada di resources/icons/icons.json
--}}
@php
    $icons = json_decode(file_get_contents(resource_path('icons/icons.json')), true);
@endphp

<svg
    {{ $attributes->merge(['class' => 'icon']) }}
    xmlns="http://www.w3.org/2000/svg"
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    data-iconify="lucide:{{ $name }}"
>{!! $icons[$name] ?? '' !!}</svg>
