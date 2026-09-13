{{--
    resources/views/components/role-badge.blade.php

    Badge peran, satu desain berbeda per peran (bukan cuma beda warna) --
    Admin = badge sistem netral gelap (bukan turunan token karena warna &
    posisinya sengaja tetap terlepas dari tema, mirip elemen fixed-dark
    lain di aplikasi), Rektor/Dekan/Korprodi memakai intensitas token
    aksen yang menurun sesuai luas cakupan (solid penuh -> outline ->
    minimal titik). Dipakai di sidebar (baris identitas + modal profil).

    Props:
    - role: 'admin' | 'rektor' | 'dekan' | 'korprodi'
--}}
@props(['role'])

@php
    $labels = [
        'admin' => 'Admin',
        'rektor' => 'Rektor',
        'dekan' => 'Dekan',
        'korprodi' => 'Korprodi',
    ];
    $label = $labels[$role] ?? null;
@endphp

@if ($label)
    @if ($role === 'admin')
        <span
            class="role-badge role-badge-admin inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide"
            style="background:#2b2f33;color:#eef0f2"
        >
            <svg class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>
            </svg>
            {{ $label }}
        </span>
    @elseif ($role === 'rektor')
        <span
            class="role-badge role-badge-rektor inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
            style="background:var(--color-accent)"
        >
            <svg class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="m12 2 2.6 6.2 6.7.5-5.1 4.4 1.6 6.5L12 16.3 6.2 19.6l1.6-6.5-5.1-4.4 6.7-.5Z"/>
            </svg>
            {{ $label }}
        </span>
    @elseif ($role === 'dekan')
        <span
            class="role-badge role-badge-dekan inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
            style="background:var(--color-accent-soft);color:var(--color-accent-dark);box-shadow:inset 0 0 0 1px var(--color-accent-line)"
        >
            <svg class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>
            </svg>
            {{ $label }}
        </span>
    @else
        <span
            class="role-badge role-badge-korprodi inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
            style="border:1px dashed var(--color-line);color:var(--color-muted)"
        >
            <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background:var(--color-accent)"></span>
            {{ $label }}
        </span>
    @endif
@endif
