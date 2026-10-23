<?php

namespace Omnimap\Tests;

use Omnimap\Geocoding\GeocoderPhp;
use Omnimap\Model\LatLng;
use Omnimap\Testing\ArrayCache;
use Omnimap\Testing\FixedGeocoder;
use PHPUnit\Framework\TestCase;

final class GeocoderPhpTest extends TestCase
{
    private const PLACES = ['Osaka, Japan' => ['lat' => 34.6937, 'lng' => 135.5023, 'locality' => 'Osaka', 'country' => 'Japan', 'countryCode' => 'JP']];

    public function testAPlaceFoundThenKept(): void
    {
        $provider = new FixedGeocoder(self::PLACES);
        $cache = new ArrayCache();
        $geocoder = new GeocoderPhp($provider, $cache, 3600);

        $places = $geocoder->geocode('Osaka, Japan', 'en');
        self::assertCount(1, $places);
        self::assertSame(['lat' => 34.6937, 'lng' => 135.5023], $places[0]->position->toArray());
        self::assertSame(['Osaka, Japan', 'fixed', 'JP'], [$places[0]->label, $places[0]->providedBy, $places[0]->address?->countryCode]);
        self::assertEquals($places, $geocoder->geocode('Osaka, Japan', 'en'));
        self::assertSame(1, $provider->calls, 'the second answer from the cache');
        self::assertSame([3600], array_values($cache->ttls));

        self::assertSame([], $geocoder->geocode('Atlantis'));
        self::assertSame('Osaka, Japan', $geocoder->reverse(new LatLng(34.694, 135.502))[0]->label);
    }

    public function testThePaceBetweenTwoCalls(): void
    {
        $geocoder = new GeocoderPhp(new FixedGeocoder(self::PLACES), null, 0, 0.2);
        $start = microtime(true);
        $geocoder->geocode('a');
        $geocoder->geocode('b');
        $geocoder->geocode('c');

        self::assertGreaterThanOrEqual(0.4, microtime(true) - $start);
    }
}
