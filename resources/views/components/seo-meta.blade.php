{{--
    resources/views/components/seo-meta.blade.php

    SEO + kartu berbagi (Open Graph / Twitter). Di-include di <head> halaman
    login dan layout utama. Crawler share (WhatsApp/Facebook/Telegram/dll)
    mengikuti redirect '/' -> '/login', jadi meta di halaman login inilah yang
    tampil saat link dibagikan — bukan "link polos".
--}}
@php
    $seoTitle = 'SAPTO v1.0 - UNSRI — Sistem Akreditasi Perguruan Tinggi Online Universitas Sriwijaya';
    $seoDesc = 'Portal pelaporan data Tridharma (pendidikan, penelitian, dan pengabdian kepada masyarakat) Universitas Sriwijaya untuk kebutuhan akreditasi program studi (BAN-PT/LAM).';
    // Query ?v= = mtime file gambarnya -- bukan hiasan, ini yang bikin
    // WhatsApp/Facebook/Telegram mau ambil ulang gambarnya. Crawler share
    // nge-cache preview per URL gambar utuh (bisa berhari-hari); ganti isi
    // file og-sapto.png tanpa ganti URL-nya sama sekali tidak akan kelihatan
    // di chat manapun sampai cache itu kadaluarsa sendiri. Auto ikut setiap
    // kali filenya diganti, tidak perlu diingat-ingat buat dinaikkan manual.
    $seoImage = asset('images/og-sapto.png').'?v='.@filemtime(public_path('images/og-sapto.png'));
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

{{-- JSON-LD: dua entitas terpisah (bukan digabung) supaya Google jelas
     membedakan "SAPTO" (aplikasi) dari "Universitas Sriwijaya" (induknya),
     ditautkan lewat isPartOf/publisher. Ini yang paling menentukan buat
     query brand seperti "sapto unsri": entity understanding, bukan sekadar
     keyword di teks.

     Kunci "@type"/"@id"/dst dibentuk lewat konkatenasi ('@'.'type'), BUKAN
     ditulis literal -- Blade men-scan seluruh isi file untuk pola @kata
     sebagai directive (mis. "@context" cocok dengan directive bawaan
     Laravel) SEBELUM PHP-nya jalan, termasuk di dalam string literal.
     Ditulis literal, Blade diam-diam mengganti string itu jadi kode PHP
     directive-nya sendiri dan JSON-nya rusak. --}}
@php
    $ld = [
        '@'.'context' => 'https://schema.org',
        '@'.'graph' => [
            [
                '@'.'type' => 'CollegeOrUniversity',
                '@'.'id' => 'https://unsri.ac.id/#organization',
                'name' => 'Universitas Sriwijaya',
                'alternateName' => 'UNSRI',
                'url' => 'https://unsri.ac.id',
            ],
            [
                '@'.'type' => 'WebApplication',
                '@'.'id' => $seoUrl.'#software',
                'name' => 'SAPTO',
                'alternateName' => 'Sistem Akreditasi Perguruan Tinggi Online',
                'url' => $seoUrl,
                'description' => $seoDesc,
                'inLanguage' => 'id-ID',
                'applicationCategory' => 'GovernmentApplication',
                'operatingSystem' => 'Any (web-based)',
                'isAccessibleForFree' => false,
                'isPartOf' => ['@'.'id' => 'https://unsri.ac.id/#organization'],
                'publisher' => ['@'.'id' => 'https://unsri.ac.id/#organization'],
            ],
            [
                '@'.'type' => 'WebSite',
                '@'.'id' => $seoUrl.'#website',
                'name' => $seoTitle,
                'url' => $seoUrl,
                'inLanguage' => 'id-ID',
                'isPartOf' => ['@'.'id' => 'https://unsri.ac.id/#organization'],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
