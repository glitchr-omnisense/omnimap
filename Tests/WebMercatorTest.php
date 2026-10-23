<?php

namespace Omnimap\Tests;

use Omnimap\Model\LatLng;
use Omnimap\Model\Point;
use Omnimap\Model\Size;
use Omnimap\Projection\WebMercator;
use PHPUnit\Framework\TestCase;

final class WebMercatorTest extends TestCase
{
    public function testTheWorldsCorners(): void
    {
        $centre = WebMercator::point(new LatLng(0, 0), 0);
        self::assertEqualsWithDelta([128.0, 128.0], [$centre->x, $centre->y], 1e-9);

        $corner = WebMercator::point(new LatLng(WebMercator::MAX_LATITUDE, -180), 1);
        self::assertEqualsWithDelta([0.0, 0.0], [$corner->x, $corner->y], 1e-6);
    }

    /** The tile of Kyoto at zoom 12: x 3592, y 1622 (the slippy map formula). */
    public function testATileOfKyoto(): void
    {
        $point = WebMercator::point(new LatLng(35.0116, 135.7681), 12);

        self::assertSame([3592, 1622], [(int) floor($point->x / 256), (int) floor($point->y / 256)]);
    }

    public function testThereAndBack(): void
    {
        $point = WebMercator::latLng(WebMercator::point(new LatLng(-33.8688, 151.2093), 9), 9);

        self::assertEqualsWithDelta([-33.8688, 151.2093], [$point->lat, $point->lng], 1e-9);
        self::assertEqualsWithDelta(-180.0, WebMercator::latLng(new Point(0, 0), 3)->lng, 1e-9);
    }

    public function testTheBoxOfAView(): void
    {
        $bounds = WebMercator::bounds(new LatLng(0, 0), 1, new Size(512, 512));

        self::assertEqualsWithDelta([-WebMercator::MAX_LATITUDE, -180.0, WebMercator::MAX_LATITUDE, 180.0], array_values($bounds->toArray()), 1e-6);
    }
}
