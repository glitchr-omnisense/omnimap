<?php

namespace Omnimap\Policy;

use Omnimap\TileSourceInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's tiles behind its guard: url() - a tile's address, which its
 * caller is about to fetch - is the operation "url"; the template, sizes,
 * attribution and headers reach nothing and are not counted.
 *
 * @extends Guarded<TileSourceInterface>
 */
final class GuardedTiles extends Guarded implements TileSourceInterface
{
    public function __construct(TileSourceInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function template(): string
    {
        return $this->provider->template();
    }

    public function url(int $zoom, int $x, int $y): string
    {
        return $this->guard->call('url', fn () => $this->provider->url($zoom, $x, $y));
    }

    public function tileSize(): int
    {
        return $this->provider->tileSize();
    }

    public function maxZoom(): int
    {
        return $this->provider->maxZoom();
    }

    public function attribution(): string
    {
        return $this->provider->attribution();
    }

    public function headers(): array
    {
        return $this->provider->headers();
    }
}
