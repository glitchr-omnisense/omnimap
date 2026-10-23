<?php

namespace Omnimap\Projection;

use Omnimap\Model\Bounds;
use Omnimap\Model\LatLng;
use Omnimap\Model\Point;
use Omnimap\Model\Size;

/**
 * The projection of Google's, OpenStreetMap's and nearly every web map's
 * tiles (EPSG:3857): at zoom z the world is a square of 256 x 2^z pixels,
 * x growing eastward from the antimeridian, y southward from latitude
 * 85.0511 (the square's edge).
 */
final class WebMercator
{
    public const TILE = 256;
    public const MAX_LATITUDE = 85.0511287798066;

    /** The world's width at a zoom, in pixels. */
    public static function worldSize(float $zoom): float
    {
        return self::TILE * 2 ** $zoom;
    }

    /** Where a point lies on the world at a zoom, in pixels. */
    public static function point(LatLng $latLng, float $zoom): Point
    {
        $size = self::worldSize($zoom);
        $sin = sin(deg2rad(max(-self::MAX_LATITUDE, min(self::MAX_LATITUDE, $latLng->lat))));

        return new Point(
            ($latLng->lng + 180.0) / 360.0 * $size,
            (0.5 - log((1 + $sin) / (1 - $sin)) / (4 * \M_PI)) * $size,
        );
    }

    /** The point at a position on the world at a zoom (its longitude not wrapped). */
    public static function latLng(Point $point, float $zoom): LatLng
    {
        $size = self::worldSize($zoom);
        $n = \M_PI - 2 * \M_PI * $point->y / $size;

        return new LatLng(
            max(-90.0, min(90.0, rad2deg(atan(sinh($n))))),
            $point->x / $size * 360.0 - 180.0,
        );
    }

    /** The box an image of this size shows around this centre at this zoom. */
    public static function bounds(LatLng $center, int $zoom, Size $size): Bounds
    {
        $middle = self::point($center, $zoom);
        $southWest = self::latLng(new Point($middle->x - $size->width / 2, $middle->y + $size->height / 2), $zoom);
        $northEast = self::latLng(new Point($middle->x + $size->width / 2, $middle->y - $size->height / 2), $zoom);

        return new Bounds($southWest, $northEast);
    }
}
