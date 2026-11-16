{{--
    Versi JavaScript dari <x-icon>, dipakai untuk icon yang dibuat lewat JS
    (template string / Alpine). Dimuat di layout SEBELUM script halaman,
    jadi window.KopIcon() sudah ada saat script halaman jalan.
    Pakai: KopIcon('bell')  -> string <svg>
--}}
<script>
    window.KopIcons = {!! file_get_contents(resource_path('icons/icons.json')) !!};
    window.KopIcon = function (name, cls) {
        return '<svg class="icon ' + (cls || '') + '" xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" '
            + 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
            + 'stroke-linejoin="round" aria-hidden="true" data-iconify="lucide:' + name + '">'
            + (window.KopIcons[name] || '') + '</svg>';
    };
</script>
