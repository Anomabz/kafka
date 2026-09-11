<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Notifikasi siaran yang muncul sebagai alert (+ suara) di semua tab SAPTO
 * yang sedang terbuka, dikirim admin lewat perintah Telegram "/notif <pesan>"
 * (lihat routes/web.php). Frontend poll GET /notifications/latest.
 */
class WebNotification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'message',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
