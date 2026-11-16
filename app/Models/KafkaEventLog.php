<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Event yang sudah dibaca consumer dari Kafka (`sapto:kafka-consume`) --
 * arsip audit permanen dan sumber daftar "event terbaru" di /admin/kafka.
 */
class KafkaEventLog extends Model
{
    protected $table = 'kafka_event_log';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
