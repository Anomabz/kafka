<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Penulis MAHASISWA pada karya di SISTER (sister_detail_publikasi,
 * sister_detail_kekayaan_intelektual).
 *
 * Kolom `penulis` berisi JSON satu entri per penulis. Field `jenis` selalu
 * "Dosen" (tidak bisa dipakai), tetapi penulis mahasiswa punya
 * `id_peserta_didik` dan `nomor_induk_peserta_didik` (NIM) yang terisi,
 * sedangkan dosen punya `id_sdm`. Program studi mahasiswa diambil dari
 * list_mahasiswa (id_mahasiswa = id_peserta_didik, atau nim = NIM).
 *
 * Dipakai Tabel 8.f.1 dan 8.f.4 (luaran yang dihasilkan mahasiswa).
 */
trait ResolvesStudentAuthors
{
    /**
     * @return list<array{nama: string, id_peserta_didik: ?string, nim: ?string}>
     */
    protected function studentAuthors(?string $json): array
    {
        $decoded = json_decode((string) $json, true);

        if (! is_array($decoded)) {
            return [];
        }

        $students = [];

        foreach ($decoded as $author) {
            if (! is_array($author)) {
                continue;
            }

            $pd = trim((string) ($author['id_peserta_didik'] ?? ''));
            $nim = trim((string) ($author['nomor_induk_peserta_didik'] ?? ''));

            if ($pd === '' && $nim === '') {
                continue;
            }

            $students[] = [
                'nama' => trim((string) ($author['nama'] ?? '')),
                'id_peserta_didik' => $pd !== '' ? $pd : null,
                'nim' => $nim !== '' ? $nim : null,
            ];
        }

        return $students;
    }

    /**
     * Prodi tiap mahasiswa, dikunci 'pd:<id_peserta_didik>' dan 'nim:<nim>'.
     *
     * @param  list<array{nama: string, id_peserta_didik: ?string, nim: ?string}>  $students
     * @return array<string, array<int, string>> kunci => daftar id_prodi
     */
    protected function studentProdiMap(array $students): array
    {
        $pds = array_values(array_unique(array_filter(array_column($students, 'id_peserta_didik'))));
        $nims = array_values(array_unique(array_filter(array_column($students, 'nim'))));
        $map = [];

        foreach (array_chunk($pds, 500) as $chunk) {
            foreach (DB::table('list_mahasiswa')->whereIn('id_mahasiswa', $chunk)->whereNotNull('id_prodi')->get(['id_mahasiswa', 'id_prodi']) as $row) {
                $map['pd:'.$row->id_mahasiswa][] = (string) $row->id_prodi;
            }
        }

        foreach (array_chunk($nims, 500) as $chunk) {
            foreach (DB::table('list_mahasiswa')->whereIn('nim', $chunk)->whereNotNull('id_prodi')->get(['nim', 'id_prodi']) as $row) {
                $map['nim:'.$row->nim][] = (string) $row->id_prodi;
            }
        }

        return array_map(fn (array $ids): array => array_values(array_unique($ids)), $map);
    }

    /**
     * Mahasiswa penulis yang berada di lingkup (null = seluruh PT).
     *
     * @param  list<array{nama: string, id_peserta_didik: ?string, nim: ?string}>  $students
     * @param  array<string, array<int, string>>  $prodiMap
     * @param  array<int, string>|null  $scopeProdiIds
     * @return list<array{nama: string, id_peserta_didik: ?string, nim: ?string}>
     */
    protected function studentsInScope(array $students, array $prodiMap, ?array $scopeProdiIds): array
    {
        if ($scopeProdiIds === null) {
            return $students;
        }

        $scope = array_flip($scopeProdiIds);

        return array_values(array_filter($students, function (array $s) use ($prodiMap, $scope): bool {
            $prodi = array_merge(
                $s['id_peserta_didik'] !== null ? ($prodiMap['pd:'.$s['id_peserta_didik']] ?? []) : [],
                $s['nim'] !== null ? ($prodiMap['nim:'.$s['nim']] ?? []) : [],
            );

            foreach ($prodi as $id) {
                if (isset($scope[$id])) {
                    return true;
                }
            }

            return false;
        }));
    }
}
