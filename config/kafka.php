<?php

/*
 * Event stream SAPTO lewat Apache Kafka -- lihat docs/kafka.md.
 *
 * PHP di container app tidak bicara protokol Kafka langsung (butuh ekstensi
 * rdkafka yang tidak ada di image composer:2 Alpine, dan membangun ulang
 * image produksi untuk itu berisiko). Semua lalu lintas lewat Confluent
 * Kafka REST Proxy (service `kafka-rest` di docker-compose.yml): HTTP biasa
 * yang bisa dibuat dengan Http facade, dan versinya sama dengan broker.
 */
return [

    // false = semua publish langsung masuk outbox tanpa mencoba jaringan, dan
    // consumer tidak jalan. phpunit.xml memaksa false supaya tes tidak pernah
    // mengirim event ke Kafka produksi (tes dijalankan di container yang sama).
    'enabled' => (bool) env('KAFKA_ENABLED', true),

    'rest_url' => rtrim((string) env('KAFKA_REST_URL', 'http://kafka-rest:8082'), '/'),

    // Pendek dengan sengaja: publish terjadi di tengah login dan sinkronisasi,
    // dan Kafka yang lambat tidak boleh membuat keduanya ikut lambat. Event
    // yang gagal terkirim tidak hilang -- masuk outbox dan dikirim ulang.
    'timeout' => (float) env('KAFKA_TIMEOUT', 2),

    // Setelah satu kegagalan, publish berikutnya selama sekian detik langsung
    // ke outbox tanpa mencoba -- supaya saat proxy mati, tiap login tidak
    // menunggu timeout sendiri-sendiri.
    'circuit_seconds' => (int) env('KAFKA_CIRCUIT_SECONDS', 30),

    'source' => 'sapto',

    /*
     * Topic dibuat eksplisit oleh `php artisan sapto:kafka-setup`, tidak
     * mengandalkan auto-create broker (yang memakai setelan bawaan dan diam-diam
     * membuat topic salah ketik). 1 partisi: volumenya kecil (puluhan event per
     * minggu + login), consumer-nya satu, dan satu partisi menjamin urutan
     * global -- paling mudah dipahami. Replication 1 karena broker cuma satu.
     *
     * Retensi di Kafka hanya penyangga untuk consumer yang tertinggal; arsip
     * permanen ada di tabel kafka_event_log.
     */
    'topics' => [
        'sapto.sync.table_updated' => [
            'description' => 'Satu tabel hasil sinkronisasi PD UNSRI selesai ditukar ke data baru.',
            'partitions' => 1,
            'retention_days' => 30,
        ],
        'sapto.sync.batch_finished' => [
            'description' => 'Satu putaran sinkronisasi PD UNSRI (semua tabel) selesai.',
            'partitions' => 1,
            'retention_days' => 30,
        ],
        'sapto.audit' => [
            'description' => 'Jejak audit: login, perubahan peran/penugasan, penyamaran admin, event uji.',
            'partitions' => 1,
            'retention_days' => 90,
        ],
    ],

    'consumer' => [
        'group' => env('KAFKA_CONSUMER_GROUP', 'sapto-app'),
        // Nama instance tetap supaya proses yang dinyalakan ulang bisa
        // membersihkan instance lamanya di REST Proxy (lihat KafkaConsumer).
        'instance' => env('KAFKA_CONSUMER_INSTANCE', 'sapto-app-1'),
        // Lama REST Proxy menunggu record baru per poll (ms).
        'poll_timeout_ms' => 1000,
        'max_bytes' => 1048576,
    ],

    'outbox' => [
        // Dicoba ulang tiap menit oleh scheduler; setelah sekian percobaan
        // (~1 hari pada jeda maksimum) event ditandai gagal permanen dan
        // tetap terlihat di halaman monitor.
        'max_attempts' => 60,
    ],
];
