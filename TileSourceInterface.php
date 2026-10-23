<?php

namespace Omnimap;

/**
 * Square tiles of the Web Mercator grid, one URL each: what Leaflet draws
 * and what Static\TileStitcher puts together.
 */
interface TileSourceInterface extends ProviderInterface
{
    /** The URL template, {z}/{x}/{y} in it: the browser's library fills it. */
    public function template(): string;

    public function url(int $zoom, int $x, int $y): string;

    /** In pixels: 256 nearly always. */
    public function tileSize(): int;

    public function maxZoom(): int;

    /** As HTML. */
    public function attribution(): string;

    /**
     * The headers a request for a tile must carry (a User-Agent naming the application, say).
     *
     * @return array<string, string>
     */
    public function headers(): array;
}
