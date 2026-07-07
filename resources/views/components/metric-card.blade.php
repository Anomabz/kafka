{{--
    resources/views/components/metric-card.blade.php

    Reusable KPI card, frosted glass. Expects: $id, $label, $icon (raw svg), $tint.
    $tint is one of: accent | info | warn | slate — the colour a card carries
    always means the same thing across SAPTO, not just decoration. All tint
    colours are theme tokens so the card adapts to light/dark automatically.
--}}
@php
    $tints = [
        'accent' => ['bar' => 'bg-accent', 'chip' => 'bg-accent-soft text-accent-dark'],
        'info' => ['bar' => 'bg-info', 'chip' => 'bg-info-soft text-info'],
        'warn' => ['bar' => 'bg-warn', 'chip' => 'bg-warn-soft text-warn'],
        'slate' => ['bar' => 'bg-muted', 'chip' => 'bg-slate-soft text-muted'],
    ];
    $t = $tints[$tint] ?? $tints['slate'];
@endphp

<div class="glass glass-hover relative overflow-hidden rounded-2xl p-4">
    <span class="absolute inset-y-0 left-0 w-1 {{ $t['bar'] }}" aria-hidden="true"></span>

    <div class="flex items-start justify-between gap-3 pl-2">
        <div class="min-w-0">
            <div @isset($labelId) id="{{ $labelId }}" @endisset class="text-[12px] leading-snug text-muted">{{ $label }}</div>
            <div id="{{ $id }}" class="metric-value is-loading mt-2 text-[26px] font-extrabold leading-none text-ink">0</div>
        </div>
        <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $t['chip'] }}">
            {!! $icon !!}
        </div>
    </div>
</div>
