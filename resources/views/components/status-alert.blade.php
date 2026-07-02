{{--
    resources/views/components/status-alert.blade.php

    Static fallback markup (used if the page is ever rendered without JS).
    The same visual is produced at runtime by renderError() in sapto.blade.php
    via the "sp-alert-warn" utility class defined in resources/css/app.css —
    keep both in sync if you change one.
--}}
@props(['title' => 'Kredensial API belum dikonfigurasi', 'message'])

<div class="sp-alert-warn" role="alert">
    <svg class="mt-0.5 h-5 w-5 shrink-0 text-warn" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
              stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <div>
        <div class="font-semibold text-warn">{{ $title }}</div>
        <p class="mt-0.5 text-warn/90">{{ $message }}</p>
    </div>
</div>
