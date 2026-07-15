{{--
    resources/views/components/sidebar.blade.php

    45 tables ship in a flat array, so they're grouped here by the leading
    segment of $table['code'] (e.g. "3.a.2" -> group "3") into labeled
    sections — a flat unlabeled list at this length isn't scannable.
    Search filtering (see app.js/#menu-search wiring in sapto.blade.php)
    covers the rest. Colours come from the --side-* theme tokens (app.css).
--}}
@php
    $groups = [];

    foreach ($tables as $table) {
        $groupKey = explode('.', $table['code'])[0] ?? $table['code'];
        $groups[$groupKey][] = $table;
    }

    $ssoUser = session('sso_user');
    $userName = $ssoUser['name'] ?? ($ssoUser['preferred_username'] ?? 'Pengguna');
    $userInitial = Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($userName, 0, 1));
@endphp

<aside id="sidebar" class="sidebar side-glass flex flex-col">
    <div class="flex min-h-[73px] items-center gap-3 px-5" style="border-bottom:1px solid var(--side-border)">
        <div class="grid h-11 w-11 shrink-0 place-items-center">
            <img
                src="{{ asset('images/logo-unsri.png') }}"
                alt="Lambang Universitas Sriwijaya"
                class="h-full w-full object-contain drop-shadow-[0_2px_10px_rgba(0,0,0,0.35)]"
            />
        </div>
        <div class="min-w-0 leading-tight">
            <div class="flex items-center gap-1.5">
                <span class="text-[16px] font-extrabold tracking-tight" style="color:var(--side-text)">SAPTO</span>
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>
            </div>
            <div class="truncate text-[11px] font-medium" style="color:var(--side-dim)">Universitas Sriwijaya</div>
        </div>
        <button
            type="button"
            id="sidebar-close"
            aria-label="Tutup sidebar"
            class="ml-auto grid h-8 w-8 shrink-0 place-items-center rounded-lg transition hover:bg-accent/10 lg:hidden"
            style="color:var(--side-dim)"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </button>
    </div>

    <div class="px-3 py-3" style="border-bottom:1px solid var(--side-border)">
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2" style="color:var(--side-faint)" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.7" />
                <path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            </svg>
            <input
                id="menu-search"
                type="search"
                placeholder="Cari tabel..."
                aria-label="Cari tabel"
                class="peer w-full rounded-xl py-2 pl-8 pr-9 text-[13px] transition focus:outline-none focus:ring-2 focus:ring-accent/25"
                style="background:var(--input-bg);border:1px solid var(--input-border);color:var(--side-text);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)"
            >
            <kbd
                class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 rounded px-1.5 py-0.5 text-[10px] font-medium peer-focus:hidden"
                style="border:1px solid var(--side-border);color:var(--side-faint)"
                aria-hidden="true"
            >/</kbd>
        </div>
    </div>

    <nav id="menu" class="menu flex-1 space-y-4 overflow-y-auto px-3 py-3" aria-label="Menu tabel SAPTO">
        <div class="space-y-0.5">
            <button
                type="button"
                data-table-id="dashboard"
                class="menu-item {{ ($activeTableId ?? 'dashboard') === 'dashboard' ? 'is-active' : '' }}"
            >
                <svg class="mr-2 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 9.5 12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z" />
                </svg>
                Dashboard
            </button>
        </div>

        @foreach ($groups as $groupKey => $items)
            <div class="menu-group" data-menu-group>
                <div class="mb-1 flex items-center gap-2 px-3">
                    <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider" style="color:var(--side-faint)">Tabel {{ $groupKey }}</span>
                    <span class="h-px flex-1" style="background:var(--side-border)"></span>
                </div>
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

        <p id="menu-empty" class="hidden px-3 py-8 text-center text-[12px]" style="color:var(--side-faint)">Tidak ada tabel yang cocok dengan pencarian.</p>
    </nav>

    @if ($ssoUser)
        <div class="px-3 py-3" style="border-top:1px solid var(--side-border)">
            <div class="flex items-center gap-2.5">
                <div
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-[12px] font-bold"
                    style="background:var(--color-accent-soft);color:var(--color-accent-dark)"
                    aria-hidden="true"
                >{{ $userInitial }}</div>
                <div class="min-w-0 flex-1 leading-tight">
                    <div class="truncate text-[12px] font-semibold" style="color:var(--side-text)" title="{{ $ssoUser['email'] ?? '' }}">
                        {{ $userName }}
                    </div>
                    <div class="truncate text-[11px]" style="color:var(--side-faint)" title="{{ $ssoUser['unit_kerja'] ?? '' }}">
                        Pengelola Program Studi{{ isset($ssoUser['unit_kerja']) ? ' — '.$ssoUser['unit_kerja'] : '' }}
                    </div>
                </div>
                <a
                    href="{{ route('sso.logout') }}"
                    class="sidebar-logout shrink-0"
                    title="Logout"
                    aria-label="Logout"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                    </svg>
                </a>
            </div>
        </div>
    @endif
</aside>

<style>
    .sidebar-logout {
        display: grid;
        place-items: center;
        height: 26px;
        width: 26px;
        border-radius: 0.5rem;
        color: var(--side-faint);
        transition: background-color .15s ease, color .15s ease;
    }

    .sidebar-logout:hover {
        background: var(--side-hover);
        color: var(--side-text);
    }
</style>
