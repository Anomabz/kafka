{{--
    resources/views/components/dashboard-page.blade.php

    Landing page: ringkasan 5 tabel BAN-PT yang sudah punya data nyata (dari
    46 total). Data diisi oleh loadDashboard()/renderDashboard() di
    sapto.blade.php dari GET /api/sapto/dashboard/summary. Urutan array ini
    cuma menentukan urutan render di markup — JS mencocokkan tiap kartu ke
    payload lewat data-card-id, bukan index, jadi urutan tidak perlu sama
    persis dengan urutan "cards" di response.
--}}
@php
    $dashboardCards = [
        ['id' => 'dosen-tetap-pt', 'tableId' => 'dosen-tetap-pt'],
        ['id' => 'dosen-pembimbing-ta', 'tableId' => 'dosen-pembimbing-ta'],
        ['id' => 'ipk-lulusan', 'tableId' => 'ipk-lulusan'],
        ['id' => 'masa-studi', 'tableId' => 'masa-studi'],
        ['id' => 'mahasiswa-asing', 'tableId' => 'mahasiswa-asing'],
    ];
@endphp

<section id="dashboard-page">
    @include('components.filter-bar')

    <div id="dashboard-status" class="mb-3 text-[12px] text-muted sm:mb-4"></div>

    <div class="grid grid-cols-1 gap-3 sm:gap-4 lg:grid-cols-2">
        @foreach ($dashboardCards as $card)
            <button
                type="button"
                class="dashboard-card glass glass-hover rounded-2xl p-4 text-left"
                data-card-id="{{ $card['id'] }}"
                data-table-id="{{ $card['tableId'] }}"
            >
                <div class="flex items-start justify-between gap-2">
                    <h3 class="dashboard-card-title text-[14px] font-bold text-ink"></h3>
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M7 17 17 7M7 7h10v10" />
                    </svg>
                </div>
                <div class="dashboard-card-metrics mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3"></div>
                <div class="dashboard-card-trend mt-3"></div>
            </button>
        @endforeach
    </div>
</section>
