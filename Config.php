<?php

namespace Omnimap;

use Omnimap\Exception\InvalidConfigException;

/**
 * A provider's configuration while its factory builds it: the options
 * given, the factory's defaults under them, and the keys that must be
 * filled.
 *
 * @extends \ArrayObject<string, mixed>
 */
final class Config extends \ArrayObject
{
    /**
     * Sets each key not set yet.
     *
     * @param array<string, mixed> $defaults
     */
    public function defaults(array $defaults): self
    {
        foreach ($defaults as $key => $value) {
            if (!$this->offsetExists($key)) {
                $this[$key] = $value;
            }
        }

        return $this;
    }

    /** @param list<string> $keys */
    public function validateNotEmpty(array $keys): void
    {
        $missing = array_values(array_filter($keys, fn (string $key) => !isset($this[$key]) || '' === $this[$key] || [] === $this[$key]));
        if ($missing) {
            throw new InvalidConfigException(\sprintf('The "%s" provider needs: %s.', $this->string('omnimap.factory_name') ?? '?', implode(', ', $missing)));
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->offsetExists($key) ? $this[$key] : $default;
    }

    /** A string option; null when it is empty. */
    public function string(string $key): ?string
    {
        $value = $this->get($key);

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key);

        return is_numeric($value) ? (float) $value : $default;
    }

    /** A boolean option, as an environment variable gives it too: "1", "true", "yes", "on". */
    public function bool(string $key): bool
    {
        $value = $this->get($key);

        return \is_bool($value) ? $value : \is_scalar($value) && \in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
