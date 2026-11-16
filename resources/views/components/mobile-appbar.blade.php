{{-- resources/views/components/mobile-appbar.blade.php --}}
{{--
    App bar halaman tabel untuk layar < sm (menggantikan #topbar di layar
    sempit). Baris atas: kembali + nama tabel. Baris bawah: chip filter.
    Aksi (unduh, lihat sebagai tabel) sengaja TIDAK berupa ikon di sini:
    pengguna utamanya dosen senior, jadi aksi tampil sebagai tombol
    bertulisan di dalam halaman. Tiap chip membungkus <select> asli
    (transparan, menutupi chip) supaya HP membuka pemilih bawaannya; isinya
    dicerminkan dari select filter desktop oleh syncMobileFilters() di
    sapto.blade.php, dan perubahan diteruskan balik ke select itu -- logika
    filter tetap satu jalur.
--}}
<div id="mobile-appbar" class="m-appbar">
    <div class="m-appbar-row">
        <button type="button" id="m-back" class="m-icon-btn is-plain" aria-label="Kembali">
            <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <div class="m-appbar-text">
            <h1 id="m-title" class="m-appbar-title"></h1>
            <div id="m-subtitle" class="m-appbar-sub"></div>
        </div>
    </div>

    <div class="m-filters" role="group" aria-label="Filter">
        @foreach (['ts' => 'Tahun (TS)', 'fak' => 'Fakultas', 'prodi' => 'Program studi'] as $key => $label)
            <label id="m-{{ $key }}-wrap" class="m-select-chip" hidden>
                <span id="m-{{ $key }}-label" class="m-select-chip-text"></span>
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                <select id="m-{{ $key }}" aria-label="{{ $label }}"></select>
            </label>
        @endforeach
        <span id="m-unit-static" class="m-static-chip" hidden></span>
    </div>
</div>
