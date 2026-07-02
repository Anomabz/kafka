<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MahasiswaBimbinganTugasAkhir extends Model
{
    use HasFactory;

    protected $table = 'mahasiswa_bimbingan_tugas_akhir';

    protected $fillable = [
        'mahasiswa_id',
        'nim',
        'nama_mahasiswa',
        'program_studi_id',
        'dosen_id',
        'judul_tugas_akhir',
        'jenis_tugas_akhir',
        'tahun',
        'status',
        'sumber_data',
    ];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }
}
