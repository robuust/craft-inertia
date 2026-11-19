<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Console;

use Illuminate\Console\Command;
use Robuust\CraftInertia\Types\TypeScriptGenerator;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/** Writes or checks the consuming project's generated TypeScript declarations. */
final class TypeScriptCommand extends Command
{
    /** {@inheritdoc} */
    protected $signature = 'craft-inertia:types
        {--output=resources/js/types/craft-inertia.d.ts : Local declaration path, relative to the project or absolute}
        {--check : Fail if the declarations are missing or stale without writing files}';

    /** {@inheritdoc} */
    protected $description = 'Generate or check TypeScript declarations for native Craft Inertia page props';

    /**
     * Compare the current declarations and write a new version when requested.
     * @param TypeScriptGenerator $generator Generates declarations from the current Craft field layouts.
     * @param Filesystem $filesystem Creates directories and installs prepared files.
     * @return int Artisan exit code.
     */
    public function handle(TypeScriptGenerator $generator, Filesystem $filesystem): int
    {
        $output = $this->option('output');
        if (!is_string($output) || !str_ends_with($output, '.d.ts') || str_contains($output, '://')
            || str_contains($output, "\0")) {
            $this->error('Specify a local .d.ts file with --output.');
            return self::FAILURE;
        }
        $output = Path::normalize($output);
        if (DIRECTORY_SEPARATOR === '\\' && !preg_match('~^(?:[a-z]:/)?[^/:][^:]*$~iD', $output)) {
            $this->error('On Windows, use a project-relative path or an absolute drive path with --output.');
            return self::FAILURE;
        }

        $path = Path::makeAbsolute($output, base_path());
        $check = $this->option('check');

        try {
            $existing = $this->readOutput($path);
            if ($existing === null && $check) {
                throw new RuntimeException('Declarations are missing. Run craft-inertia:types with the same output path.');
            }
            if ($existing !== null) {
                $firstLine = explode("\n", $existing, 2)[0];
                if ($firstLine !== TypeScriptGenerator::MARKER && $firstLine !== TypeScriptGenerator::MARKER."\r") {
                    throw new RuntimeException('The existing file has no Craft Inertia generator marker on its first line. Choose another output path.');
                }
            }

            $contents = $generator->generate();
            if ($existing === $contents) {
                $this->info("Declarations are current at {$path}");
                return self::SUCCESS;
            }
            if ($check) {
                throw new RuntimeException('Declarations are stale. Run craft-inertia:types with the same output path.');
            }

            $this->writeOutput($filesystem, $path, $contents, $existing);
            if ($this->readOutput($path) !== $contents) {
                throw new RuntimeException('The destination changed while writing declarations. Run the command again.');
            }
            $this->info("Wrote declarations to {$path}");
            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error("{$path}: {$exception->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Read the file without following a target symlink or project subdirectory symlink.
     * @param string $path Absolute declaration path.
     * @return string|null Existing file contents, or null if absent.
     */
    private function readOutput(string $path): ?string
    {
        clearstatcache(true);
        $project = Path::canonicalize(base_path());
        $windows = DIRECTORY_SEPARATOR === '\\';
        $projectKey = $windows ? mb_strtolower($project) : $project;
        $directory = Path::getDirectory($path);
        while (true) {
            $directoryKey = $windows ? mb_strtolower($directory) : $directory;
            if ($directoryKey === $projectKey) {
                break;
            }
            $linked = is_link($directory);
            if ($linked || file_exists($directory)) {
                if (!is_dir($directory) || ($linked && Path::isBasePath($projectKey, $directoryKey))) {
                    throw new RuntimeException("Output parent {$directory} is not a usable directory.");
                }
                if (!$windows && !is_executable($directory)) {
                    throw new RuntimeException("Output parent {$directory} does not allow directory traversal.");
                }
            }

            $parent = Path::getDirectory($directory);
            if ($parent === $directory) {
                break;
            }
            $directory = $parent;
        }

        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new RuntimeException('The output is a symbolic link or another non-file path.');
        }
        if (!file_exists($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('The existing file could not be read.');
        }

        return $contents;
    }

    /**
     * Install complete output and preserve files that appeared or changed during generation.
     * @param Filesystem $filesystem Local filesystem operations.
     * @param string $path Absolute declaration path.
     * @param string $contents Generated declarations.
     * @param string|null $existing Contents before generation, or null if absent.
     */
    private function writeOutput(Filesystem $filesystem, string $path, string $contents, ?string $existing): void
    {
        if ($this->readOutput($path) !== $existing) {
            throw new RuntimeException('The destination changed during generation. Run the command again.');
        }
        if ($existing !== null) {
            $this->updateExisting($path, $contents, $existing);
            return;
        }

        $directory = Path::getDirectory($path);
        $filesystem->mkdir($directory);
        $temporary = $filesystem->tempnam($directory, '.craft-inertia-', '.tmp');

        try {
            if (@file_put_contents($temporary, $contents) !== strlen($contents)
                || !@chmod($temporary, 0666 & ~umask())) {
                throw new RuntimeException('The generated declarations could not be prepared.');
            }
            if ($this->readOutput($path) !== null) {
                throw new RuntimeException('The destination changed during generation. Run the command again.');
            }

            if (!@link($temporary, $path)) {
                $this->createExclusively($path, $contents);
            }
        } finally {
            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * Update only the verified file held by the stream, even if its path is replaced.
     * @param string $path Absolute declaration path.
     * @param string $contents Generated declarations.
     * @param string $existing Previously verified, marker-owned bytes.
     */
    private function updateExisting(string $path, string $contents, string $existing): void
    {
        $stream = @fopen($path, 'r+b');
        if ($stream === false) {
            throw new RuntimeException('The existing declaration file could not be opened for writing.');
        }

        try {
            $opened = @fstat($stream);
            if ($opened === false || ($opened['mode'] & 0170000) !== 0100000 || !@flock($stream, LOCK_EX)) {
                throw new RuntimeException('The existing declaration file could not be locked as a regular file.');
            }
            clearstatcache(true, $path);
            $current = @lstat($path);
            if ($current === false || ($current['mode'] & 0170000) !== 0100000 || $opened['ino'] === 0
                || $opened['dev'] !== $current['dev'] || $opened['ino'] !== $current['ino']
                || @stream_get_contents($stream) !== $existing) {
                throw new RuntimeException('The destination changed during generation. Run the command again.');
            }
            if ($current['nlink'] !== 1) {
                throw new RuntimeException('The declaration file has additional hard links. Choose a separate output file.');
            }

            $length = strlen($contents);
            if (!@rewind($stream) || @fwrite($stream, $contents) !== $length
                || !@ftruncate($stream, $length) || !@fflush($stream)) {
                throw new RuntimeException('The generated declaration file may be incomplete. Inspect it before retrying.');
            }
        } finally {
            $closed = @fclose($stream);
        }

        if (!$closed) {
            throw new RuntimeException('The declaration file could not be closed after writing. Inspect it before retrying.');
        }
    }

    /**
     * Create a destination on a filesystem without hard links.
     * @param string $path Absolute declaration path.
     * @param string $contents Generated declarations.
     */
    private function createExclusively(string $path, string $contents): void
    {
        if ($this->readOutput($path) !== null) {
            throw new RuntimeException('The destination changed during generation. Run the command again.');
        }

        $stream = @fopen($path, 'xb');
        if ($stream === false) {
            throw new RuntimeException('The declaration file could not be created exclusively.');
        }

        $written = @fwrite($stream, $contents) === strlen($contents) && @fflush($stream);
        if (!@fclose($stream) || !$written) {
            throw new RuntimeException('The new declaration file may be incomplete. Inspect it before retrying.');
        }
    }
}
