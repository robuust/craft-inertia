<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Console;

use Illuminate\Console\Command;
use RuntimeException;

/** Copies the quickstart files into the consuming project on request. */
final class ScaffoldCommand extends Command
{
    /** @var list<string> Frontend destinations relative to the project root. */
    private const array FILES = [
        'resources/views/frontend/app.blade.php',
        'resources/js/app.js',
        'resources/js/pages/Site/Quickstart.vue',
    ];

    /** {@inheritdoc} */
    protected $signature = 'craft-inertia:scaffold
        {--write : Write the scaffold files}
        {--force : Allow replacement of different regular files when writing}';

    /** {@inheritdoc} */
    protected $description = 'Preview or copy the Craft Inertia quickstart files';

    /**
     * Report the full plan and write the requested files after a successful preflight.
     * @return int Artisan exit code.
     */
    public function handle(): int
    {
        $files = [];
        $hasConflicts = false;

        foreach (self::FILES as $relative) {
            try {
                $stub = @file_get_contents(dirname(__DIR__, 2).'/resources/stubs/'.$relative.'.stub');
                if ($stub === false) {
                    throw new RuntimeException('The packaged stub could not be read.');
                }

                $existing = $this->destinationContents($relative);
                if ($existing === $stub) {
                    $this->line("Unchanged {$relative}");
                    continue;
                }
                if ($existing !== null && !$this->option('force')) {
                    $this->line("Keep {$relative} (merge the example manually or preview a replacement with --force)");
                    continue;
                }

                $action = $existing === null ? 'Create' : 'Replace';
                $this->line("{$action} {$relative}");
                $files[$relative] = [$stub, $existing];
            } catch (RuntimeException $exception) {
                $hasConflicts = true;
                $this->error("Conflict {$relative}. {$exception->getMessage()}");
            }
        }

        $this->line('Follow the quickstart for the Blade namespace, plugin configuration, Vite build, and Craft field and template mapping.');
        $this->line('https://github.com/robuust/craft-inertia/blob/main/docs/QUICKSTART.md');

        if ($hasConflicts) {
            $this->error('Resolve the conflicts before writing scaffold files.');
            return self::FAILURE;
        }
        if (!$this->option('write')) {
            $this->info('Preview only. Add --write to apply the plan.');
            return self::SUCCESS;
        }

        foreach ($files as $relative => [$stub, $existing]) {
            try {
                $this->writeFile($relative, $stub, $existing);
                $action = $existing === null ? 'Created' : 'Replaced';
                $this->info("{$action} {$relative}");
            } catch (RuntimeException $exception) {
                $this->error("Could not write {$relative}. {$exception->getMessage()}");
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Inspect the file without following symbolic links inside the project.
     * @param string $relative Destination relative to the project root.
     * @return string|null Existing regular file contents, or null if absent.
     */
    private function destinationContents(string $relative): ?string
    {
        clearstatcache(true);
        $parent = '';
        foreach (explode('/', dirname($relative)) as $segment) {
            $parent .= $segment;
            $directory = base_path($parent);
            if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
                throw new RuntimeException("{$parent} must be a directory without a symbolic link.");
            }
            if (DIRECTORY_SEPARATOR === '/' && is_dir($directory) && !is_executable($directory)) {
                throw new RuntimeException("{$parent} does not allow directory traversal.");
            }
            $parent .= '/';
        }

        $destination = base_path($relative);
        if (is_link($destination) || (file_exists($destination) && !is_file($destination))) {
            throw new RuntimeException('The destination is a symbolic link or another non-file path.');
        }
        if (!file_exists($destination)) {
            return null;
        }

        $contents = @file_get_contents($destination);
        if ($contents === false) {
            throw new RuntimeException('The existing file could not be read.');
        }

        return $contents;
    }

    /**
     * Publish the complete stub after rechecking the destination.
     * @param string $relative Destination relative to the project root.
     * @param string $contents Contents of the packaged stub.
     * @param string|null $existing Contents at preflight, or null if absent.
     */
    private function writeFile(string $relative, string $contents, ?string $existing): void
    {
        if ($this->destinationContents($relative) !== $existing) {
            throw new RuntimeException('The destination changed after planning. Run the command again.');
        }

        $destination = base_path($relative);
        $directory = dirname($destination);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('The destination directory could not be created.');
        }

        $temporary = @tempnam($directory, '.craft-inertia-');
        if ($temporary === false) {
            throw new RuntimeException('A temporary file could not be created.');
        }

        try {
            if (realpath(dirname($temporary)) !== realpath($directory)) {
                throw new RuntimeException('A temporary file could not be created beside the destination.');
            }
            $mode = $existing === null ? 0666 & ~umask() : @fileperms($destination);
            if ($mode === false || @file_put_contents($temporary, $contents) !== strlen($contents)
                || !@chmod($temporary, $mode & 0777)) {
                throw new RuntimeException('The scaffold contents could not be prepared.');
            }
            if ($this->destinationContents($relative) !== $existing) {
                throw new RuntimeException('The destination changed after planning. Run the command again.');
            }

            if ($existing === null) {
                // A hard link publishes a complete file without replacing a concurrent creation.
                if (!@link($temporary, $destination)) {
                    $this->createFileExclusively($relative, $contents);
                }
            } elseif (!@rename($temporary, $destination)) {
                throw new RuntimeException('The prepared file could not be installed at its destination.');
            }
        } finally {
            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * Create a new stub on filesystems where a hard link cannot be made.
     * @param string $relative Destination relative to the project root.
     * @param string $contents Contents of the packaged stub.
     */
    private function createFileExclusively(string $relative, string $contents): void
    {
        if ($this->destinationContents($relative) !== null) {
            throw new RuntimeException('The destination changed after planning. Run the command again.');
        }

        $destination = base_path($relative);
        $stream = @fopen($destination, 'xb');
        if ($stream === false) {
            throw new RuntimeException('The scaffold file could not be created exclusively.');
        }

        $written = @fwrite($stream, $contents) === strlen($contents) && @fflush($stream);
        if (@fclose($stream) && $written) {
            return;
        }

        throw new RuntimeException('The new file may be incomplete. Inspect it before retrying.');
    }
}
