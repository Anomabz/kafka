{{-- resources/views/components/sidebar.blade.php --}}
<aside class="flex flex-col bg-navy-950 text-slate-200 lg:h-screen lg:sticky lg:top-0">
    <div class="flex min-h-[72px] items-center gap-3 border-b border-white/10 px-5">
        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-accent to-focus text-[15px] font-extrabold text-white">
            S
        </div>
        <div class="leading-tight">
            <div class="text-[15px] font-bold text-white">SAPTO</div>
            <div class="text-[11px] text-slate-400">Akreditasi Program Studi</div>
        </div>
    </div>

    <nav class="menu flex-1 space-y-0.5 overflow-y-auto px-3 py-3" aria-label="Menu tabel SAPTO">
        @foreach ($tables as $table)
            <button
                type="button"
                data-table-id="{{ $table['id'] }}"
                class="menu-item {{ $table['id'] === $activeTableId ? 'is-active' : '' }}"
            >
                {{ $table['name'] }}
            </button>
        @endforeach
    </nav>
</aside>
