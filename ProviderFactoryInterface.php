<?php

namespace Omnimap;

/** Builds a provider from its options (a key, a secret, a tile URL...). */
interface ProviderFactoryInterface
{
    /** The name providers are configured with: "google", "openstreetmap"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): ProviderInterface;
}
