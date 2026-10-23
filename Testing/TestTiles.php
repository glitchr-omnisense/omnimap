<?php

namespace Omnimap\Testing;

use Omnimap\Capabilities;
use Omnimap\MapRendererInterface;
use Omnimap\Model\Image;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;
use Omnimap\Static\TileStitcher;
use Omnimap\StaticMapInterface;
use Omnimap\TileSourceInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A provider for tests and the bare script: tiles from
 * https://tiles.example/{z}/{x}/{y}.png (answer them with PlainTiles), a
 * view described as plain data, its image made by TileStitcher.
 */
final class TestTiles implements MapRendererInterface, StaticMapInterface, TileSourceInterface
{
    public function __construct(private readonly HttpClientInterface $http, private readonly ?CacheInterface $cache = null)
    {
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getTitle(): string
    {
        return 'Test tiles';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(renderer: true, staticMap: true, tiles: true, browserHosts: ['tiles.example']);
    }

    public function describe(MapView $view): array
    {
        return ['provider' => 'test', 'view' => $view->toArray(), 'tiles' => ['template' => $this->template()], 'attribution' => $this->attribution()];
    }

    public function fingerprint(MapView $view): string
    {
        return 'test|'.json_encode([$view->center, $view->zoom, $view->markers], \JSON_THROW_ON_ERROR);
    }

    public function attribution(): string
    {
        return '&copy; Test tiles';
    }

    public function image(MapView $view, Size $size, int $scale = 1): Image
    {
        return (new TileStitcher($this, $this->http, $this->cache))->image($view, $size, $scale);
    }

    public function template(): string
    {
        return 'https://tiles.example/{z}/{x}/{y}.png';
    }

    public function url(int $zoom, int $x, int $y): string
    {
        return "https://tiles.example/$zoom/$x/$y.png";
    }

    public function tileSize(): int
    {
        return 256;
    }

    public function maxZoom(): int
    {
        return 19;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return ['User-Agent' => 'omnimap-tests/1.0 (+https://github.com/glitchr-studio/omnimap)'];
    }
}
