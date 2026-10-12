<?php

namespace App\Console\Commands;

use App\Support\Media\ImageContentRewriter;
use App\Support\Media\ImageUrlResolver;
use App\Support\Media\MediaScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Find (and optionally fix) broken image references after the WordPress move.
 *
 *   php artisan media:audit                 # uploads folder + DB + code, read-only
 *   php artisan media:audit --db --table=posts
 *   php artisan media:audit --code=../frontend/src
 *   php artisan media:audit --report=storage/app/media-report.csv
 *   php artisan media:audit --db --write    # rewrite DB values whose files exist
 *   php artisan media:audit --rollback=storage/logs/media-rewrite-20261012-101500.json
 */
class MediaAudit extends Command
{
    protected $signature = 'media:audit
        {--db : Scan the database columns listed in config/media.php}
        {--code=* : Scan source folders (defaults to media.audit.code_paths)}
        {--files : Check public/uploads for .htaccess, PHP files and permissions}
        {--table=* : Limit the DB scan to these tables}
        {--write : Rewrite DB values (only references whose file exists)}
        {--force : Skip the confirmation prompt for --write}
        {--report= : Write every finding to this CSV file}
        {--rollback= : Restore DB values from a rewrite log}
        {--show=50 : Max findings printed per section}';

    protected $description = 'Audit and repair image URLs left over from the WordPress migration';

    private array $rows = [];

    public function handle(ImageUrlResolver $resolver, ImageContentRewriter $rewriter): int
    {
        if ($log = $this->option('rollback')) {
            return $this->rollback($log);
        }

        $scanner = new MediaScanner($resolver);
        $codeOpt = $this->option('code');
        $all = !$this->option('db') && !$this->option('files') && empty($codeOpt);

        $this->line('Uploads folder: <info>' . public_path($resolver->uploadsDir()) . '</info>');
        $this->line('Base URL:       <info>' . (config('media.base_url') ?: '(root-relative)') . '</info>');
        $this->line('Legacy hosts:   <info>' . (implode(', ', $resolver->legacyHosts()) ?: '(none configured: any host is treated as legacy for /wp-content/uploads/)') . '</info>');
        $this->newLine();

        $problems = 0;

        if ($all || $this->option('files')) {
            $problems += $this->auditFiles($scanner, public_path($resolver->uploadsDir()));
        }
        if ($all || $this->option('db')) {
            $problems += $this->auditDatabase($rewriter);
        }
        if ($all || !empty($codeOpt)) {
            $paths = !empty($codeOpt) && $codeOpt !== [null]
                ? $codeOpt
                : config('media.audit.code_paths', []);
            $paths = array_map(fn ($p) => str_starts_with($p, '/') ? $p : base_path($p), array_filter($paths));
            $problems += $this->auditCode($scanner, $paths);
        }

        if ($path = $this->option('report')) {
            $this->writeCsv($path);
        }

        $this->newLine();
        if ($problems === 0) {
            $this->info('No broken image references found.');
            return self::SUCCESS;
        }
        $this->warn("{$problems} reference(s) or folder issue(s) need attention.");
        return self::FAILURE;
    }

    // --- uploads folder ----------------------------------------------------

    private function auditFiles(MediaScanner $scanner, string $uploadsRoot): int
    {
        $this->info('== Uploads folder ==');
        $issues = $scanner->checkUploadsFolder($uploadsRoot);
        foreach ($issues as $i) {
            $this->line(sprintf('  <%s>%s</> %s', $i['level'] === 'error' ? 'error' : 'comment', strtoupper($i['level']), $i['path']));
            $this->line('        ' . $i['message']);
            $this->rows[] = ['files', $i['path'], '', $i['level'], '', '', $i['message']];
        }
        if (!$issues) {
            $this->line('  OK');
        }
        $this->newLine();
        return count($issues);
    }

    // --- database ----------------------------------------------------------

    private function auditDatabase(ImageContentRewriter $rewriter): int
    {
        $this->info('== Database ==');
        $targets = config('media.audit.columns', []);
        if ($only = $this->option('table')) {
            $targets = array_intersect_key($targets, array_flip($only));
        }
        if (!$targets) {
            $this->warn('  No columns configured. Add them under media.audit.columns in config/media.php.');
            $this->newLine();
            return 0;
        }

        $write = (bool) $this->option('write');
        if ($write && !$this->option('force')
            && !$this->confirm('Rewrite image references in the database? Back up the DB first. A rollback log will also be written.')) {
            $write = false;
        }

        $changes = [];
        $counts = [];
        $problems = 0;
        $shown = 0;
        $max = (int) $this->option('show');

        foreach ($targets as $table => $spec) {
            $pk = $spec['primary_key'] ?? 'id';
            $columns = array_values(array_filter(
                $spec['columns'] ?? [],
                fn ($c) => Schema::hasColumn($table, $c)
            ));
            if (!Schema::hasTable($table) || !$columns) {
                $this->warn("  Skipping {$table}: table or columns not found.");
                continue;
            }

            DB::table($table)->select(array_merge([$pk], $columns))
                ->chunkById(500, function ($records) use ($table, $pk, $columns, $rewriter, $write, &$changes, &$counts, &$problems, &$shown, $max) {
                    foreach ($records as $record) {
                        $update = [];
                        foreach ($columns as $col) {
                            $old = $record->{$col};
                            if ($old === null || $old === '') {
                                continue;
                            }
                            $new = $rewriter->rewrite((string) $old, ImageContentRewriter::MODE_PATH);
                            $refs = $this->uniqueRefs($rewriter->lastReferences());

                            foreach ($refs as $ref) {
                                $counts[$ref['status']] = ($counts[$ref['status']] ?? 0) + 1;
                                $where = "{$table}.{$col} #{$record->{$pk}}";
                                $this->rows[] = ['db', $where, $ref['input'], $ref['status'], $ref['path'] ?? '', $ref['proposed'] ?? '', $ref['note'] ?? ''];
                                if ($this->isProblem($ref['status'])) {
                                    $problems++;
                                    if ($shown++ < $max) {
                                        $this->line("  <comment>{$ref['status']}</comment> {$where}: {$ref['input']}");
                                        if (!empty($ref['note'])) {
                                            $this->line('        ' . $ref['note']);
                                        }
                                    }
                                }
                            }

                            if ($new !== $old) {
                                $update[$col] = $new;
                                $changes[] = ['table' => $table, 'pk' => $pk, 'id' => $record->{$pk}, 'column' => $col, 'old' => $old, 'new' => $new];
                            }
                        }
                        if ($write && $update) {
                            DB::table($table)->where($pk, $record->{$pk})->update($update);
                        }
                    }
                }, $pk);
        }

        $this->printCounts($counts);
        $this->line(sprintf('  %d value(s) %s.', count($changes), $write ? 'rewritten' : 'would be rewritten (dry run; add --write)'));

        if ($write && $changes) {
            $log = storage_path('logs/media-rewrite-' . date('Ymd-His') . '.json');
            file_put_contents($log, json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->line("  Rollback log: <info>{$log}</info>");
        }
        $this->newLine();

        return $problems;
    }

    private function rollback(string $log): int
    {
        if (!is_file($log)) {
            $this->error("Log not found: {$log}");
            return self::FAILURE;
        }
        $changes = json_decode((string) file_get_contents($log), true) ?: [];
        if (!$this->option('force') && !$this->confirm('Restore ' . count($changes) . ' value(s)?')) {
            return self::FAILURE;
        }
        DB::transaction(function () use ($changes) {
            foreach ($changes as $c) {
                DB::table($c['table'])->where($c['pk'], $c['id'])->update([$c['column'] => $c['old']]);
            }
        });
        $this->info('Restored ' . count($changes) . ' value(s).');
        return self::SUCCESS;
    }

    // --- code --------------------------------------------------------------

    private function auditCode(MediaScanner $scanner, array $paths): int
    {
        $this->info('== Source code ==');
        $existing = array_filter($paths, fn ($p) => file_exists($p));
        foreach (array_diff($paths, $existing) as $p) {
            $this->line("  <comment>skip</comment> {$p} (not found)");
        }

        $findings = $scanner->scanCode($existing, config('media.audit.code_extensions', ['js', 'jsx', 'ts', 'tsx', 'css', 'scss']));
        $counts = [];
        $problems = 0;
        $shown = 0;
        $max = (int) $this->option('show');

        foreach ($findings as $f) {
            $counts[$f['status']] = ($counts[$f['status']] ?? 0) + 1;
            $where = $f['file'] . ':' . $f['line'];
            $this->rows[] = ['code', $where, $f['ref'], $f['status'], '', $f['proposed'] ?? '', $f['note'] ?? ''];

            // In code, anything not already in canonical /uploads/ form needs an edit.
            $needsEdit = $f['status'] !== ImageUrlResolver::OK && $f['status'] !== ImageUrlResolver::EXTERNAL
                && $f['status'] !== ImageUrlResolver::UNCHANGED;
            if ($needsEdit) {
                $problems++;
                if ($shown++ < $max) {
                    $this->line("  <comment>{$f['status']}</comment> {$where}");
                    $this->line("        {$f['ref']}" . ($f['proposed'] ? "  ->  {$f['proposed']}" : ''));
                    if (!empty($f['note']) && $f['status'] !== ImageUrlResolver::MAPPED) {
                        $this->line('        ' . $f['note']);
                    }
                }
            }
        }

        $this->printCounts($counts);
        $this->newLine();
        return $problems;
    }

    // --- helpers -----------------------------------------------------------

    private function isProblem(string $status): bool
    {
        return in_array($status, [
            ImageUrlResolver::MISSING,
            ImageUrlResolver::UNREADABLE,
            ImageUrlResolver::UNMAPPABLE,
        ], true);
    }

    private function uniqueRefs(array $refs): array
    {
        $seen = [];
        foreach ($refs as $r) {
            $seen[$r['input'] . '|' . $r['status']] = $r;
        }
        return array_values($seen);
    }

    private function printCounts(array $counts): void
    {
        if (!$counts) {
            $this->line('  No image references found.');
            return;
        }
        ksort($counts);
        $this->line('  ' . implode('  ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($counts), $counts)));
    }

    private function writeCsv(string $path): void
    {
        $path = str_starts_with($path, '/') ? $path : base_path($path);
        @mkdir(dirname($path), 0775, true);
        $fh = fopen($path, 'w');
        fputcsv($fh, ['source', 'location', 'reference', 'status', 'resolved_path', 'proposed', 'note']);
        foreach ($this->rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);
        $this->line("Report written to <info>{$path}</info>");
    }
}
