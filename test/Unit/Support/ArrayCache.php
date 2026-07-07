<?php

namespace Rede\Tests\Support;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Minimal in-memory PSR-16 cache double for unit tests. It records the TTL used
 * on the last set() per key so tests can assert the cache write behaviour.
 */
final class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $storage = [];

    /**
     * @var array<string, int|null>
     */
    public array $ttls = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->storage[$key] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->storage[$key] = $value;
        $this->ttls[$key] = $ttl instanceof DateInterval ? null : $ttl;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->storage[$key], $this->ttls[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->storage = [];
        $this->ttls = [];

        return true;
    }

    /**
     * @param iterable<string> $keys
     *
     * @return array<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[(string)$key] = $this->get((string)$key, $default);
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string)$key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string)$key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }
}
