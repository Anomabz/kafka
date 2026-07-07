{{--
    resources/views/components/metric-card.blade.php

    Reusable KPI card. Expects: $id, $label, $icon (raw svg string), $tint.
    $tint is one of: accent | info | warn | slate — mirrors the colour-coded
    status bars already used elsewhere in SAPTO/SIM IKU, so a card's colour
    always means the same thing across the tool, not just decoration here.
--}}
@php
    $tints = [
        'accent' => ['bar' => 'bg-accent', 'chip' => 'bg-accent-soft text-accent-dark'],
        'info' => ['bar' => 'bg-focus', 'chip' => 'bg-info-soft text-focus'],
        'warn' => ['bar' => 'bg-warn', 'chip' => 'bg-warn-soft text-warn'],
        'slate' => ['bar' => 'bg-slate-400', 'chip' => 'bg-slate-soft text-slate-600'],
    ];
    $t = $tints[$tint] ?? $tints['slate'];
@endphp

<div class="relative overflow-hidden rounded-xl border border-line bg-surface p-4 shadow-sm">
    <span class="absolute inset-y-0 left-0 w-1 {{ $t['bar'] }}" aria-hidden="true"></span>

    <div class="flex items-start justify-between gap-3 pl-2">
        <div class="min-w-0">
            <div @isset($labelId) id="{{ $labelId }}" @endisset class="text-[12px] leading-snug text-muted">{{ $label }}</div>
            <div id="{{ $id }}" class="metric-value is-loading mt-2 text-[26px] font-extrabold leading-none text-ink">0</div>
        </div>
        <div class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $t['chip'] }}">
            {!! $icon !!}
        </div>
    </div>
</div>
