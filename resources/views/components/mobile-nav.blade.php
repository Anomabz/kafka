{{-- resources/views/components/mobile-nav.blade.php --}}
{{-- Navigasi bawah khusus layar < sm. Di >= sm disembunyikan lewat CSS --
     desktop tetap memakai sidebar. Tab aktif diatur syncMobileShell() di
     sapto.blade.php lewat aria-current. --}}
<nav id="mobile-nav" class="m-nav" aria-label="Navigasi utama">
    @foreach ([
        'beranda' => ['Beranda', '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" />'],
        'tabel' => ['Tabel', '<rect x="3" y="4" width="18" height="16" rx="2" /><path d="M3 10h18M9 4v16" />'],
        'akun' => ['Akun', '<circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" />'],
    ] as $key => [$label, $path])
        <button type="button" class="m-nav-item" data-nav="{{ $key }}">
            <span class="m-nav-indicator">
                <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $path !!}</svg>
            </span>
            <span class="m-nav-label">{{ $label }}</span>
        </button>
    @endforeach
</nav>
