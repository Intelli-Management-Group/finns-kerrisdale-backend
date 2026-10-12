<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Shared search / sort / pagination for admin tables.
     *
     * @param  array<int,string>  $searchable  columns searched with ?q=
     * @param  array<int,string>  $sortable  columns allowed in ?sort= (prefix "-" for descending)
     */
    protected function paginateQuery(Request $request, Builder $query, array $searchable, array $sortable, string $defaultSort = '-id')
    {
        if (($q = trim((string) $request->query('q'))) !== '') {
            $query->where(function (Builder $w) use ($searchable, $q) {
                foreach ($searchable as $col) {
                    $w->orWhere($col, 'like', '%'.addcslashes($q, '%_\\').'%');
                }
            });
        }

        $sort = (string) $request->query('sort', $defaultSort);
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        if (! in_array($col, $sortable, true)) {
            $col = ltrim($defaultSort, '-');
            $dir = str_starts_with($defaultSort, '-') ? 'desc' : 'asc';
        }
        $query->orderBy($col, $dir);
        if ($col !== 'id') {
            $query->orderBy('id', 'desc');
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        return $query->paginate($perPage)->withQueryString();
    }
}
