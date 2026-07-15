<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Korprodi extends Model
{
    use HasFactory;

    protected $table = 'korprodi';

    protected $fillable = [
        'nip',
        'nama',
        'kode_program_studi',
        'confidence',
    ];
}
