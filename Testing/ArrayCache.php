<?php

namespace Omnimap\Testing;

use Psr\SimpleCache\CacheInterface;

/** A PSR-16 cache in this process's memory, with what was set kept readable (ttls()). */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, int|null> */
    public array $ttls = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return \array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->values[$key] = $value;
        $this->ttls[$key] = $ttl instanceof \DateInterval ? (int) (new \DateTimeImmutable('@0'))->add($ttl)->format('U') : $ttl;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key], $this->ttls[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = $this->ttls = [];

        return true;
    }

    /** @param iterable<string> $keys @return iterable<string, mixed> */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /** @param iterable<string, mixed> $values */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    /** @param iterable<string> $keys */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->values);
    }
}
