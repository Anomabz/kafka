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

<div class="glass glass-hover relative overflow-hidden rounded-2xl p-3.5 sm:p-4">
    <span class="absolute inset-y-0 left-0 w-1 {{ $t['bar'] }}" aria-hidden="true"></span>

    <div class="flex items-start justify-between gap-2 pl-1.5 sm:gap-3 sm:pl-2">
        <div class="min-w-0">
            <div @isset($labelId) id="{{ $labelId }}" @endisset class="text-[11px] leading-snug text-muted sm:text-[12px]">{{ $label }}</div>
            <div id="{{ $id }}" class="metric-value is-loading mt-1.5 text-[21px] font-extrabold leading-none text-ink sm:mt-2 sm:text-[26px]">0</div>
        </div>
        <div id="{{ $id }}-icon" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg sm:h-9 sm:w-9 sm:rounded-xl {{ $t['chip'] }}">
            {!! $icon !!}
        </div>
    </div>
</div>
