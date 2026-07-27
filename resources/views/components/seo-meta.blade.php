{{--
    resources/views/components/seo-meta.blade.php

    SEO + kartu berbagi (Open Graph / Twitter). Di-include di <head> halaman
    login dan layout utama. Crawler share (WhatsApp/Facebook/Telegram/dll)
    mengikuti redirect '/' -> '/login', jadi meta di halaman login inilah yang
    tampil saat link dibagikan — bukan "link polos".
--}}
@php
    $seoTitle = 'SAPTO — Sistem Aplikasi Pelaporan Tridharma Universitas Sriwijaya';
    $seoDesc = 'Portal pelaporan data Tridharma (pendidikan, penelitian, dan pengabdian kepada masyarakat) Universitas Sriwijaya untuk kebutuhan akreditasi program studi (BAN-PT/LAM).';
    $seoImage = asset('images/og-sapto.png');
    $seoUrl = url('/');
@endphp
<meta name="description" content="{{ $seoDesc }}">
<meta name="keywords" content="SAPTO, Universitas Sriwijaya, UNSRI, akreditasi, tridharma, pelaporan, BAN-PT, LAM, LKPS, program studi">
<meta name="author" content="Universitas Sriwijaya">
<meta name="robots" content="index, follow">
<link rel="canonical" href="{{ $seoUrl }}">

{{-- Open Graph (Facebook, WhatsApp, LinkedIn, Telegram, dll) --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="SAPTO Universitas Sriwijaya">
<meta property="og:locale" content="id_ID">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDesc }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:image:secure_url" content="{{ $seoImage }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="SAPTO — Universitas Sriwijaya">

{{-- Twitter / X --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDesc }}">
<meta name="twitter:image" content="{{ $seoImage }}">
<meta name="twitter:image:alt" content="SAPTO — Universitas Sriwijaya">
