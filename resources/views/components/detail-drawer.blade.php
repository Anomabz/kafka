{{-- resources/views/components/detail-drawer.blade.php --}}
<div id="drawer-backdrop" class="drawer-backdrop fixed z-20"></div>

<aside
    id="detail-drawer"
    class="drawer glass-strong fixed right-0 top-0 z-30 w-full max-w-[620px] flex-col"
    aria-labelledby="detail-title"
    aria-modal="true"
    role="dialog"
>
    <header class="flex min-h-[72px] items-center justify-between gap-3 px-5" style="border-bottom:1px solid var(--color-line)">
        <div>
            <h3 id="detail-title" class="text-[16px] font-bold text-ink">Detail Data</h3>
            <div id="detail-subtitle" class="text-[12px] text-muted"></div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="drawer-detail-export" class="rounded-lg px-3 py-1.5 text-[12px] font-semibold text-ink hover:bg-accent-soft" style="border:1px solid var(--input-border)">Export Excel</button>
            <button
                type="button"
                id="close-drawer"
                aria-label="Tutup detail"
                class="theme-toggle"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    </header>

    <div id="detail-body" class="flex-1 space-y-3 overflow-y-auto p-5"></div>
</aside>
