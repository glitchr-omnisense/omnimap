<?php

namespace Omnimap\Static;

use Omnimap\Exception\ProviderException;
use Omnimap\Exception\UnreachableException;
use Omnimap\Model\Image;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;
use Omnimap\Projection\WebMercator;
use Omnimap\TileSourceInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A map image made on the server from a tile source: the tiles the view
 * covers, fetched (all at once: the HTTP client runs them side by side)
 * with the source's headers, kept in a PSR-16 cache as long as the server's
 * Cache-Control allows (max-age plus stale-while-revalidate; 7 days when it
 * says nothing - OpenStreetMap's tile policy), put together with GD, the
 * markers drawn as dots, the attribution written in the bottom-right corner.
 * The world repeats east and west; above and below it, $background.
 *
 * A scale of 2 takes the tiles of the next zoom: the same view at twice
 * the density.
 */
final class TileStitcher
{
    public function __construct(
        private readonly TileSourceInterface $tiles,
        private readonly HttpClientInterface $http,
        private readonly ?CacheInterface $cache = null,
        private readonly string $background = '#aad3df',
        private readonly string $markerColor = '#d33b3b',
    ) {
    }

    public function image(MapView $view, Size $size, int $scale = 1): Image
    {
        $scale = max(1, min(2, $scale));
        $zoom = min($this->tiles->maxZoom(), $view->zoom + ($scale - 1));
        $tile = $this->tiles->tileSize();
        $out = $size->scaled($scale);
        $centre = WebMercator::point($view->center, $zoom);
        $left = $centre->x - $out->width / 2;
        $top = $centre->y - $out->height / 2;
        // The world in tiles of the source's size at this zoom (a 512-pixel source halves the count).
        $worldTiles = (int) round(WebMercator::worldSize($zoom) / $tile);

        $canvas = imagecreatetruecolor(max(1, $out->width), max(1, $out->height));
        imagefill($canvas, 0, 0, self::color($canvas, $this->background));

        $wanted = [];
        for ($ty = (int) floor($top / $tile); $ty <= (int) floor(($top + $out->height - 1) / $tile); ++$ty) {
            if ($ty < 0 || $ty >= $worldTiles) {
                continue;
            }
            for ($tx = (int) floor($left / $tile); $tx <= (int) floor(($left + $out->width - 1) / $tile); ++$tx) {
                $wanted[] = [$tx, $ty, $this->tiles->url($zoom, (($tx % $worldTiles) + $worldTiles) % $worldTiles, $ty)];
            }
        }
        foreach ($this->fetch(array_unique(array_column($wanted, 2))) as $url => $bytes) {
            $picture = @imagecreatefromstring($bytes);
            if (false === $picture) {
                throw ProviderException::answered($this->tiles->getTitle(), $url, 'something that is not an image');
            }
            foreach ($wanted as [$tx, $ty, $wantedUrl]) {
                if ($wantedUrl === $url) {
                    imagecopy($canvas, $picture, (int) round($tx * $tile - $left), (int) round($ty * $tile - $top), 0, 0, imagesx($picture), imagesy($picture));
                }
            }
        }

        imagealphablending($canvas, true);
        foreach ($view->markers as $marker) {
            $point = WebMercator::point($marker->position, $zoom);
            $x = (int) round(fmod($point->x - $left + 2 * WebMercator::worldSize($zoom), WebMercator::worldSize($zoom)));
            $y = (int) round($point->y - $top);
            $radius = 6 * $scale;
            imagefilledellipse($canvas, $x, $y, 2 * $radius + 4 * $scale, 2 * $radius + 4 * $scale, self::color($canvas, '#ffffff'));
            imagefilledellipse($canvas, $x, $y, 2 * $radius, 2 * $radius, self::color($canvas, $marker->color ?? $this->markerColor));
        }
        $this->attribute($canvas, $scale);

        ob_start();
        imagepng($canvas);

        return new Image((string) ob_get_clean(), 'image/png', $out);
    }

    /**
     * @param array<string> $urls
     *
     * @return \Generator<string, string>
     */
    private function fetch(array $urls): \Generator
    {
        $responses = [];
        foreach ($urls as $url) {
            $cached = $this->cache?->get(self::key($url));
            if (\is_string($cached)) {
                yield $url => $cached;
                continue;
            }
            try {
                $responses[$url] = $this->http->request('GET', $url, ['headers' => $this->tiles->headers()]);
            } catch (HttpException $e) {
                throw new UnreachableException(\sprintf('%s could not be reached for %s: %s', $this->tiles->getTitle(), $url, $e->getMessage()), 0, $e);
            }
        }
        foreach ($responses as $url => $response) {
            try {
                $status = $response->getStatusCode();
                if (200 !== $status) {
                    throw ProviderException::answered($this->tiles->getTitle(), $url, $status);
                }
                $bytes = $response->getContent();
                $headers = $response->getHeaders();
            } catch (HttpException $e) {
                throw new UnreachableException(\sprintf('%s could not be reached for %s: %s', $this->tiles->getTitle(), $url, $e->getMessage()), 0, $e);
            }
            $this->cache?->set(self::key($url), $bytes, self::lifetime($headers['cache-control'][0] ?? ''));
            yield $url => $bytes;
        }
    }

    /** max-age plus stale-while-revalidate, in seconds; 7 days when the server says neither. */
    public static function lifetime(string $cacheControl): int
    {
        $seconds = 0;
        foreach (['max-age', 'stale-while-revalidate'] as $directive) {
            if (preg_match('~(?:^|,)\s*'.$directive.'=(\d+)~i', $cacheControl, $match)) {
                $seconds += (int) $match[1];
            }
        }

        return $seconds > 0 ? $seconds : 604800;
    }

    private function attribute(\GdImage $canvas, int $scale): void
    {
        $text = trim(html_entity_decode(strip_tags($this->tiles->attribution()), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        if ('' === $text) {
            return;
        }
        // GD's own fonts are 8-bit: "©" written "(c)".
        $text = str_replace('©', '(c)', $text);
        $text = (string) preg_replace('~[^\x20-\x7E]~', '', $text);
        $font = 2 === $scale ? 4 : 2;
        $w = imagefontwidth($font) * \strlen($text) + 8;
        $h = imagefontheight($font) + 4;
        $x = imagesx($canvas) - $w;
        $y = imagesy($canvas) - $h;
        imagefilledrectangle($canvas, $x, $y, imagesx($canvas), imagesy($canvas), (int) imagecolorallocatealpha($canvas, 255, 255, 255, 40));
        imagestring($canvas, $font, $x + 4, $y + 2, $text, self::color($canvas, '#333333'));
    }

    private static function color(\GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (3 === \strlen($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        [$red, $green, $blue] = array_map(static fn (string $pair): int => max(0, min(255, (int) hexdec($pair))), str_split(str_pad(substr($hex, 0, 6), 6, '0'), 2));

        return (int) imagecolorallocate($image, $red, $green, $blue);
    }

    private static function key(string $url): string
    {
        return 'omnimap.tile.'.sha1($url);
    }
}
