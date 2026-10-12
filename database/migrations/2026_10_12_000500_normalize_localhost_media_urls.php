<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LOCAL_STORAGE_URL = '~https?:\\\\?/\\\\?/(?:localhost|127\.0\.0\.1)(?::\d+)?(?=/storage/uploads/)~i';

    public function up(): void
    {
        foreach ([
            'posts' => ['content'],
            'pages' => ['content', 'sections'],
            'brands' => ['logo_url'],
            'settings' => ['value'],
        ] as $table => $columns) {
            $this->normalizeColumns($table, $columns);
        }

        if (Schema::hasTable('media')) {
            $this->normalizeColumns('media', ['path']);
            $this->normalizeVariants();
        }
    }

    public function down(): void
    {
        // Removed localhost origins cannot be restored without deployment-specific data.
    }

    private function normalizeColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $columns = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn($table, $column)));
        if (! $columns) {
            return;
        }

        DB::table($table)->select(array_merge(['id'], $columns))
            ->where(function ($query) use ($table, $columns) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%localhost%')
                        ->orWhere($column, 'like', '%127.0.0.1%');
                    if ($table === 'media' && $column === 'path') {
                        $query->orWhere($column, 'like', '%/storage/uploads/%');
                    }
                }
            })
            ->chunkById(500, function ($rows) use ($table, $columns) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $column) {
                        $value = $row->{$column};
                        if ($value === null || $value === '') {
                            continue;
                        }

                        $normalized = $this->normalizeLocalStorageUrl((string) $value);
                        if ($table === 'media' && $column === 'path') {
                            $normalized = preg_replace('#^/?storage/(uploads/)#', '$1', $normalized);
                            $normalized = ltrim($normalized, '/');
                        }
                        if ($normalized !== $value) {
                            $updates[$column] = $normalized;
                        }
                    }
                    if ($updates) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    private function normalizeVariants(): void
    {
        if (! Schema::hasColumn('media', 'variants')) {
            return;
        }

        DB::table('media')->select(['id', 'variants'])->whereNotNull('variants')
            ->where('variants', 'like', '%storage%uploads%')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $variants = json_decode((string) $row->variants, true);
                    if (! is_array($variants)) {
                        continue;
                    }

                    $normalized = $this->normalizeVariantPaths($variants);
                    $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES);
                    if ($encoded !== false && $encoded !== $row->variants) {
                        DB::table('media')->where('id', $row->id)->update(['variants' => $encoded]);
                    }
                }
            });
    }

    private function normalizeVariantPaths(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->normalizeVariantPaths($value);
            } elseif (is_string($value)) {
                $value = $this->normalizeLocalStorageUrl($value);
                $values[$key] = preg_replace('#^/?storage/(uploads/)#', '$1', $value);
            }
        }

        return $values;
    }

    private function normalizeLocalStorageUrl(string $value): string
    {
        while (str_contains($value, '\\/')) {
            $value = str_replace('\\/', '/', $value);
        }

        return preg_replace(self::LOCAL_STORAGE_URL, '', $value);
    }
};
