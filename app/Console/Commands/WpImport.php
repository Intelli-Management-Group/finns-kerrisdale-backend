<?php

namespace App\Console\Commands;

use App\Services\WordPress\WordPressImporter;
use Illuminate\Console\Command;
use Throwable;

class WpImport extends Command
{
    protected $signature = 'wp:import
        {--connection=wordpress : Database connection holding the WordPress tables (see WP_DB_* in .env)}
        {--prefix=wp_ : WordPress table prefix}
        {--uploads= : Path to a local copy of wp-content/uploads}
        {--download : Download images from the live site when they are not found locally}
        {--site-url=https://finnskerrisdaleladies.com : Old site URL (used to match image links in content)}
        {--skip-media : Import text content only (re-uses media imported earlier)}
        {--overwrite-pages : Replace the content of existing pages with the WordPress page-builder content}
        {--dry-run : Rehearse the whole import and print the report without saving anything}';

    protected $description = 'Import pages, posts, categories, tags, authors, images and subscribers from the old WordPress database';

    public function handle(WordPressImporter $importer): int
    {
        if (! $this->option('uploads') && ! $this->option('download') && ! $this->option('skip-media')) {
            $this->warn('No --uploads path and no --download flag: images cannot be imported. Use --skip-media to import text only.');
            if (! $this->confirm('Continue without images?', false)) {
                return self::FAILURE;
            }
        }

        $this->info($this->option('dry-run') ? 'Dry run — nothing will be saved.' : 'Importing from WordPress…');

        try {
            $report = $importer->run([
                'connection' => $this->option('connection'),
                'prefix' => $this->option('prefix'),
                'uploads' => $this->option('uploads'),
                'download' => (bool) $this->option('download'),
                'site_url' => $this->option('site-url'),
                'skip_media' => (bool) $this->option('skip-media'),
                'overwrite_pages' => (bool) $this->option('overwrite-pages'),
                'dry_run' => (bool) $this->option('dry-run'),
            ], fn ($m) => $this->line('  '.$m));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Item', 'Count'], [
            ['Authors', $report['users']],
            ['Categories / tags', $report['categories'].' / '.$report['tags']],
            ['Images imported (already present)', $report['media'].' ('.$report['media_skipped'].')'],
            ['Images failed', count($report['media_failed'])],
            ['Pages created / updated', $report['pages_created'].' / '.$report['pages_updated']],
            ['Posts created / updated', $report['posts_created'].' / '.$report['posts_updated']],
            ['Subscribers added', $report['subscribers']],
            ['Approved comments found (not imported)', $report['comments_found']],
            ['Redirects added for changed slugs', $report['redirects']],
        ]);
        foreach (array_slice($report['media_failed'], 0, 30) as $f) {
            $this->warn('  image failed: '.$f);
        }
        foreach ($report['warnings'] as $w) {
            $this->warn('  '.$w);
        }
        $this->info('Next: run `php artisan site:check` to verify there are no broken images or links.');

        return self::SUCCESS;
    }
}
