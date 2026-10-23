<?php

namespace Omnimap\Tests;

use Omnimap\Exception\InvalidArgumentException;
use Omnimap\Model\Bounds;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\Model\Size;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testALatLngWrapsItsLongitudeAndRefusesALatitudePastAPole(): void
    {
        self::assertSame(['lat' => 10.0, 'lng' => -170.0], (new LatLng(10, 190))->wrapped()->toArray());
        self::assertSame(['lat' => 10.0, 'lng' => 170.0], (new LatLng(10, -190))->wrapped()->toArray());
        self::assertSame(['lat' => 0.0, 'lng' => -180.0], (new LatLng(0, 180))->wrapped()->toArray());
        self::assertSame('35.0115754,135.7681441', (string) new LatLng(35.0115754, 135.7681441));
        self::assertSame('0,-0.5', (string) new LatLng(0, -0.5));
        self::assertTrue((new LatLng(1, 2))->equals(LatLng::fromArray(['lat' => '1', 'lng' => 2])));

        $this->expectException(InvalidArgumentException::class);
        new LatLng(90.5, 0);
    }

    public function testBoundsAcrossTheAntimeridian(): void
    {
        $pacific = new Bounds(new LatLng(-10, 170), new LatLng(10, -170));

        self::assertTrue($pacific->contains(new LatLng(0, 179)));
        self::assertTrue($pacific->contains(new LatLng(0, -175)));
        self::assertFalse($pacific->contains(new LatLng(0, 0)));
        self::assertFalse($pacific->contains(new LatLng(20, 179)));
    }

    public function testASizeAndAView(): void
    {
        self::assertSame('640x480', (string) Size::fromString('640x480'));
        self::assertSame('1280x960', (string) Size::fromString('640x480')->scaled(2));

        $view = (new MapView(new LatLng(0, 0), 2, id: 'myMap'))
            ->withMarker(new Marker(new LatLng(1, 2), 'One'))
            ->withOptions('google', ['styles' => []])
            ->withOptions('google', ['map' => ['gestureHandling' => 'none']]);

        self::assertSame(['styles' => [], 'map' => ['gestureHandling' => 'none']], $view->options('google'));
        self::assertSame([], $view->options('openstreetmap'));
        self::assertSame(['id' => 'myMap', 'center' => ['lat' => 0.0, 'lng' => 0.0], 'zoom' => 2, 'markers' => [['position' => ['lat' => 1.0, 'lng' => 2.0], 'title' => 'One', 'color' => null]], 'controls' => true], $view->toArray());

        $this->expectException(InvalidArgumentException::class);
        new MapView(new LatLng(0, 0), 25);
    }
}
