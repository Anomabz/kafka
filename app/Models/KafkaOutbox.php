<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Event yang belum berhasil dikirim ke Kafka -- lihat KafkaProducerService
 * dan `sapto:kafka-flush-outbox`.
 */
class KafkaOutbox extends Model
{
    public const PENDING = 'pending';

    public const SENT = 'sent';

    // Percobaan habis. Tidak dihapus supaya tetap terlihat di halaman monitor
    // dan bisa dikirim ulang manual (`sapto:kafka-flush-outbox --dead`).
    public const DEAD = 'dead';

    protected $table = 'kafka_outbox';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
