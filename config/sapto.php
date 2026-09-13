<?php

return [

    // Kunci nilai TS (tahun akademik penuh terakhir) saat prodi mengajukan
    // akreditasi untuk tahun tertentu; kosong = dihitung otomatis. Lihat
    // App\Services\SaptoTsResolver.
    'ts' => env('SAPTO_TS'),

    'institution_keyword' => env('SAPTO_PERGURUAN_TINGGI_KEYWORD', 'Universitas Sriwijaya'),

    'api' => [
        'program_studi_max_pages' => (int) env('SAPTO_API_PRODI_MAX_PAGES', 100),
        'data_max_pages' => (int) env('SAPTO_API_DATA_MAX_PAGES', 10),
        'activity_lookup_limit' => (int) env('SAPTO_API_ACTIVITY_LOOKUP_LIMIT', 600),
        'activity_pool_size' => (int) env('SAPTO_API_ACTIVITY_POOL_SIZE', 20),
    ],
];
