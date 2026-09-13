<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Satu endpoint generik untuk seluruh "1 tabel = 1 endpoint GET" di
 * "URL API PD UNSRI.xlsx": GET /api/v1/{table} me-list isi tabel data
 * akademik, baca-saja. Daftar tabel yang boleh diakses (dan pemetaan slug
 * URL -> nama tabel asli) ada di config/pd_unsri_tables.php -- di luar
 * daftar itu selalu 404, jadi request tidak pernah menyentuh tabel
 * sembarangan (users, sessions, dst) lewat parameter URL.
 *
 * Parameter standar sesuai kolom "Parameter standar" di sheet README:
 * page, limit, sort, plus filter per kolom kalau kolomnya memang ada di
 * tabel tersebut.
 */
class PdUnsriTableController extends Controller
{
    private const DEFAULT_LIMIT = 25;

    private const MAX_LIMIT = 200;

    /** Nama parameter query yang bukan filter kolom. */
    private const RESERVED_PARAMS = ['page', 'limit', 'sort'];

    public function index(Request $request, string $table): JsonResponse
    {
        $map = config('pd_unsri_tables');
        $tableName = $map[$table] ?? null;

        if ($tableName === null || ! Schema::hasTable($tableName)) {
            throw new NotFoundHttpException("Tabel '{$table}' tidak tersedia di API ini.");
        }

        $columns = Schema::getColumnListing($tableName);

        $query = DB::table($tableName);

        foreach ($request->query() as $key => $value) {
            if (in_array($key, self::RESERVED_PARAMS, true) || $value === null || $value === '') {
                continue;
            }

            if (in_array($key, $columns, true)) {
                $query->where($key, $value);
            }
        }

        $sort = (string) $request->query('sort', '');

        if ($sort !== '') {
            $direction = 'asc';

            if (str_starts_with($sort, '-')) {
                $direction = 'desc';
                $sort = substr($sort, 1);
            }

            if (in_array($sort, $columns, true)) {
                $query->orderBy($sort, $direction);
            }
        }

        $limit = (int) $request->query('limit', self::DEFAULT_LIMIT);
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $page = max(1, (int) $request->query('page', 1));

        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'table' => $table,
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
