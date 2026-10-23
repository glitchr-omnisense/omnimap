<?php

namespace Omnimap\Testing;

use Geocoder\Collection;
use Geocoder\Model\AddressBuilder;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

/**
 * A geocoder-php provider that knows a few places by heart (and counts the
 * questions it was asked): GeocoderPhp checked with no network.
 */
final class FixedGeocoder implements Provider
{
    public int $calls = 0;

    /** @param array<string, array{lat: float, lng: float, locality?: string, country?: string, countryCode?: string}> $places */
    public function __construct(private readonly array $places)
    {
    }

    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        ++$this->calls;
        $place = $this->places[$query->getText()] ?? null;
        if (null === $place) {
            return new AddressCollection([]);
        }

        return new AddressCollection([$this->address($place)]);
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        ++$this->calls;
        foreach ($this->places as $place) {
            if (abs($place['lat'] - $query->getCoordinates()->getLatitude()) < 0.01 && abs($place['lng'] - $query->getCoordinates()->getLongitude()) < 0.01) {
                return new AddressCollection([$this->address($place)]);
            }
        }

        return new AddressCollection([]);
    }

    public function getName(): string
    {
        return 'fixed';
    }

    /** @param array{lat: float, lng: float, locality?: string, country?: string, countryCode?: string} $place */
    private function address(array $place): \Geocoder\Location
    {
        $builder = new AddressBuilder($this->getName());
        $builder->setCoordinates($place['lat'], $place['lng']);
        $builder->setLocality($place['locality'] ?? null);
        $builder->setCountry($place['country'] ?? null);
        $builder->setCountryCode($place['countryCode'] ?? null);

        return $builder->build();
    }
}
