<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dosen extends Model
{
    use HasFactory;

    protected $table = 'dosen';

    protected $fillable = [
        'nama',
        'nidn',
        'nidk',
        'nip',
    ];

    public function bimbinganTugasAkhir(): HasMany
    {
        return $this->hasMany(MahasiswaBimbinganTugasAkhir::class);
    }
}
