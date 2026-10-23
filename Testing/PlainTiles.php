<?php

namespace Omnimap\Testing;

/**
 * Tiles drawn here, for a test or the harness's bare script: a tile of one
 * colour per zoom, x and y, its coordinates written on it - the image a
 * RecordedHttpClient answers with.
 */
final class PlainTiles
{
    public static function png(int $zoom, int $x, int $y, int $size = 256): string
    {
        $tile = imagecreatetruecolor(max(1, $size), max(1, $size));
        imagefill($tile, 0, 0, (int) imagecolorallocate($tile, abs(40 * $x + 60) % 256, abs(40 * $y + 90) % 256, abs(30 * $zoom + 120) % 256));
        imagestring($tile, 3, 8, 8, "$zoom/$x/$y", (int) imagecolorallocate($tile, 255, 255, 255));
        ob_start();
        imagepng($tile);

        return (string) ob_get_clean();
    }

    /** The answer RecordedHttpClient gives for ".../{z}/{x}/{y}.png". */
    public static function answer(): \Closure
    {
        return static function (string $method, string $url): array {
            if (!preg_match('~/(\d+)/(\d+)/(\d+)\.png$~', $url, $m)) {
                return [404, ''];
            }

            return [200, self::png((int) $m[1], (int) $m[2], (int) $m[3]), ['content-type' => ['image/png'], 'cache-control' => ['max-age=91080, stale-while-revalidate=604800, stale-if-error=604800']]];
        };
    }
}
