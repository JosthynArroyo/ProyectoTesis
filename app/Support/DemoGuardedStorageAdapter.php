<?php

namespace App\Support;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;

class DemoGuardedStorageAdapter implements FilesystemAdapter
{
    /**
     * In-memory cache for ephemeral writes during demo requests.
     *
     * @var array<string, string>
     */
    private array $ephemeralWrites = [];

    public function __construct(
        private readonly FilesystemAdapter $innerAdapter,
        private readonly array $config = [],
        private readonly array $persistentPrefixes = []
    ) {}

    public function fileExists(string $location): bool
    {
        return isset($this->ephemeralWrites[$location]) || $this->innerAdapter->fileExists($location);
    }

    public function directoryExists(string $location): bool
    {
        return $this->innerAdapter->directoryExists($location);
    }

    public function write(string $location, string $contents, Config $config): void
    {
        if ($this->shouldPersist($location)) {
            $this->innerAdapter->write($location, $contents, $config);

            return;
        }

        $this->ephemeralWrites[$location] = $contents;
    }

    public function writeStream(string $location, $contents, Config $config): void
    {
        if ($this->shouldPersist($location)) {
            $this->innerAdapter->writeStream($location, $contents, $config);

            return;
        }

        $this->ephemeralWrites[$location] = stream_get_contents($contents);
    }

    public function read(string $location): string
    {
        if (isset($this->ephemeralWrites[$location])) {
            return $this->ephemeralWrites[$location];
        }

        return $this->innerAdapter->read($location);
    }

    public function readStream(string $location)
    {
        if (isset($this->ephemeralWrites[$location])) {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $this->ephemeralWrites[$location]);
            rewind($stream);

            return $stream;
        }

        return $this->innerAdapter->readStream($location);
    }

    public function delete(string $location): void
    {
        if ($this->shouldPersist($location)) {
            $this->innerAdapter->delete($location);

            return;
        }

        unset($this->ephemeralWrites[$location]);
        // Protected: Do NOT delete from real persistent storage!
    }

    public function deleteDirectory(string $prefix): void
    {
        if ($this->shouldPersist($prefix)) {
            $this->innerAdapter->deleteDirectory($prefix);

            return;
        }

        foreach (array_keys($this->ephemeralWrites) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->ephemeralWrites[$key]);
            }
        }
        // Protected: Do NOT delete from real persistent storage!
    }

    public function createDirectory(string $location, Config $config): void
    {
        if ($this->shouldPersist($location)) {
            $this->innerAdapter->createDirectory($location, $config);

            return;
        }

        // Ephemeral in-memory operation
    }

    public function setVisibility(string $path, string $visibility): void
    {
        if ($this->shouldPersist($path)) {
            $this->innerAdapter->setVisibility($path, $visibility);

            return;
        }

        // Ephemeral in-memory operation
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        if (isset($this->ephemeralWrites[$path])) {
            return new FileAttributes($path, null, null, null, 'application/octet-stream');
        }

        return $this->innerAdapter->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        if (isset($this->ephemeralWrites[$path])) {
            return new FileAttributes($path, null, null, time());
        }

        return $this->innerAdapter->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        if (isset($this->ephemeralWrites[$path])) {
            return new FileAttributes($path, strlen($this->ephemeralWrites[$path]));
        }

        return $this->innerAdapter->fileSize($path);
    }

    public function listContents(string $location, bool $deep): iterable
    {
        return $this->innerAdapter->listContents($location, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        if (isset($this->ephemeralWrites[$source])) {
            $this->ephemeralWrites[$destination] = $this->ephemeralWrites[$source];
            unset($this->ephemeralWrites[$source]);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        if (isset($this->ephemeralWrites[$source])) {
            $this->ephemeralWrites[$destination] = $this->ephemeralWrites[$source];
        }
    }

    public function getUrl(string $path): string
    {
        if (method_exists($this->innerAdapter, 'getUrl')) {
            return $this->innerAdapter->getUrl($path);
        }

        $url = $this->config['url'] ?? (config('app.url').'/storage');

        return rtrim($url, '/').'/'.ltrim($path, '/');
    }

    public function path(string $path): string
    {
        if (method_exists($this->innerAdapter, 'path')) {
            return $this->innerAdapter->path($path);
        }

        $root = $this->config['root'] ?? storage_path('app/public');

        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.ltrim($path, '/\\');
    }

    private function shouldPersist(string $location): bool
    {
        $isTestRuntime = defined('PHPUNIT_TESTSUITE')
            || class_exists(\PHPUnit\Framework\TestCase::class, false)
            || app()->runningUnitTests()
            || str_ends_with((string) config('database.connections.mysql.database'), '_test');
        if ($isTestRuntime && ! config('private_documents.persist_demo_receipts_in_tests', false)) {
            return false;
        }

        $normalized = trim(str_replace('\\', '/', $location), '/');

        foreach ($this->persistentPrefixes as $prefix) {
            $normalizedPrefix = trim(str_replace('\\', '/', (string) $prefix), '/');
            if ($normalized === $normalizedPrefix || str_starts_with($normalized, $normalizedPrefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
