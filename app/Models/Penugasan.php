<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Hak akses SAPTO: satu baris = "orang ini boleh masuk sebagai <peran> untuk
 * <lingkup>". Admin mengelolanya lewat /admin/peran.
 *
 * Akun admin SAPTO sengaja TIDAK ada di sini melainkan tetap di tabel `users`
 * (is_admin): begitu daftar admin ikut bisa disunting dari UI, satu salah
 * hapus bisa mengunci semua orang keluar tanpa jalan balik.
 */
class Penugasan extends Model
{
    use HasFactory;

    protected $table = 'penugasan';

    /** Urut dari yang paling luas — dipakai saat satu orang punya lebih dari satu peran. */
    public const PERAN = ['rektor', 'dekan', 'korprodi'];

    public const LABEL_PERAN = [
        'rektor' => 'Rektor',
        'dekan' => 'Dekan',
        'korprodi' => 'Korprodi',
    ];

    protected $fillable = [
        'peran',
        'nip',
        'email',
        'nama',
        'lingkup',
        'dibuat_oleh',
    ];

    /**
     * Baris yang cocok dengan identitas SSO, lewat NIP ATAU email.
     *
     * Kalau kedua kunci kosong, kueri TIDAK boleh dijalankan sama sekali:
     * where() dengan closure kosong akan cocok ke seluruh isi tabel dan
     * memberi akses ke sembarang akun SSO.
     */
    public function scopeUntukIdentitas(Builder $query, ?string $nip, ?string $email): Builder
    {
        $nip = trim((string) $nip);
        $email = trim((string) $email);

        if ($nip === '' && $email === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($nip, $email): void {
            if ($nip !== '') {
                $q->orWhere('nip', $nip);
            }
            if ($email !== '') {
                $q->orWhere('email', $email);
            }
        });
    }

    /**
     * Lingkup dalam bentuk siap tampil: nama prodi/fakultas, keterangan kecil
     * di bawahnya, dan apakah lingkup itu benar-benar ada di data referensi.
     * Peta referensi dioper dari luar supaya satu halaman berisi ratusan baris
     * cukup membaca data prodi sekali.
     *
     * @param  \Illuminate\Support\Collection<string, array>  $prodiByKode
     * @param  \Illuminate\Support\Collection<string, array>  $fakultasById
     * @return array{nama: string, sub: ?string, ada: bool}
     */
    public function lingkupInfo($prodiByKode, $fakultasById): array
    {
        return match ($this->peran) {
            'korprodi' => ($prodi = $prodiByKode->get((string) $this->lingkup))
                ? ['nama' => $prodi['label'], 'sub' => $prodi['fakultas'], 'ada' => true]
                : ['nama' => 'Prodi '.$this->lingkup, 'sub' => 'Tidak ada di data referensi', 'ada' => false],
            'dekan' => ($fakultas = $fakultasById->get((string) $this->lingkup))
                ? ['nama' => $fakultas['nama'], 'sub' => null, 'ada' => true]
                : ['nama' => 'Fakultas '.$this->lingkup, 'sub' => 'Tidak ada di data referensi', 'ada' => false],
            default => ['nama' => 'Seluruh universitas', 'sub' => null, 'ada' => true],
        };
    }

    /** "Korprodi · S1 Sistem Komputer" — untuk teks konfirmasi, log, dan pencarian. */
    public function label($prodiByKode, $fakultasById): string
    {
        $info = $this->lingkupInfo($prodiByKode, $fakultasById);

        return (self::LABEL_PERAN[$this->peran] ?? $this->peran).' · '.$info['nama'].($info['ada'] ? '' : ' (tidak ditemukan)');
    }

    /**
     * Inisial untuk avatar: gelar di depan ("Dr.", "Ir.", "Prof.") dan gelar
     * di belakang koma dilewati, jadi "Dr. Ir. Agus Hermawan, MT" -> "AH".
     */
    public static function inisial(?string $nama, ?string $cadangan = null): string
    {
        $inti = trim(explode(',', (string) $nama)[0]);
        $kata = array_values(array_filter(
            preg_split('/\s+/', $inti) ?: [],
            fn (string $w): bool => $w !== '' && ! str_ends_with($w, '.') && ctype_alpha($w[0]),
        ));

        $huruf = implode('', array_map(fn (string $w): string => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($kata, 0, 2)));

        if ($huruf !== '') {
            return $huruf;
        }

        return mb_strtoupper(mb_substr((string) $cadangan, 0, 2)) ?: '?';
    }

    /**
     * Peran terluas dari sekumpulan penugasan — rektor mengalahkan dekan,
     * dekan mengalahkan korprodi. Dengan begini orang yang merangkap tidak
     * perlu memilih peran tiap kali masuk.
     */
    public static function peranTerluas(iterable $penugasans): ?string
    {
        $dimiliki = [];

        foreach ($penugasans as $penugasan) {
            $dimiliki[] = is_array($penugasan) ? ($penugasan['peran'] ?? '') : $penugasan->peran;
        }

        foreach (self::PERAN as $peran) {
            if (in_array($peran, $dimiliki, true)) {
                return $peran;
            }
        }

        return null;
    }
}
