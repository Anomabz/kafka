<?php

namespace App\Support;

use Closure;
use Illuminate\Cache\Repository;

use function Illuminate\Support\enum_value;

/**
 * Repository cache yang menjalankan ulang callback remember()/rememberForever()
 * lalu menimpa nilainya. Dipasang (Cache::swap) hanya di dalam proses
 * `sapto:refresh-scope`, jadi hanya key yang disentuh laporan lingkup itu yang
 * dihitung ulang -- cache lingkup/pengguna lain tidak dibuang seperti
 * cache:clear.
 *
 * Tiap key dihitung ulang SEKALI per proses: sub-cache yang dipakai banyak
 * laporan (daftar prodi, roster, daftar tahun) dipanggil berkali-kali dalam
 * satu penyegaran, dan tanpa ini semuanya dihitung ulang tiap panggilan
 * (terukur ~280 detik untuk satu prodi).
 */
class RecomputingCacheRepository extends Repository
{
    /** @var array<string, true> */
    private array $recomputed = [];

    /** @return list<string> Key yang ditulis ulang, untuk log/verifikasi. */
    public function rewritten(): array
    {
        return array_keys($this->recomputed);
    }

    public function rememberWithWarmth($key, $ttl, Closure $callback): array
    {
        $name = (string) enum_value($key);

        if (isset($this->recomputed[$name])) {
            return parent::rememberWithWarmth($key, $ttl, $callback);
        }

        $value = $callback();

        $this->put($key, $value, value($ttl, $value));
        $this->recomputed[$name] = true;

        return [$value, false];
    }

    public function rememberForever($key, Closure $callback)
    {
        $name = (string) enum_value($key);

        if (isset($this->recomputed[$name])) {
            return parent::rememberForever($key, $callback);
        }

        $value = $callback();

        $this->forever($key, $value);
        $this->recomputed[$name] = true;

        return $value;
    }
}
