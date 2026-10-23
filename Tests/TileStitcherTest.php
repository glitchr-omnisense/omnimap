<?php

namespace Omnimap\Tests;

use Omnimap\Exception\ProviderException;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\Model\Size;
use Omnimap\Static\TileStitcher;
use Omnimap\Testing\ArrayCache;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;
use Omnimap\Testing\TestTiles;
use PHPUnit\Framework\TestCase;

final class TileStitcherTest extends TestCase
{
    public function testTheTilesAViewCoversOnceEachWithTheSourcesHeadersThenFromTheCache(): void
    {
        $http = new RecordedHttpClient(PlainTiles::answer());
        $cache = new ArrayCache();
        $tiles = new TestTiles($http, $cache);
        $view = new MapView(new LatLng(35.0116, 135.7681), 12);

        $image = $tiles->image($view, new Size(600, 400));
        self::assertSame(['image/png', '600x400'], [$image->mimeType, (string) $image->size]);
        self::assertSame([600, 400], \array_slice((array) getimagesizefromstring($image->bytes), 0, 2));
        $urls = array_column($http->requests, 'url');
        self::assertCount(\count(array_unique($urls)), $urls, 'each tile once');
        self::assertContains('https://tiles.example/12/3592/1622.png', $urls, 'the tile under the centre');
        self::assertSame(['User-Agent' => 'omnimap-tests/1.0 (+https://github.com/glitchr-studio/omnimap)'], $http->requests[0]['options']['headers']);
        self::assertSame([695880], array_values(array_unique($cache->ttls)), 'max-age plus stale-while-revalidate');

        $before = \count($http->requests);
        $tiles->image($view, new Size(600, 400));
        self::assertCount($before, $http->requests, 'all from the cache');
    }

    public function testTwiceTheDensityTakesTheNextZoom(): void
    {
        $http = new RecordedHttpClient(PlainTiles::answer());
        $image = (new TestTiles($http))->image(new MapView(new LatLng(35.0116, 135.7681), 12), new Size(300, 200), 2);

        self::assertSame('600x400', (string) $image->size);
        self::assertStringStartsWith('https://tiles.example/13/', $http->requests[0]['url']);
    }

    public function testTheWorldRepeatsAndTheMarkersAreDrawn(): void
    {
        $http = new RecordedHttpClient(PlainTiles::answer());
        $image = (new TestTiles($http))->image(new MapView(new LatLng(0, 170), 1, [new Marker(new LatLng(0, -170), null, '#0000ff')]), new Size(700, 500));
        $urls = array_column($http->requests, 'url');
        sort($urls);

        self::assertSame(['https://tiles.example/1/0/0.png', 'https://tiles.example/1/0/1.png', 'https://tiles.example/1/1/0.png', 'https://tiles.example/1/1/1.png'], $urls, 'x 2 is x 0 again');
        $picture = imagecreatefromstring($image->bytes);
        self::assertNotFalse($picture);
        // -170 at zoom 1: 14.2 px east of the antimeridian; the image starts 147.8 px west of it.
        self::assertSame([0, 0, 255], array_values(\array_slice(imagecolorsforindex($picture, imagecolorat($picture, 378, 250)), 0, 3)));
    }

    public function testAnErrorStatus(): void
    {
        $this->expectException(ProviderException::class);
        (new TestTiles(new RecordedHttpClient(static fn () => [403, 'blocked'])))->image(new MapView(new LatLng(0, 0), 3), new Size(10, 10));
    }

    public function testLifetimes(): void
    {
        self::assertSame(91080, TileStitcher::lifetime('max-age=91080'));
        self::assertSame(695880, TileStitcher::lifetime('max-age=91080, stale-while-revalidate=604800, stale-if-error=604800'));
        self::assertSame(604800, TileStitcher::lifetime(''), '7 days when the server says nothing');
    }
}
