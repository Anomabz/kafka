{{--
    resources/views/components/sidebar.blade.php

    45 tables ship in a flat array, so they're grouped here by the leading
    segment of $table['code'] (e.g. "3.a.2" -> group "3") into labeled
    sections — a flat unlabeled list at this length isn't scannable.
    Search filtering (see app.js/#menu-search wiring in sapto.blade.php)
    covers the rest.
--}}
@php
    $groups = [];

    foreach ($tables as $table) {
        $groupKey = explode('.', $table['code'])[0] ?? $table['code'];
        $groups[$groupKey][] = $table;
    }
@endphp

<aside id="sidebar" class="sidebar flex flex-col bg-navy-950 text-slate-200">
    <div class="flex min-h-[72px] items-center gap-3 border-b border-white/10 px-5">
        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-accent to-focus text-[15px] font-extrabold text-white">
            S
        </div>
        <div class="leading-tight">
            <div class="text-[15px] font-bold text-white">SAPTO</div>
            <div class="text-[11px] text-slate-400">Akreditasi Program Studi</div>
        </div>
        <button
            type="button"
            id="sidebar-close"
            aria-label="Tutup sidebar"
            class="ml-auto grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-white/10 hover:text-white lg:hidden"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </button>
    </div>

    <div class="border-b border-white/10 px-3 py-3">
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.7" />
                <path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            </svg>
            <input
                id="menu-search"
                type="search"
                placeholder="Cari tabel..."
                aria-label="Cari tabel"
                class="w-full rounded-lg border border-white/10 bg-white/5 py-2 pl-8 pr-3 text-[13px] text-white
                       placeholder:text-slate-500 transition focus:border-accent/60 focus:bg-white/10 focus:outline-none focus:ring-2 focus:ring-accent/25"
            >
        </div>
    </div>

    <nav id="menu" class="menu flex-1 space-y-4 overflow-y-auto px-3 py-3" aria-label="Menu tabel SAPTO">
        @foreach ($groups as $groupKey => $items)
            <div class="menu-group" data-menu-group>
                <div class="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500">Tabel {{ $groupKey }}</div>
                <div class="space-y-0.5">
                    @foreach ($items as $table)
                        <button
                            type="button"
                            data-table-id="{{ $table['id'] }}"
                            data-search="{{ Illuminate\Support\Str::lower($table['code'] . ' ' . $table['name']) }}"
                            class="menu-item {{ $table['id'] === $activeTableId ? 'is-active' : '' }}"
                        >
                            {{ $table['name'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach

        <p id="menu-empty" class="hidden px-3 py-8 text-center text-[12px] text-slate-500">Tidak ada tabel yang cocok dengan pencarian.</p>
    </nav>

    <div class="border-t border-white/10 px-5 py-3 text-[11px] text-slate-500">
        SAPTO &middot; Universitas Sriwijaya
    </div>
</aside>
