<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Notifikasi siaran yang muncul sebagai alert (+ suara) di semua tab SAPTO
 * yang sedang terbuka, dikirim admin lewat perintah Telegram "/notif <pesan>"
 * (lihat routes/web.php). Frontend poll GET /notifications/latest.
 *
 * audience 'admin' = hanya tampil di sesi admin (mis. hasil sinkronisasi PD
 * UNSRI yang dikirim consumer Kafka); null = siaran ke semua orang.
 */
class WebNotification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'message',
        'audience',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
