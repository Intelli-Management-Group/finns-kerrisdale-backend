<?php

namespace App\Support\Media;

/**
 * Read-only scanning helpers used by `php artisan media:audit`.
 * No Laravel dependency.
 */
class MediaScanner
{
    private const SKIP_DIRS = ['node_modules', 'vendor', 'dist', 'build', '.git', '.next', 'coverage', 'storage'];

    public function __construct(private ImageUrlResolver $resolver)
    {
    }

    /**
     * Find image references in frontend/backend source files.
     *
     * @return array<int, array{file:string,line:int,ref:string,status:string,proposed:?string,note:?string}>
     */
    public function scanCode(array $paths, array $extensions): array
    {
        $findings = [];
        $extRe = '/\.(' . implode('|', array_map(fn ($e) => preg_quote($e, '/'), $extensions)) . ')$/i';

        foreach ($paths as $root) {
            if (!is_dir($root) && !is_file($root)) {
                continue;
            }
            foreach ($this->files($root) as $file) {
                if (!preg_match($extRe, $file) || str_contains($file, '.min.')) {
                    continue;
                }
                $lines = @file($file, FILE_IGNORE_NEW_LINES);
                if ($lines === false) {
                    continue;
                }
                foreach ($lines as $i => $line) {
                    foreach ($this->refsInLine($line) as $ref) {
                        $findings[] = ['file' => $file, 'line' => $i + 1] + $this->classify($ref);
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * Problems in public/uploads that make existing files fail to load.
     *
     * @return array<int, array{level:string,path:string,message:string}>
     */
    public function checkUploadsFolder(string $uploadsRoot, int $maxPermissionReports = 25): array
    {
        $issues = [];

        if (!is_dir($uploadsRoot)) {
            return [['level' => 'error', 'path' => $uploadsRoot, 'message' => 'Uploads folder does not exist.']];
        }

        $permReports = 0;
        $permTotal = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($uploadsRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $item) {
            $path = $item->getPathname();
            $name = $item->getFilename();

            if ($item->isFile() && strtolower($name) === '.htaccess') {
                $issues[] = ['level' => 'error', 'path' => $path,
                    'message' => '.htaccess copied from WordPress (often a security-plugin "deny" rule). It can block or rewrite image requests on Apache. Review it; remove if it only contained WordPress rules.'];
                continue;
            }
            if ($item->isFile() && preg_match('/\.(php\d?|phtml|phar)$/i', $name)) {
                $issues[] = ['level' => 'warning', 'path' => $path,
                    'message' => 'Executable PHP file inside public uploads. Not an image problem, but a security risk worth removing.'];
                continue;
            }

            $mode = $item->getPerms();
            $bad = $item->isDir() ? (($mode & 0005) !== 0005) : (($mode & 0004) !== 0004);
            if ($bad) {
                $permTotal++;
                if ($permReports++ < $maxPermissionReports) {
                    $issues[] = ['level' => 'warning', 'path' => $path, 'message' => sprintf(
                        '%s is %s (not world-%s). The web server user may get 403.',
                        $item->isDir() ? 'Directory' : 'File',
                        substr(sprintf('%o', $mode), -4),
                        $item->isDir() ? 'readable/traversable' : 'readable'
                    )];
                }
            }
        }

        if ($permTotal > $maxPermissionReports) {
            $issues[] = ['level' => 'warning', 'path' => $uploadsRoot,
                'message' => ($permTotal - $maxPermissionReports) . ' more permission findings not listed.'];
        }

        return $issues;
    }

    public function classify(string $ref): array
    {
        $info = $this->resolver->inspect($ref);
        $out = [
            'ref' => $ref,
            'status' => $info['status'],
            'proposed' => $info['proposed'] ?? ($info['path'] !== $ref ? $info['path'] : null),
            'note' => $info['note'],
        ];
        if (str_contains($ref, '${')) {
            $out['status'] = 'dynamic';
            $out['note'] = 'Built at runtime; route it through resolveImageUrl() in the frontend.';
            $out['proposed'] = null;
        }
        return $out;
    }

    /** @return string[] */
    private function refsInLine(string $line): array
    {
        $refs = [];

        // CSS url(...) — quoted or not, also when nested inside a JS string.
        if (preg_match_all('/url\(\s*(["\']?)([^"\')\s]+)\1\s*\)/i', $line, $m)) {
            foreach ($m[2] as $s) {
                if ($this->interesting($s)) {
                    $refs[] = $s;
                }
            }
        }

        // Quoted strings / template literals (url(...) wrappers handled above)
        if (preg_match_all('/(["\'`])((?:(?!\1).){3,}?)\1/', $line, $m)) {
            foreach ($m[2] as $s) {
                $s = trim($s);
                if (stripos($s, 'url(') === false && $this->interesting($s)) {
                    $refs[] = $s;
                }
            }
        }

        return array_values(array_unique($refs));
    }

    private function interesting(string $s): bool
    {
        if (preg_match('/\s/', $s) && !str_contains($s, '${')) {
            return false;
        }
        if (stripos($s, 'wp-content/uploads') !== false) {
            return true;
        }
        if ($this->resolver->isUploadReference($s)) {
            return true;
        }
        foreach ($this->resolver->legacyHosts() as $h) {
            if (stripos($s, '//' . $h . '/') !== false && preg_match('/\.(jpe?g|png|gif|webp|avif|svg)(\?|$)/i', $s)) {
                return true;
            }
        }
        return false;
    }

    /** @return \Generator<string> */
    private function files(string $root): \Generator
    {
        if (is_file($root)) {
            yield $root;
            return;
        }
        $dir = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator($dir, function ($current) {
            return !($current->isDir() && in_array($current->getFilename(), self::SKIP_DIRS, true));
        });
        foreach (new \RecursiveIteratorIterator($filter) as $f) {
            if ($f->isFile()) {
                yield $f->getPathname();
            }
        }
    }
}
