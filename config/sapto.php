<?php

return [

    // Kunci nilai TS (tahun akademik penuh terakhir) saat prodi mengajukan
    // akreditasi untuk tahun tertentu; kosong = dihitung otomatis. Lihat
    // App\Services\SaptoTsResolver.
    //'ts' => env('SAPTO_TS'),
    'ts' => 2026,

    'institution_keyword' => env('SAPTO_PERGURUAN_TINGGI_KEYWORD', 'Universitas Sriwijaya'),

    // Visibilitas menu LKPS mengikuti jenjang program studi yang sedang
    // menjadi lingkup pengguna. Rules dievaluasi di sidebar; backend tetap
    // menentukan lingkup data berdasarkan SaptoUserContext.
    'table_visibility' => [
        'mahasiswa-asing' => ['exclude_levels' => ['D3']],
        'dosen-industri' => ['only_levels' => ['D3']],
        'publikasi-ilmiah-dtps' => ['exclude_levels' => ['D3']],
        'pagelaran-dtps' => ['only_levels' => ['D3']],
        'sitasi-dtps' => ['exclude_levels' => ['D3']],
        'produk-jasa-dtps' => ['only_levels' => ['D3']],
        'penelitian-melibatkan-mahasiswa' => ['exclude_levels' => ['D3']],
        'penelitian-rujukan-tema' => ['exclude_levels' => ['D3', 'S1']],
        'pkm-melibatkan-mahasiswa' => ['only_levels' => ['D3']],
        'prestasi-non-akademik' => ['only_levels' => ['D3']],
    ],

    'api' => [
        'program_studi_max_pages' => (int) env('SAPTO_API_PRODI_MAX_PAGES', 100),
        'data_max_pages' => (int) env('SAPTO_API_DATA_MAX_PAGES', 10),
        'activity_lookup_limit' => (int) env('SAPTO_API_ACTIVITY_LOOKUP_LIMIT', 600),
        'activity_pool_size' => (int) env('SAPTO_API_ACTIVITY_POOL_SIZE', 20),
    ],
];
